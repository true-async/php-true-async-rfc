<?php

declare(strict_types=1);

namespace Async;

final class TaskGroup implements Awaitable, ScopeProvider, SpawnStrategy
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
    
    #[\Override] public function provideScope(): ?Scope {}
    
    #[\Override]
    public function afterCoroutineEnqueue(Coroutine $coroutine, Scope $scope): void {}
    
    #[\Override]
    public function beforeCoroutineEnqueue(Coroutine $coroutine, Scope $scope): array {}
    
    /**
     * Cancel a task group.
     */
    public function cancel(CancellationException $cancellationException): void {}
    
    public function disposeResults(): void {}
    
    public function dispose(): void {}
    
    public function isFinished(): bool {}
    
    public function isClosed(): bool {}
    
    public function onFinally(\Closure $callback): void {}
}