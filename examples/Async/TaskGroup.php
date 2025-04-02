<?php

declare(strict_types=1);

namespace Async;

final class TaskGroup implements Awaitable
{
    public function firstTask(): Awaitable {}
    public function getResults(): array {}
}