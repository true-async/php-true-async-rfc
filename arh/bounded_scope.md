#### BoundedScope

The `BoundedScope` class is designed to create explicit constraints
that will be applied to all coroutines spawned within the specified `Scope`.
It allows controlling both the lifetime of the `Scope`
and the handling of exceptions that may be delivered to the `Scope`
(see [Exception Handling](#exception-handling)).

The `BoundedScope` API is extracted into a separate class to clearly define
its responsibility, distinguishing it from other places where `Scope` might be used.

For example:
```php

use Async\BoundedScope;
use Async\Scope;

function socketListen(): void
{
    $scope = new Scope();    

    $scope->spawn(function() {
        while ($socket = stream_socket_accept($serverSocket, 0)) {
            $scope->spawn(handleConnection(...), $socket);
        }
    });

    await $scope;
}
```

This code creates a separate `Scope` to group all coroutines related to request handling.
However, it has a few issues:

* If an exception occurs in `handleConnection`, the entire server will stop running.
* If `handleConnection` mistakenly creates child coroutines and forgets to close them, it will lead to a resource leak.

`BoundedScope` helps solve both problems:

* Instead of handling every request within the same `Scope`,
  we will create a separate `BoundedScope` for each request.
* `BoundedScope` will have a lifetime limited to the coroutine handling the request.
  This way, if the coroutine unexpectedly terminates, all its child coroutines will be canceled along with it.
* `BoundedScope` allows defining an exception handler that isolates the server
  and other coroutines from errors occurring within a specific request.

```php
use Async\BoundedScope;
use Async\Scope;

function socketListen(): void
{
    $scope = new Scope();    

    $scope->spawn(function() {
        while ($socket = stream_socket_accept($serverSocket, 0)) {
            BoundedScope::inherit($scope)->spawnAndBound(handleConnection(...), $socket);
        }
    });

    await $scope;
}
```


The `BoundedScope` class implements the following pattern:

```php
$scope = new Scope();

$constraints = new Future();

$scope->spawn(function () use($constraints) {
    
    try {
        await $constraints;
    } finally {
        \Async\currentScope()->cancel();
    }    
});
```

Here, `$constraints` is an object implementing the `Awaitable` interface.
Once it completes, the `Scope` will be terminated, and all associated resources will be released.


| Method                                                      | Description                                                                                             |
|-------------------------------------------------------------|---------------------------------------------------------------------------------------------------------|
| `defineTimeout(int $milliseconds)`                          | Define a specified timeout, automatically canceling coroutines when the time expires.                   |
| `spawnAndBound(callable $coroutine, ...$args)`              | Spawns a coroutine and restricts the lifetime of the entire Scope to match the coroutine’s lifetime.    |
| `boundScope(Awaitable $constraint)`                         | Limits the scope’s lifetime based on a **Cancellation token, Future, or another coroutine's lifetime**. |
| `setExceptionHandler(callable $exceptionHandler)`           | Sets an exception handler for the Scope.                                                                |
| `setChildScopeExceptionHandler(callable $exceptionHandler)` | Sets an exception handler for child scopes.                                                             |

```php
$scope = new BoundedScope();
$scope->defineTimeout(1000);

$scope->spawnAndBound(function() {
    sleep(2);
    echo "Task 1\n";
});

await $scope;
```

##### defineTimeout

The `defineTimeout` method sets a global timeout for all coroutines belonging to a `Scope`.
The method initializes a single internal timer, which starts when `defineTimeout` is called.
When the timer expires, the `Scope::cancel()` method is invoked.

The `defineTimeout` method can only be called once; a repeated call will throw an exception.

##### spawnAndBound/boundScope

The `BoundedScope` class allows defining a single lifetime constraint that influences
the duration of the `Scope`.

Two methods are available for this purpose:
- `boundScope`
- `spawnAndBound`

The `boundScope` method limits the `Scope`'s lifetime based on the state
of an object implementing the `Awaitable` interface
(e.g., a coroutine, another `Scope`, `Future`, or `Cancellation`).

The `boundScope` method can be called multiple times, as well as `spawnAndBound`,
but only the last constraint will take effect.

`spawnAndBound` is shorthand for the expression:
`$scope->boundScope($scope->spawn(...))`.