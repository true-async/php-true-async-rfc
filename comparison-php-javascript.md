# PHP True Async vs JavaScript: API Comparison

This document compares the coroutine APIs of PHP True Async and JavaScript's async/await model.

## Overview

| Aspect | PHP True Async | JavaScript |
|--------|----------------|------------|
| **Model** | Implicit async (no keywords in function signatures) | Explicit async (`async` keyword required) |
| **Primary Primitive** | `Coroutine` object | `Promise` object |
| **Coroutine Creation** | `spawn(callable, ...args)` | `async function()` call or `new Promise()` |
| **Awaiting** | `await($completable)` | `await expression` |
| **Cancellation** | Built-in, cooperative | Not built-in (AbortController for some APIs) |

## Coroutine/Promise Definition

| Feature | PHP True Async | JavaScript |
|---------|----------------|------------|
| **Syntax** | Regular function (no special syntax) | `async function name()` |
| **Marking** | None required | `async` keyword mandatory |
| **Return Type** | Any (transparent) | Always returns `Promise` |
| **Callable in sync context** | Yes (runs synchronously) | Returns Promise immediately |

### Code Examples

**PHP True Async:**
```php
// Any function can be a coroutine - no special syntax
function fetchData(string $url): string {
    return file_get_contents($url);
}

// Launch as coroutine
$coro = spawn(fetchData(...), 'https://example.com');
```

**JavaScript:**
```javascript
// Must be marked with async
async function fetchData(url) {
    const response = await fetch(url);
    return await response.text();
}

// Calling returns a Promise
const promise = fetchData('https://example.com');
```

## Coroutine/Task Launching

| Feature | PHP True Async | JavaScript |
|---------|----------------|------------|
| **Function** | `spawn(callable, ...args)` | Call async function directly |
| **Returns** | `Coroutine` object | `Promise` object |
| **Arguments** | Passed to spawn | Passed to function call |
| **Start behavior** | Queued, deferred start | Sync until first `await`, then microtask |
| **Manual creation** | N/A | `new Promise((resolve, reject) => {})` |

### Code Examples

**PHP True Async:**
```php
use function Async\spawn;

// Arguments passed to spawn()
$coro = spawn('file_get_contents', 'https://php.net');

// Using first-class callable
$coro = spawn(fetchData(...), 'https://php.net');

// Multiple coroutines
$coro1 = spawn(task1(...));
$coro2 = spawn(task2(...));
```

**JavaScript:**
```javascript
// Direct call returns Promise
const promise = fetchData('https://example.com');

// Manual Promise creation
const promise = new Promise((resolve, reject) => {
    setTimeout(() => resolve('done'), 1000);
});

// Multiple tasks
const promise1 = task1();
const promise2 = task2();
```

## Awaiting Results

| Feature | PHP True Async | JavaScript |
|---------|----------------|------------|
| **Syntax** | `await($completable)` | `await expression` |
| **Type** | Function call | Language keyword |
| **Context requirement** | Anywhere | Inside `async` function only* |
| **Multiple awaits** | Same result (idempotent) | Same result (idempotent) |
| **With timeout** | `await($coro, $cancellation)` | `Promise.race([promise, timeout])` |

*Top-level await available in ES modules

### Code Examples

**PHP True Async:**
```php
use function Async\spawn;
use function Async\await;

$coro = spawn(fetchData(...), 'https://php.net');
$result = await($coro);

// With cancellation/timeout
$result = await(
    spawn(fetchData(...), 'https://php.net'),
    spawn('sleep', 5)  // timeout after 5 seconds
);
```

**JavaScript:**
```javascript
const promise = fetchData('https://example.com');
const result = await promise;

// With timeout
const result = await Promise.race([
    fetchData('https://example.com'),
    new Promise((_, reject) =>
        setTimeout(() => reject(new Error('Timeout')), 5000)
    )
]);
```

## Yielding Control (Suspension)

| Feature | PHP True Async | JavaScript |
|---------|----------------|------------|
| **Function** | `suspend()` | `await Promise.resolve()` or `await 0` |
| **Explicit yield** | Yes, dedicated function | No, use resolved promise |
| **Throws on cancel** | `Cancellation` exception | N/A (no native cancellation) |

