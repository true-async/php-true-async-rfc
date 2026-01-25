# PHP True Async vs Python asyncio: API Comparison

This document compares the coroutine APIs of PHP True Async and Python's asyncio module.

## Overview

| Aspect                 | PHP True Async                                      | Python asyncio                                |
|------------------------|-----------------------------------------------------|-----------------------------------------------|
| **Model**              | Implicit async (no keywords in function signatures) | Explicit async (`async def` keyword required) |
| **Coroutine Creation** | `spawn(callable, ...args)`                          | `asyncio.create_task(coro)`                   |
| **Awaiting**           | `await($completable)`                               | `await expression`                            |
| **Yield Control**      | `suspend()`                                         | `await asyncio.sleep(0)`                      |
| **Cancellation**       | Built-in, cooperative                               | Built-in, cooperative                         |

## Coroutine Definition

| Feature                      | PHP True Async                       | Python asyncio                |
|------------------------------|--------------------------------------|-------------------------------|
| **Syntax**                   | Regular function (no special syntax) | `async def function_name():`  |
| **Marking**                  | None required                        | `async` keyword mandatory     |
| **Return Type**              | Any (transparent)                    | `Coroutine` object            |
| **Callable in sync context** | Yes (runs synchronously)             | No (returns coroutine object) |

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

**Python asyncio:**
```python
# Must be marked with async
async def fetch_data(url: str) -> str:
    async with aiohttp.ClientSession() as session:
        async with session.get(url) as response:
            return await response.text()

# Create task
task = asyncio.create_task(fetch_data('https://example.com'))
```

## Coroutine Launching

| Feature            | PHP True Async                        | Python asyncio                      |
|--------------------|---------------------------------------|-------------------------------------|
| **Function**       | `spawn(callable, ...args)`            | `asyncio.create_task(coro)`         |
| **Returns**        | `Coroutine` object                    | `Task` object                       |
| **Arguments**      | Passed directly to spawn              | Passed when calling async function  |
| **Start behavior** | Queued, starts on scheduler iteration | Starts on next event loop iteration |

### Code Examples

**PHP True Async:**
```php
use function Async\spawn;

// Arguments passed to spawn()
$coro = spawn('file_get_contents', 'https://php.net');

// Using first-class callable
$coro = spawn(fetchData(...), 'https://php.net');
```

**Python asyncio:**
```python
import asyncio

# Arguments passed to coroutine function
task = asyncio.create_task(fetch_data('https://python.org'))

# Alternative: asyncio.ensure_future()
task = asyncio.ensure_future(fetch_data('https://python.org'))
```

## Awaiting Results

| Feature             | PHP True Async                | Python asyncio                    |
|---------------------|-------------------------------|-----------------------------------|
| **Syntax**          | `await($completable)`         | `await expression`                |
| **Type**            | Function call                 | Language keyword                  |
| **Multiple awaits** | Same result (idempotent)      | Same result (idempotent)          |
| **With timeout**    | `await($coro, $cancellation)` | `asyncio.wait_for(coro, timeout)` |

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

**Python asyncio:**
```python
import asyncio

task = asyncio.create_task(fetch_data('https://python.org'))
result = await task

# With timeout
result = await asyncio.wait_for(
    fetch_data('https://python.org'),
    timeout=5.0
)
```

## Yielding Control (Suspension)

| Feature              | PHP True Async           | Python asyncio             |
|----------------------|--------------------------|----------------------------|
| **Function**         | `suspend()`              | `await asyncio.sleep(0)`   |
| **Explicit yield**   | Yes, dedicated function  | No, use sleep(0) hack      |
| **Throws on cancel** | `Cancellation` exception | `CancelledError` exception |

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

**Python asyncio:**
```python
import asyncio

async def process_items(items: list) -> None:
    for item in items:
        process(item)
        await asyncio.sleep(0)  # Yield control
```

## Cancellation

| Feature               | PHP True Async                            | Python asyncio                                     |
|-----------------------|-------------------------------------------|----------------------------------------------------|
| **Method**            | `$coroutine->cancel(?Cancellation)`       | `task.cancel(msg=None)`                            |
| **Exception**         | `\Cancellation` (extends `\Throwable`)    | `asyncio.CancelledError` (extends `BaseException`) |
| **Suppression**       | Auto-suppressed if unhandled in coroutine | Must be caught or propagates                       |
| **Multiple cancels**  | First wins                                | Last wins                                          |
| **Self-cancellation** | Allowed (delayed effect)                  | Allowed (immediate)                                |

### Exception Hierarchy

**PHP True Async:**
```
\Throwable
├── \Error
├── \Exception
└── \Cancellation          ← Separate from Exception tree
    └── \Async\AsyncCancellation
```

