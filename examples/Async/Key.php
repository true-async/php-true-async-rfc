<?php

declare(strict_types=1);

namespace Async;

final readonly class Key
{
    public string $description;
    public function __construct(string $description = '') {}
    public function __toString(): string {}
}