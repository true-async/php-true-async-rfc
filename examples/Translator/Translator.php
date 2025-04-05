<?php

declare(strict_types=1);

namespace Translator;

use Async\Coroutine;
use Async\Scope;
use LongPoll;

/**
 * ### **Example: "Translator" Service**
 *
 * A **Translator** service that translates user messages using an **HTTP service** (e.g., AI-based translation).
 *
 * The service receives lines from the client as they arrive, sends them to the translation service,
 * and returns the results asynchronously as they become available.
 */
final class Translator
{
    private Scope $scope;
    private Scope $connectionScope;
    
    public function __construct(private LongPoll $longPoll, private \TranslatorHttpClient $translatorClient)
    {
        $this->scope = new Scope();
        $this->connectionScope = Scope::inherit($this->scope);
        
        // Define an exception handle for all child scopes
        // Handling exceptions from child **Scopes** ensures that errors in child coroutines do not propagate
        // to the current **Scope** and do not crash the entire application.
        $this->scope->setChildScopeExceptionHandler(static function (Scope $scope, Coroutine $coroutine, \Throwable $exception): void {
            echo "Occurred an exception: {$exception->getMessage()} in Coroutine {$coroutine->getSpawnLocation()}\n";
        });
    }
    
    public function start(): void
    {
        spawn with $this->scope $this->run();
    }
    
    private function run(): void
    {
        while (($socket = $this->longPoll->receive()) !== null) {
            spawn with $this->connectionScope $this->handleRequest($socket);
        }
    }
    
    private function handleRequest(\Socket $socket): void
    {
        with Scope::inherit()->asNotSafely() as $scope {
            try {
                $this->handleLines($socket);
            } catch (\Throwable $exception) {
                $response = json_encode(['error' => $exception->getMessage(), 'code' => $exception->getCode()]);
                socket_write($socket, $response);
            } finally {
                socket_close($socket);
            }
        }
    }
    
    private function handleLines(\Socket $socket): void
    {
        // Try to read lines separated by \n
        // Eof equals to \n\n
        
        while (($data = socket_read($socket, 4096)) !== false) {
            $lines = explode("\n", $data);
            
            foreach ($lines as $line) {
                if ($line === '') {
                    return;
                }
                
                spawn use ($line, $socket) {
                    $translation = $this->translatorClient->translate($line);
                    socket_write($socket, json_encode(['translation' => $translation, 'line' => $line, 'code' => 200]));
                };
            }
        }
    }
    
    public function stop(): void
    {
        $this->scope->cancel();
    }
    
    public function __destruct()
    {
        $this->stop();
    }
}