**Python asyncio:**
```
BaseException
├── Exception
└── CancelledError         ← Separate from Exception tree (since Python 3.8)
```

### Code Examples

**PHP True Async:**
```php
use function Async\spawn;
use function Async\await;

$coro = spawn(function() {
    try {
        await(spawn('sleep', 10));
    } catch (\Cancellation $e) {
        // Cleanup logic
        echo "Cancelled: " . $e->getMessage();
    }
});

$coro->cancel(new \Cancellation("Timeout"));
```

**Python asyncio:**
```python
import asyncio

async def my_task():
    try:
        await asyncio.sleep(10)
    except asyncio.CancelledError:
        print("Task was cancelled")
        raise  # Re-raise is recommended

task = asyncio.create_task(my_task())
task.cancel()
```

## Coroutine State Inspection

| Method/Property   | PHP True Async          | Python asyncio       |
|-------------------|-------------------------|----------------------|
| **Get ID**        | `$coro->getId()`        | N/A (use `id(task)`) |
| **Is running**    | `$coro->isRunning()`    | N/A                  |
| **Is completed**  | `$coro->isCompleted()`  | `task.done()`        |
| **Is cancelled**  | `$coro->isCancelled()`  | `task.cancelled()`   |
| **Get result**    | `$coro->getResult()`    | `task.result()`      |
| **Get exception** | `$coro->getException()` | `task.exception()`   |
| **Is started**    | `$coro->isStarted()`    | N/A (always started) |
| **Is suspended**  | `$coro->isSuspended()`  | N/A                  |

## Cleanup (Finally)

| Feature           | PHP True Async                     | Python asyncio               |
|-------------------|------------------------------------|------------------------------|
| **Method**        | `$coro->finally(callback)`         | `task.add_done_callback(cb)` |
| **Function**      | `Async\finally(callback)`          | N/A                          |
| **Callback args** | Receives coroutine                 | Receives task                |
| **Execution**     | Concurrent (in separate coroutine) | Sequential                   |

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

spawn('task');
```

**Python asyncio:**
```python
import asyncio

async def task():
    file = open('file.txt', 'r')
    try:
        # Work with file...
        pass
    finally:
        file.close()

# Or with callback
def cleanup(task):
    print(f"Task {task} completed")

t = asyncio.create_task(task())
t.add_done_callback(cleanup)
```

## Multiple Coroutines

| Operation              | PHP True Async | Python asyncio                                     |
|------------------------|----------------|----------------------------------------------------|
| **Wait all**           | Via Scope RFC  | `asyncio.gather(*coros)`                           |
| **Wait first**         | Via Scope RFC  | `asyncio.wait(tasks, return_when=FIRST_COMPLETED)` |
| **Wait any**           | Via Scope RFC  | `asyncio.wait(tasks, return_when=FIRST_COMPLETED)` |
| **Get all coroutines** | Via Scope RFC  | `asyncio.all_tasks()`                              |

### Code Examples

**PHP True Async:**
```php
use function Async\spawn;
use function Async\await;
use function Async\get_coroutines;

$coro1 = spawn(fetchData(...), 'url1');
$coro2 = spawn(fetchData(...), 'url2');

$result1 = await($coro1);
$result2 = await($coro2);

// Get all coroutines
$all = get_coroutines();
```

**Python asyncio:**
```python
import asyncio

task1 = asyncio.create_task(fetch_data('url1'))
task2 = asyncio.create_task(fetch_data('url2'))

# Wait for all
results = await asyncio.gather(task1, task2)

# Get all tasks
all_tasks = asyncio.all_tasks()
```

## Graceful Shutdown

| Feature                | PHP True Async                           | Python asyncio                   |
|------------------------|------------------------------------------|----------------------------------|
| **Trigger**            | Unhandled exception / `Async\shutdown()` | `loop.stop()` / signal handlers  |
| **Behavior**           | Cancels all coroutines, then continues   | Stops loop immediately           |
| **Exit/Die**           | Triggers graceful shutdown               | `sys.exit()` raises `SystemExit` |
| **Deadlock detection** | Built-in, throws `DeadlockError`         | No built-in detection            |

## Key Philosophical Differences

| Aspect                   | PHP True Async                     | Python asyncio                         |
|--------------------------|------------------------------------|----------------------------------------|
| **Function coloring**    | No (any function can be coroutine) | Yes (`async` functions are different)  |
| **Cancellation default** | Cancellable by design              | Cancellable by design                  |
| **Code migration**       | Minimal changes needed             | Requires `async`/`await` everywhere    |
| **I/O transparency**     | Same API for sync/async            | Different APIs (aiohttp vs requests)   |
| **Entry point**          | Any code can use async             | Requires `asyncio.run()` or event loop |
