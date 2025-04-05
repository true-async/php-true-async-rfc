<?php

declare(strict_types=1);

namespace RetryWithBackoff;

class RetryWithBackoff
{
    public function __construct(
        private readonly \Closure $fn,
        private readonly int $maxRetries = 5,
        private readonly int $initialDelayMs = 100,
        private readonly bool $useJitter = false
    ) {}
    
    public function __invoke(): mixed
    {
        $attempt = 0;
        $delay = $this->initialDelayMs;
        
        while (true) {
            try {
                return await ($this->fn)();
            } catch (\Exception $exception) {
                if (++$attempt > $this->maxRetries) {
                    throw $exception;
                }
                
                $sleep = $this->useJitter
                    ? random_int((int)($delay * 0.5), $delay)
                    : $delay;
                
                Async\delay($sleep * 1000);
                $delay *= 2;
            }
        }
    }
}