### Code Examples

**PHP True Async:**
```php
use function Async\suspend;

function processItems(array $items): void {
    foreach ($items as $item) {
        process($item);
        suspend(); // Yield control to other coroutines
    }
}
```

**JavaScript:**
```javascript
async function processItems(items) {
    for (const item of items) {
        process(item);
        await Promise.resolve(); // Yield to event loop (microtask)
        // or: await new Promise(resolve => setTimeout(resolve, 0)); // macrotask
    }
}
```

## Cancellation

| Feature | PHP True Async | JavaScript |
|---------|----------------|------------|
| **Native support** | Yes, built-in | No (Promises are not cancellable) |
| **Method** | `$coroutine->cancel(?Cancellation)` | N/A |
| **Exception** | `\Cancellation` | N/A |
| **Workaround** | N/A | `AbortController` / `AbortSignal` |
| **API support** | All coroutines | Only fetch, some DOM APIs |

### Code Examples

**PHP True Async:**
```php
use function Async\spawn;
use function Async\await;

$coro = spawn(function() {
    try {
        await(spawn('sleep', 10));
    } catch (\Cancellation $e) {
        echo "Cancelled: " . $e->getMessage();
    }
});

$coro->cancel(new \Cancellation("Timeout"));
```

**JavaScript:**
```javascript
// AbortController - only works with APIs that support it
const controller = new AbortController();
const signal = controller.signal;

const promise = fetch('https://example.com', { signal })
    .then(response => response.text())
    .catch(err => {
        if (err.name === 'AbortError') {
            console.log('Fetch was cancelled');
        }
    });

controller.abort(); // Cancel the fetch

// Custom cancellation pattern
function cancellablePromise(executor) {
    let cancel;
    const promise = new Promise((resolve, reject) => {
        cancel = () => reject(new Error('Cancelled'));
        executor(resolve, reject);
    });
    return { promise, cancel };
}
```

## Coroutine/Promise State Inspection

| Method/Property | PHP True Async | JavaScript |
|-----------------|----------------|------------|
| **Get ID** | `$coro->getId()` | N/A |
| **Is running** | `$coro->isRunning()` | N/A |
| **Is completed** | `$coro->isCompleted()` | N/A (no direct check)* |
| **Is cancelled** | `$coro->isCancelled()` | N/A |
| **Get result** | `$coro->getResult()` | Must use `.then()` |
| **Get exception** | `$coro->getException()` | Must use `.catch()` |
| **Is started** | `$coro->isStarted()` | N/A (always started) |
| **Is suspended** | `$coro->isSuspended()` | N/A |

*JavaScript Promises don't expose their state directly. You must use callbacks.

### Code Examples

**PHP True Async:**
```php
$coro = spawn(fetchData(...), 'https://php.net');

if ($coro->isCompleted()) {
    $result = $coro->getResult();
    $error = $coro->getException();
}

echo "Coroutine ID: " . $coro->getId();
echo "Is running: " . ($coro->isRunning() ? 'yes' : 'no');
```

**JavaScript:**
```javascript
// Must track state manually
let state = 'pending';
let result, error;

const promise = fetchData('https://example.com')
    .then(r => { state = 'fulfilled'; result = r; return r; })
    .catch(e => { state = 'rejected'; error = e; throw e; });

// Or use a wrapper
function trackablePromise(promise) {
    let state = 'pending', result, error;
    promise
        .then(r => { state = 'fulfilled'; result = r; })
        .catch(e => { state = 'rejected'; error = e; });
    return {
        promise,
        getState: () => state,
        getResult: () => result,
        getError: () => error
    };
}
```

## Cleanup (Finally)

| Feature | PHP True Async | JavaScript |
|---------|----------------|------------|
| **Method** | `$coro->finally(callback)` | `promise.finally(callback)` |
| **Function** | `Async\finally(callback)` | N/A |
| **Callback args** | Receives coroutine | No arguments |
| **Execution timing** | On completion | On settlement |

### Code Examples

**PHP True Async:**
```php
use function Async\spawn;
use function Async\finally;

function task(): void {
    $file = fopen('file.txt', 'r');
    finally(fn() => fclose($file));

    // Work with file...
}

$coro = spawn('task');
$coro->finally(function($completedCoro) {
    echo "Coroutine " . $completedCoro->getId() . " completed\n";
});
```

