<?php

declare(strict_types=1);

namespace Async;

/**
 * Execute the provided closure in non-cancellable mode.
 */
function protect(\Closure $closure): void {}

function hiPriority(?Scope $scope = null): SpawnStrategy {}

function any(iterable $triggers): Awaitable {}

function all(iterable $triggers): Awaitable {}

function anyOf(int $count, iterable $triggers): Awaitable {}

function ignoreErrors(Awaitable $awaitable, callable $handler): Awaitable {}

function captureErrors(Awaitable $awaitable): Awaitable {}

function delay(int $ms): void {}

function timeout(int $ms): Awaitable {}

function currentContext(): Context {}

function coroutineContext(): Context {}

/**
 * Returns the current coroutine.
 */
function currentCoroutine(): Coroutine {}

/**
 * Returns the root Scope.
 */
function rootContext(): Context {}

/**
 * Returns the list of all coroutines
 *
 * @return Coroutine[]
 */
function getCoroutines(): array {}

function gracefulShutdown(?CancellationException $cancellationException = null): void {}