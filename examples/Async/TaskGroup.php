<?php

declare(strict_types=1);

namespace Async;

final class TaskGroup implements Awaitable
{
    public function race(bool $ignoreErrors = false): Awaitable {}
    public function getResults(): array {}
    public function getErrors(): array {}
    
    public function add(Coroutine $coroutine): self {}
}