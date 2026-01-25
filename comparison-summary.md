# Coroutines API Comparison: PHP True Async vs Python vs JavaScript

A comprehensive side-by-side comparison of async/await implementations.

## Quick Reference Table

| Feature                  | PHP True Async         | Python asyncio                       | JavaScript                          |
|--------------------------|------------------------|--------------------------------------|-------------------------------------|
| **Coroutine Definition** | Regular function       | `async def func():`                  | `async function func()`             |
| **Launch Coroutine**     | `spawn(fn, ...args)`   | `asyncio.create_task(coro)`          | `fn()` (returns Promise)            |
| **Await Result**         | `await($coro)`         | `await coro`                         | `await promise`                     |
| **Yield Control**        | `suspend()`            | `await asyncio.sleep(0)`             | `await Promise.resolve()`           |
| **Cancel**               | `$coro->cancel()`      | `task.cancel()`                      | N/A (AbortController for some APIs) |
| **Cancel Exception**     | `\Cancellation`        | `CancelledError`                     | N/A                                 |
| **Is Completed**         | `$coro->isCompleted()` | `task.done()`                        | N/A                                 |
| **Is Cancelled**         | `$coro->isCancelled()` | `task.cancelled()`                   | N/A                                 |
| **Get Result**           | `$coro->getResult()`   | `task.result()`                      | `.then(r => ...)`                   |
| **On Complete**          | `$coro->finally(cb)`   | `task.add_done_callback(cb)`         | `promise.finally(cb)`               |
| **Wait All**             | Await RFC              | `asyncio.gather(...)`                | `Promise.all([...])`                |
| **Wait First**           | `await($a, $b)`        | `asyncio.wait(..., FIRST_COMPLETED)` | `Promise.race([...])`               |
| **Get All Tasks**        | `get_coroutines()`     | `asyncio.all_tasks()`                | N/A                                 |
| **Current Coroutine**    | `current_coroutine()`  | `asyncio.current_task()`             | N/A                                 |

## Syntax Comparison

### Creating and Running Coroutines

```php
// PHP True Async
use function Async\{spawn, await};

function fetchData($url) {
    return file_get_contents($url);
}

$result = await(spawn(fetchData(...), 'https://php.net'));
```

```python
# Python asyncio
import asyncio

async def fetch_data(url):
    # async HTTP client needed
    return await some_async_http_get(url)

result = await asyncio.create_task(fetch_data('https://python.org'))
```

```javascript
// JavaScript
async function fetchData(url) {
    const response = await fetch(url);
    return await response.text();
}

const result = await fetchData('https://example.com');
```

### Cancellation

```php
// PHP True Async - Native cancellation
$coro = spawn(longTask(...));
$coro->cancel(new \Cancellation("Timeout"));

try {
    await($coro);
} catch (\Cancellation $e) {
    echo "Cancelled";
}
```

```python
# Python asyncio - Native cancellation
task = asyncio.create_task(long_task())
task.cancel()

try:
    await task
except asyncio.CancelledError:
    print("Cancelled")
```

```javascript
// JavaScript - No native cancellation, use AbortController
const controller = new AbortController();

fetch(url, { signal: controller.signal })
    .catch(e => {
        if (e.name === 'AbortError') console.log('Cancelled');
    });

controller.abort();
```

### Timeout Pattern

```php
// PHP True Async
use function Async\{spawn, await};
use Async\AwaitCancelledException;

try {
    $result = await(
        spawn(slowOperation(...)),
        spawn('sleep', 5)  // 5 second timeout
    );
} catch (AwaitCancelledException $e) {
    echo "Timeout!";
}
```

```python
# Python asyncio
import asyncio

try:
    result = await asyncio.wait_for(slow_operation(), timeout=5.0)
except asyncio.TimeoutError:
    print("Timeout!")
```

```javascript
// JavaScript
const timeout = ms => new Promise((_, reject) =>
    setTimeout(() => reject(new Error('Timeout')), ms));

try {
    const result = await Promise.race([
        slowOperation(),
        timeout(5000)
    ]);
} catch (e) {
    if (e.message === 'Timeout') console.log('Timeout!');
}
```

## Feature Matrix

### Core Capabilities

| Capability                            | PHP   | Python   | JavaScript   |
|---------------------------------------|:-----:|:--------:|:------------:|
| Implicit async (no function coloring) |   ✅   |    ❌     |      ❌       |
| Native cancellation                   |   ✅   |    ✅     |      ❌       |
| Coroutine state inspection            |   ✅   |    ✅     |      ❌       |
| Deadlock detection                    |   ✅   |    ❌     |      ❌       |
| Graceful shutdown                     |   ✅   |    ❌     |      ❌       |
| Structured concurrency                |  ✅*   |   ✅**    |      ❌       |
| Sync code reuse in async              |   ✅   |    ❌     |      ❌       |

