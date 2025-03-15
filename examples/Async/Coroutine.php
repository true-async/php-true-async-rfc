<?php

declare(strict_types=1);

namespace Async;

final class Coroutine implements Awaitable
{
    private function __construct(\Closure $coroutine) {}
    public function getId(): int {}
    
    public function getContext(): Context {}
    
    public function getScope(): Scope {}
    
    public function getTrace(): array {}
    
    public function getParentCoroutine(): ?Coroutine {}
    
    public function getSpawnFileAndLine(): array {}
    
    public function getSpawnLocation(): string {}
    
    public function getSuspendFileAndLine(): array {}
    
    public function getSuspendLocation(): string {}
    
    public function isStarted(): bool {}
    
    public function isSuspended(): bool {}
    
    public function isCancelled(): bool {}
    
    public function isFinished(): bool {}
    
    public function getAwaitingInfo(): array {}
    
    public function cancel(\Throwable $throwable): void {}
}