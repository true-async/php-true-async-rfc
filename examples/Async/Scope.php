<?php

declare(strict_types=1);

namespace Async;

class Scope extends Context implements Awaitable
{
    public static function inherit(?Scope $parentScope = null): Scope
    {
    
    }
    
    public function __construct()
    {
    
    }
    
    public function spawn(\Closure $callable, ...$params): Coroutine
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