**JavaScript:**
```javascript
async function task() {
    const file = await openFile('file.txt');
    try {
        // Work with file...
    } finally {
        await file.close();
    }
}

const promise = task();
promise.finally(() => {
    console.log('Task completed');
});
```

## Multiple Coroutines/Promises

| Operation | PHP True Async | JavaScript |
|-----------|----------------|------------|
| **Wait all** | Loop with `await()` | `Promise.all([...])` |
| **Wait all (settled)** | N/A | `Promise.allSettled([...])` |
| **Wait first success** | Via Scope RFC | `Promise.any([...])` |
| **Wait first (any)** | `await($coro, $cancellation)` | `Promise.race([...])` |
| **Get all coroutines** | `Async\get_coroutines()` | N/A |

### Code Examples

**PHP True Async:**
```php
use function Async\spawn;
use function Async\await;

$coro1 = spawn(fetchData(...), 'url1');
$coro2 = spawn(fetchData(...), 'url2');

// Wait for all
$result1 = await($coro1);
$result2 = await($coro2);

// Get all running coroutines
$all = Async\get_coroutines();
```

**JavaScript:**
```javascript
const promise1 = fetchData('url1');
const promise2 = fetchData('url2');

// Wait for all
const [result1, result2] = await Promise.all([promise1, promise2]);

// Wait for all (including failures)
const results = await Promise.allSettled([promise1, promise2]);

// Wait for first
const firstResult = await Promise.race([promise1, promise2]);

// Wait for first success
const firstSuccess = await Promise.any([promise1, promise2]);
```

## Error Handling

| Feature | PHP True Async | JavaScript |
|---------|----------------|------------|
| **Mechanism** | try/catch with exceptions | try/catch or .catch() |
| **Unhandled in coroutine** | Triggers graceful shutdown | Unhandled rejection warning |
| **Cancellation exception** | `\Cancellation` | N/A (no native) |
| **Recommended catch** | `catch (\Exception)` | `catch (error)` |

### Code Examples

**PHP True Async:**
```php
use function Async\spawn;
use function Async\await;

try {
    await(spawn(function() {
        throw new \Exception("Error");
    }));
} catch (\Exception $e) {
    echo "Caught: " . $e->getMessage();
}
// Note: catch (\Exception) won't catch \Cancellation
```

**JavaScript:**
```javascript
// With async/await
try {
    await failingTask();
} catch (error) {
    console.log('Caught:', error.message);
}

// With promises
failingTask()
    .then(result => console.log(result))
    .catch(error => console.log('Caught:', error.message));
```

## Graceful Shutdown

| Feature | PHP True Async | JavaScript |
|---------|----------------|------------|
| **Trigger** | Unhandled exception / `Async\shutdown()` | N/A (no native) |
| **Behavior** | Cancels all coroutines | N/A |
| **Exit/Die** | Triggers graceful shutdown | `process.exit()` (Node.js) |
| **Deadlock detection** | Built-in, throws `DeadlockError` | No built-in detection |

## Key Philosophical Differences

| Aspect | PHP True Async | JavaScript |
|--------|----------------|------------|
| **Function coloring** | No (any function can be coroutine) | Yes (`async` functions are different) |
| **Cancellation** | First-class, built-in | Not built-in, requires workarounds |
| **State inspection** | Full state access | No direct state access |
| **Code migration** | Minimal changes needed | Requires `async`/`await` everywhere |
| **I/O transparency** | Same API for sync/async | Different patterns (callbacks → promises) |
| **Scheduler control** | `suspend()` for explicit yield | Implicit via microtask queue |
| **Multiple results** | Coroutine holds single result | Promise holds single result |

## Promise vs Coroutine Semantics

| Aspect | PHP `Coroutine` | JavaScript `Promise` |
|--------|-----------------|---------------------|
| **Represents** | Running computation | Future value |
| **Execution** | Active (has lifecycle) | Passive (just a value container) |
| **Cancellation** | Can cancel execution | Cannot cancel |
| **State methods** | Rich inspection API | No inspection |
| **Debugging** | Trace, location info | Stack trace only on error |
