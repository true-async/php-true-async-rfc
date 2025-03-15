<?php

declare(strict_types=1);

namespace Async;

class CoroutineScope
{
    public static function inherit(?CoroutineScope $parentScope = null): CoroutineScope
    {
    
    }
    
    public function __construct()
    {
    
    }
    
    public function spawn(callable $callable, ...$params): Coroutine
    {
    
    }
    
    public function spawnWith(CoroutineScope $scope, callable $callable, ...$params): Coroutine
    {
    
    }
    
    public function find(string|object $key): mixed
    {
    
    }
    
    public function get(string|object $key): mixed
    {
    
    }
    
    public function set(string|object $key, mixed $value): void
    {
    
    }
    
    public function unset(string|object $key): void
    {
    
    }
    
    public function setExceptionHandler(callable $exceptionHandler): void
    {
    
    }
    
    public function setChildScopeExceptionHandler(callable $exceptionHandler): void
    {
    
    }
    
    public function cancel(): void
    {
    
    }
    
    public function dispose(): void
    {
    
    }
}