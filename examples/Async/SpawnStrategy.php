<?php

declare(strict_types=1);

namespace Async;

interface SpawnStrategy extends ScopeProvider
{
    /**
     * Called before a coroutine is spawned, before it is enqueued.
     *
     * @param   Coroutine   $coroutine  The coroutine to be spawned.
     * @param   Scope       $scope      The Scope instance.
     *
     */
    public function beforeCoroutineEnqueue(Coroutine $coroutine, Scope $scope): array;
    
    /**
     * Called after a coroutine is spawned, enqueued.
     *
     * @param Coroutine $coroutine
     * @param Scope     $scope
     */
    public function afterCoroutineEnqueue(Coroutine $coroutine, Scope $scope): void;
}