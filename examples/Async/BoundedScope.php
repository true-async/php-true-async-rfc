<?php

declare(strict_types=1);

namespace Async;

final class BoundedScope extends Scope
{
    public function spawnAndBound(\Closure $coroutine): Coroutine
    {
    
    }
    
    public function withTimeout(int $milliseconds): void
    {
    
    }
    
    public function boundedBy(Awaitable $constraint): void
    {
    
    }
}