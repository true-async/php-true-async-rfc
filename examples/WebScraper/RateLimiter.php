<?php

declare(strict_types=1);

namespace WebScraper;

use Async\Awaitable;
use Async\Future;

final class RateLimiter
{
    private int $active = 0;
    private array $queue = [];
    
    public function __construct(private int $limit) {}
    
    public function acquire(): Awaitable
    {
        if ($this->active < $this->limit) {
            $this->active++;
            return new Future(true);
        }
        
        $future = new Future();
        $this->queue[] = $future;
        return $future;
    }
    
    public function release(): void
    {
        $this->active--;
        
        if (!empty($this->queue)) {
            $future = array_shift($this->queue);
            $this->active++;
            $future->complete(true);
        }
    }
}