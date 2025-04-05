<?php

declare(strict_types=1);

namespace Async;

interface ContextManager
{
    public function enter(): Scope;
    public function exit(): void;
}
