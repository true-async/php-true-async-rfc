<?php

declare(strict_types=1);

namespace Async;

class Scope
{
    public readonly Context $context;
    
    /**
     * Creates a new Scope that inherits from the specified one. If the parameter is not provided,
     * the Scope inherits from the current one.
     *
     * @param Scope|null $parentScope
     *
     * @return Scope
     */
    public static function inherit(?Scope $parentScope = null): Scope {}
    
    public function __construct() {}
    
    public function spawn(\Closure $callable, ...$params): Coroutine {}
    
    public function cancel(?CancellationException $cancellationException = null): void {}
    
    public function directTasks(): Awaitable {}
    
    public function allTasks(): Awaitable {}
    
    public function isFinished(): bool {}
    
    public function isClosed(): bool {}
    
    /**
     * Sets an error handler that is called when an exception is passed to the Scope from one of its child coroutines.
     */
    public function setExceptionHandler(callable $exceptionHandler): void {}
    
    /**
     * Exception handler for child Scope.
     * Setting this handler prevents the exception from propagating to the current Scope.
     */
    public function setChildScopeExceptionHandler(callable $exceptionHandler): void {}
    
    public function onCompletion(\Closure $callback): void {}
    
    public function dispose(): void {}
    
    public function disposeSafely(): void {}
    
    public function disposeAfterTimeout(int $timeout): void {}
}