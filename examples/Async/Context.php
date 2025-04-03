<?php

declare(strict_types=1);

namespace Async;

final class Context
{
    public function find(string|object $key): mixed {}
    public function get(string|object $key): mixed {}
    public function has(string|object $key): bool {}
    public function set(string|object $key, mixed $value, bool $replace = false): self {}
    public function unset(string|object $key): self {}
    public function findLocal(string|object $key): mixed {}
    public function getLocal(string|object $key): mixed {}
    public function hasLocal(string|object $key): bool {}
    public function dispose(): void {}
}