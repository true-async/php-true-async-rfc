<?php

declare(strict_types=1);

namespace PeriodicHeartbeatQueue;

use Async\Scope;
use function Async\spawn;
use WeakReference;

/**
 * https://github.com/amphp/websocket/blob/2.x/src/PeriodicHeartbeatQueue.php
 */
final class PeriodicHeartbeatQueue
{
    private array $clients = [];
    private Scope $scope;
    private int   $heartbeatPeriod;
    private int            $queuedPingLimit;
    
    public function __construct(int $heartbeatPeriod = 60, int $queuedPingLimit = 2)
    {
        $this->heartbeatPeriod = $heartbeatPeriod;
        $this->queuedPingLimit = $queuedPingLimit;
        
        $this->scope           = new Scope();
        // Stop the queue when an exception occurs.
        $this->scope->setExceptionHandler($this->stop(...));
        
        $this->startHeartbeat();
    }
    
    public function __destruct()
    {
        $this->stop();
    }
    
    public function addClient(WebSocketClient $client): void
    {
        $this->clients[spl_object_id($client)] = WeakReference::create($client);
    }
    
    private function startHeartbeat(): void
    {
        $this->scope->spawn(function () {
            while (true) {
                foreach ($this->clients as $id => $weakRef) {
                    
                    $client = $weakRef->get();
                    if ($client === null) {
                        unset($this->clients[$id]);
                        continue;
                    }
                    
                    if ($client->getPendingPings() >= $this->queuedPingLimit) {
                        $client->close();
                        unset($this->clients[$id]);
                        continue;
                    }
                    
                    $this->scope->spawn($client->ping(...));
                }
                
                sleep($this->heartbeatPeriod);
            }
        });
    }
    
    public function stop(): void
    {
        $this->scope->cancel();
    }
}
