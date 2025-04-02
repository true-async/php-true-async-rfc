<?php

declare(strict_types=1);

namespace Async;

/**
 * Execute the provided closure in non-cancellable mode.
 */
function protect(\Closure $closure): void {}

function any(iterable $futures): Awaitable {}

function all(iterable $futures): Awaitable {}

function anyOf(int $limit, iterable $futures): Awaitable {}

function ignoreErrors(Awaitable $awaitable): Awaitable {}

function withErrors(Awaitable $awaitable): Awaitable {}

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