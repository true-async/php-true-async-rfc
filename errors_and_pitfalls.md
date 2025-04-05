# Common Errors and Pitfalls

This document describes common mistakes developers might encounter when using the PHP True Async API, 
including examples and suggested best practices to avoid them.

## 1. Spawn and Await Issues

### Forgotten Await
Launching coroutines without awaiting results.

```php
spawn file_get_contents('file.txt');
// Missing await, coroutine might not finish properly.
```

### Misuse of Await
Using `await` unnecessarily.

```php
await spawn file_get_contents('file.txt'); // Unnecessary await
```

## 2. Scope Management Errors

### Coroutine Leaks (Zombie Coroutines)
Coroutines continue running after scope disposal.

```php
$scope = new Scope();
spawn with $scope {
  Async\delay(5000);
};
$scope->dispose(); // Coroutine canceled, warning triggered
```

### Reusing Closed Scope
Attempting to reuse a canceled or disposed Scope.

```php
$scope->cancel();
spawn with $scope { /* Fatal error */ };
```

### Forgotten Await on Scope
Not awaiting coroutines within Scope.

```php
$scope = new Scope();
spawn with $scope throw new Exception();
// No await or exception handler set, exception ignored.
```

## 3. Cancellation Pitfalls

### Ignoring CancellationException
Improperly handling coroutine cancellations.

```php
try {
  await spawn someTask();
} catch (\Throwable $e) {
  // Incorrectly catches CancellationException
}
```

### Critical Sections without Protection
Cancellation interrupts critical operations.

```php
$db->beginTransaction();
await someCoroutine(); // Possible cancellation, transaction hangs
$db->commit();
// Use Async\protect() instead.
```

## 4. TaskGroup Mistakes

### Wrong Scope for TaskGroup
Adding coroutine from a different Scope.

```php
$tg = new TaskGroup($scope);
$tg->add(spawn otherScopeTask()); // Exception: Wrong scope
```

### Adding the Same Coroutine Twice

```php
$task = spawn task();
$tg->add($task);
$tg->add($task); // Exception: Task already added
```

### Ignoring TaskGroup Errors
Not handling exceptions properly with `race()` or `firstResult()`.

```php
$result = await $tg->race();
// Potential errors ignored
```

## 5. Async Block Errors

### Misunderstanding 'bounded'

```php
async bounded $scope {
  spawn longRunningTask();
} // Task auto-canceled when block exits
```

## 6. Exception Handling Mistakes

### Lost Exceptions
Exceptions not handled or awaited.

```php
spawn {throw new Exception();} // Lost exception
```

### Missing Exception Handlers

```php
$scope = new Scope();
spawn with $scope {throw new Exception();} // Entire scope canceled
```

## 7. Context Issues

### Resource Leaks in Context
Objects in context persisting unexpectedly.

```php
currentContext()->set('db', $pdo);
// Potential resource leak
```

### Local vs. Inherited Context Confusion

```php
coroutineContext()->set('data', 'x');
spawn {
  currentContext()->get('data'); // null, separate local context
};
```

## 8. Structured Concurrency Errors

### Deadlocks
Mutual coroutine dependencies.

```php
$task1 = spawn await $task2;
$task2 = spawn await $task1;
```

### Incorrect Resource Closure
Premature resource release.

```php
fclose($resource);
await $childTaskUsingResource;
```

## 9. I/O and Suspend Issues

### Unprotected I/O Operations
Unexpected coroutine switches.

```php
fwrite($socket, 'part1');
fwrite($socket, 'part2');
// Without Async\protect(), could be interrupted
```

### Improper Suspend Usage
Not handling cancellation during `suspend`.

```php
suspend; // Cancellation possible
```

## 10. Graceful Shutdown and Deadlocks

### Misuse of Graceful Shutdown
Creating new coroutines during shutdown.

```php
Async\gracefulShutdown();
spawn newTask(); // Task immediately canceled
```

### Deadlock Handling

```php
$task = spawn await neverCompletingTask();
await $task; // Deadlock situation
```

## Best Practices

- Clearly manage coroutine lifetimes with explicit Scopes.
- Always await coroutine results when necessary.
- Properly handle exceptions and cancellations.
- Avoid coroutine leaks and Scope reuse errors.
- Regularly use diagnostic tools (`getCoroutines()`, `isSuspended()`, etc.) to detect and resolve issues early.

