<?php

declare(strict_types=1);

namespace BackgroundLogger;

use Async\Scope;
use Async\TaskGroup;

class BackgroundLogger
{
    private TaskGroup $taskGroup;
    private Scope $scope;
    
    public function __construct()
    {
        $this->scope = new Scope();
        $this->taskGroup = new TaskGroup($this->scope);
    }
    
    public function logAsync(string $message): void
    {
        $this->taskGroup->spawn(static function() use($message) {
            try {
                file_put_contents(
                    'app.log',
                    date('[Y-m-d H:i:s] ') . $message . PHP_EOL,
                    FILE_APPEND
                );
            } catch (\Throwable $e) {
                error_log("Async log failed: " . $e->getMessage());
            }
        });
    }
    
    public function __destruct()
    {
        $this->scope->dispose();
    }
}

$logger = new BackgroundLogger;

function processOrder(Order $order, BackgroundLogger $logger): void
{
    $logger->logAsync("Order processed: " . $order->id);
    
    spawn use ($order, $logger) {
        try {
            Mailer::send($order->getUserEmail(), "Order Confirmation");
        } catch (\Throwable $e) {
            $logger->logAsync("Email failed: " . $e->getMessage());
        }
    };
}

processOrder(new Order('1', 'test@test.com'), $logger);