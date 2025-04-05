<?php

declare(strict_types=1);

namespace Async;

interface SpawnedCoroutineAcceptor
{
    /**
     * Accepts a spawned coroutine.
     *
     * @param Coroutine $coroutine
     */
    public function acceptCoroutine(Coroutine $coroutine): void;
}