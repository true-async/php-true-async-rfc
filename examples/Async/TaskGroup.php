<?php

declare(strict_types=1);

namespace Async;

final class TaskGroup implements Awaitable
{
    public function __construct(
        private ?Scope  $scope = null,
        private bool $captureResults    = false,
        private bool $bounded = false,
    ) {}
    
    public function all(bool $ignoreErrors = false, $nullOnFail = false): Awaitable {}
    public function race(bool $ignoreErrors = false): Awaitable {}
    public function firstResult(bool $ignoreErrors = false): Awaitable {}
    public function getResults(): array {}
    public function getErrors(): array {}
    
    public function add(Coroutine ...$coroutines): self {}
    
    public function spawn(\Closure $closure, mixed ...$args): Coroutine {}
    
    /**
     * Cancel a task group.
     */
    public function cancel(CancellationException $cancellationException): void {}
    
    public function disposeResults(): void {}
    
    public function dispose(): void {}
    
    public function isFinished(): bool {}
    
    public function isClosed(): bool {}
}