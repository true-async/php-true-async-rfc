<?php

declare(strict_types=1);

namespace AsyncRabbitMQ;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Async\Coroutine;
use Async\Scope;

final class RabbitMQListener
{
    private AMQPStreamConnection $connection;
    private Scope                $scope;
    private Scope                $consumeScope;
    
    public function __construct(string $host, int $port, string $user, string $password)
    {
        $this->connection = new AMQPStreamConnection($host, $port, $user, $password);
        $this->scope = new Scope();
        $this->consumeScope = new Scope();
    }
    
    public function __destruct()
    {
        $this->cancelAll();
    }
    
    public function listen(array $queues): void
    {
        $channel = $this->connection->channel();
        
        foreach ($queues as $queue) {
            $channel->queue_declare($queue, false, true, false, false);
            
            $callback = function (AMQPMessage $msg) use ($queue) {
                // handle a message in a separate coroutine
                $this->scope->spawn($this->handleMessage(...), $msg, $queue);
            };
            
            $channel->basic_consume('', false, true, false, false, $callback);
        }
        
        // non-blocking consume
        $this->scope->spawn(static function () use ($channel) {
            while ($channel->is_consuming()) {
                $channel->wait();
            }
        });
    }
    
    public function await(): void
    {
        await($this->scope);
        await($this->consumeScope);
    }
    
    private function handleMessage(AMQPMessage $msg, string $queue): void
    {
        try {
            echo "[{$queue}] Received: " . $msg->body . "\n";
            // emulate processing
            sleep(1);
            echo "[{$queue}] Processed: " . $msg->body . "\n";
        } catch (\Throwable $exception) {
            echo "Error processing message: " . $exception->getMessage() . "\n";
        }
    }
    
    public function stop(): void
    {
        $this->scope->cancel();
        try {
            await $this->scope->allTasks();
        } finally {
            $this->connection->close();
        }
    }
    
    public function cancelAll(): void
    {
        $this->scope->cancel();
        $this->consumeScope->cancel();
        $this->connection->close();
    }
}
