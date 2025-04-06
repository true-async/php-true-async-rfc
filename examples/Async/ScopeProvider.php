<?php

declare(strict_types=1);

namespace Async;

interface ScopeProvider
{
    public function provideScope(): ?Scope;
}