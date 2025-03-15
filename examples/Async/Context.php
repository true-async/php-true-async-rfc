<?php

declare(strict_types=1);

namespace Async;

class Context
{
    public function find(string|object $key): mixed {}
    public function get(string|object $key): mixed {}
    public function set(string|object $key, mixed $value, bool $replace = false): self {}
    public function unset(string|object $key): self {}
}