<?php

declare(strict_types=1);

use Async\Coroutine;
use Async\Scope;

final class RequestHandler
{
    private Scope $scope;
    
    public function __construct()
    {
        $this->scope = new Scope();
        $this->scope->setChildScopeExceptionHandler(static function (Scope $scope, Coroutine $coroutine, \Throwable $exception): void {
            echo "Occurred an exception: {$exception->getMessage()} in Coroutine {$coroutine->getSpawnLocation()}\n";
        });
    }
    
    public function start(): void
    {
        $server = stream_socket_server('tcp://0.0.0.0:8080');
        
        spawn with $this->scope use($server) {
            try {
                while (($client = stream_socket_accept($server)) !== false) {
                    $child = Scope::inherit($this->scope);
                    $coroutine = spawn with $child $this->handleConnection($client);
                    $coroutine->onComplete(static fn() => $child->dispose());
                }
            } finally {
                fclose($server);
                $this->scope->cancel();
            }
        };
    }
    
    private function handleConnection($client): void
    {
        try {
            $request = fread($client, 1024);
            $response = "HTTP/1.1 200 OK\r\nContent-Length: 12\r\n\r\nHello World!";
            fwrite($client, $response);
        } finally {
            fclose($client);
        }
    }
}

$server = new RequestHandler();
$server->start();