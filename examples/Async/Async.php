<?php

declare(strict_types=1);

namespace Async;

/**
 * Initiates the execution of the function $fn in an asynchronous context with arguments.
 * Returns a Coroutine object that describes the coroutine.
 *
 * @param \Closure $fn
 * @param          ...$args
 *
 * @return Coroutine
 */
function spawn(\Closure $fn, ...$args): Coroutine {}

/**
 * Suspends the execution of the current coroutine.
 */
function suspend(): void {}

/**
 * Suspends the execution of the current coroutine until the awaitable completing.
 * The awaitable can be a Coroutine or a CoroutineScope object.
 *
 * Returns the result of the awaitable or throws an exception if the awaitable fails.
 *
 * @param Awaitable $awaitable
 *
 * @return mixed
 */
function await(Awaitable $awaitable): mixed {}

function currentContext(): Context {}

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