<?php

declare(strict_types=1);

namespace Async;

function spawn(callable $fn, ...$args): Coroutine
{
}

function await(Coroutine|CoroutineScope $awaitable): mixed
{

}