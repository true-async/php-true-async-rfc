<?php

declare(strict_types=1);

namespace Async;

class Scope implements Awaitable
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
    
    public function cancel(): void {}
    
    public function onExit(\Closure $callback): void {}
}