*Via Scope RFC
**Via TaskGroup (Python 3.11+)

### Cancellation Features

| Feature                                        | PHP   | Python   | JavaScript |
|------------------------------------------------|:-----:|:--------:|:----------:|
| Cancel any coroutine                           |   ✅   |    ✅     | ❌ |
| Cancel with reason                             |   ✅   |   ✅***   | ❌ |
| Cancellation propagation                       |   ✅   |    ✅     | ❌ |
| Self-cancellation                              |   ✅   |    ✅     | N/A |
| Cancellation exception separate from Exception |   ✅   |    ✅     | N/A |

***Python 3.9+ supports cancel message

### State Inspection

| State Check | PHP | Python | JavaScript |
|-------------|:---:|:------:|:----------:|
| Is started | ✅ | ❌ | ❌ |
| Is running | ✅ | ❌ | ❌ |
| Is suspended | ✅ | ❌ | ❌ |
| Is queued | ✅ | ❌ | ❌ |
| Is completed | ✅ | ✅ | ❌ |
| Is cancelled | ✅ | ✅ | ❌ |
| Get result | ✅ | ✅ | ❌* |
| Get exception | ✅ | ✅ | ❌* |
| Get trace | ✅ | ❌ | ❌ |
| Get spawn location | ✅ | ❌ | ❌ |

*Must use .then()/.catch() callbacks

## Exception Hierarchy Comparison

```
PHP True Async:
┌─────────────────────────────────────────┐
│ \Throwable                              │
│ ├── \Error                              │
│ │   └── Async\AsyncError                │
│ │       └── Async\DeadlockError         │
│ ├── \Exception                          │
│ │   └── Async\AsyncException            │
│ │       └── Async\AwaitCancelledException│
│ └── \Cancellation  ←── NOT Exception!   │
│     └── Async\AsyncCancellation         │
└─────────────────────────────────────────┘

Python asyncio:
┌─────────────────────────────────────────┐
│ BaseException                           │
│ ├── Exception                           │
│ │   └── ... (all regular exceptions)    │
│ └── CancelledError ←── NOT Exception!   │
└─────────────────────────────────────────┘

JavaScript:
┌─────────────────────────────────────────┐
│ Error                                   │
│ ├── TypeError                           │
│ ├── RangeError                          │
│ └── ... (all errors are the same tree)  │
│                                         │
│ AbortError (from AbortController)       │
│ └── extends DOMException                │
└─────────────────────────────────────────┘
```

## Code Migration Effort

| Migration Scenario     | PHP            | Python                              | JavaScript                        |
|------------------------|----------------|-------------------------------------|-----------------------------------|
| Sync → Async function  | None           | Add `async def` + internal `await`s | Add `async` + internal `await`s   |
| Call async from sync   | `spawn()` only | `asyncio.run()` required            | Cannot (need callback or .then()) |
| Use sync libs in async | Works          | Need async alternatives             | Need async alternatives           |
| Error handling changes | Minimal        | Add `CancelledError` handling       | Same patterns                     |

## Unique Features

### PHP True Async Only
- **No function coloring** - any function works as coroutine
- **Deadlock detection** - automatic detection with diagnostics
- **Graceful shutdown** - controlled application termination
- **Rich state inspection** - spawn location, suspend location, trace
- **Transparent I/O** - same functions work sync and async

### Python asyncio Only
- **Mature ecosystem** - extensive async library support
- **TaskGroup** - structured concurrency (Python 3.11+)
- **asyncio.gather** - built-in parallel await with return_exceptions

### JavaScript Only
- **Promise.allSettled** - get all results regardless of failures
- **Promise.any** - first successful result
- **Top-level await** - in ES modules
- **Microtask queue** - well-defined execution order

## Summary

| Aspect                | Winner           | Reason                                     |
|-----------------------|------------------|--------------------------------------------|
| **Ease of migration** | PHP              | No code changes for existing functions     |
| **Cancellation**      | PHP/Python (tie) | Both have native, cooperative cancellation |
| **State inspection**  | PHP              | Most comprehensive debugging API           |
| **Ecosystem**         | Python           | Most mature async library ecosystem        |
| **Simplicity**        | JavaScript       | Promise is simpler concept than coroutine  |
| **Safety**            | PHP              | Deadlock detection, graceful shutdown      |
