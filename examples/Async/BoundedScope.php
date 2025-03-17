<?php

declare(strict_types=1);

namespace Async;

final class BoundedScope extends Scope
{
    /**
     *
     */
    public function spawnAndBound(\Closure $coroutine, ...$args): Coroutine {}
    public function defineTimeout(int $milliseconds): void {}
    public function boundScope(Awaitable $constraint): void {}
    
    /**
     * Sets an error handler that is called when an exception is passed to the Scope from one of its child coroutines.
     */
    public function setExceptionHandler(callable $exceptionHandler): void {}
    
    /**
     * Exception handler for child Scope.
     * Setting this handler prevents the exception from propagating to the current Scope.
     */
    public function setChildScopeExceptionHandler(callable $exceptionHandler): void {}
    
    /**
     * Returns the bound information of the scope in array format.
     */
    public function getBoundsInfo(): array {}
}