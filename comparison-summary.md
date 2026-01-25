# Coroutines API Comparison: PHP True Async vs Python vs JavaScript vs Go

A comprehensive side-by-side comparison of async/concurrency implementations.

## Execution Model

| Aspect              | PHP True Async   | Python asyncio   | JavaScript      | Go              |
|---------------------|------------------|------------------|-----------------|-----------------|
| **Threading**       | Single-threaded  | Single-threaded  | Single-threaded | Multi-threaded  |
| **Model**           | Concurrency      | Concurrency      | Concurrency     | Parallelism     |
| **CPU cores**       | 1                | 1                | 1               | Multiple        |
| **Race conditions** | Impossible       | Impossible       | Impossible      | Possible        |
| **Best for**        | I/O-bound        | I/O-bound        | I/O-bound       | I/O + CPU-bound |

> **Concurrency vs Parallelism:**
> - **PHP, Python, JavaScript** — tasks are interleaved on a single thread (concurrent but not parallel)
> - **Go** — tasks can execute simultaneously on multiple CPU cores (true parallelism)

## Quick Reference Table

| Feature                  | PHP True Async         | Python asyncio                  | JavaScript                | Go                       |
|--------------------------|------------------------|---------------------------------|---------------------------|--------------------------|
| **Coroutine Definition** | Regular function       | `async def func():`             | `async function func()`   | Regular function         |
| **Launch**               | `spawn(fn, ...args)`   | `asyncio.create_task(coro)`     | `fn()` → Promise          | `go fn()`                |
| **Await Result**         | `await($coro)`         | `await coro`                    | `await promise`           | `<-channel`              |
| **Yield Control**        | `suspend()`            | `await asyncio.sleep(0)`        | `await Promise.resolve()` | `runtime.Gosched()`      |
| **Cancel**               | `$coro->cancel()`      | `task.cancel()`                 | N/A                       | `context.WithCancel()`   |
| **Cancel Exception**     | `\Cancellation`        | `CancelledError`                | N/A                       | `context.Canceled`       |
| **Is Completed**         | `$coro->isCompleted()` | `task.done()`                   | N/A                       | N/A                      |
| **Is Cancelled**         | `$coro->isCancelled()` | `task.cancelled()`              | N/A                       | `ctx.Err() != nil`       |
| **Get Result**           | `$coro->getResult()`   | `task.result()`                 | `.then(r => ...)`         | Via channel              |
| **On Complete**          | `$coro->finally(cb)`   | `task.add_done_callback(cb)`    | `promise.finally(cb)`     | `defer`                  |
| **Wait All**             | Via Await RFC          | `asyncio.gather(...)`           | `Promise.all([...])`      | `sync.WaitGroup`         |
| **Wait First**           | Via Await RFC          | `asyncio.wait(FIRST_COMPLETED)` | `Promise.race([...])`     | `select`                 |
| **Get All Tasks**        | `get_coroutines()`     | `asyncio.all_tasks()`           | N/A                       | `runtime.NumGoroutine()` |
| **Current Task**         | `current_coroutine()`  | `asyncio.current_task()`        | N/A                       | N/A                      |

## Syntax Comparison

### Creating and Running Concurrent Tasks

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

```go
// Go
func fetchData(url string) string {
    resp, _ := http.Get(url)
    body, _ := io.ReadAll(resp.Body)
    return string(body)
}

ch := make(chan string)
go func() { ch <- fetchData("https://go.dev") }()
result := <-ch
```

### Cancellation

```php
// PHP True Async - Native cancellation
$coro = spawn(longTask(...));
$coro->cancel(new \Cancellation("Timeout"));
```

```python
# Python asyncio - Native cancellation
task = asyncio.create_task(long_task())
task.cancel()
```

```javascript
// JavaScript - AbortController (limited support)
const controller = new AbortController();
fetch(url, { signal: controller.signal });
controller.abort();
```

```go
// Go - Context-based cancellation
ctx, cancel := context.WithCancel(context.Background())
go worker(ctx)
cancel()
```

### Timeout Pattern

```php
// PHP True Async
try {
    $result = await(
        spawn(slowOperation(...)),
        spawn('sleep', 5)
    );
} catch (AwaitCancelledException $e) {
    echo "Timeout!";
}
```

```python
# Python asyncio
try:
    result = await asyncio.wait_for(slow_operation(), timeout=5.0)
except asyncio.TimeoutError:
    print("Timeout!")
```

```javascript
// JavaScript
const result = await Promise.race([
    slowOperation(),
    new Promise((_, reject) => setTimeout(() => reject(new Error('Timeout')), 5000))
]);
```

```go
// Go
ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
defer cancel()

select {
case result := <-doWork(ctx):
    fmt.Println(result)
case <-ctx.Done():
    fmt.Println("Timeout!")
}
```

## Feature Matrix

### Core Capabilities

| Capability | PHP | Python | JavaScript | Go |
|------------|:---:|:------:|:----------:|:---:|
| Single-threaded (no race conditions) | ✅ | ✅ | ✅ | ❌ |
| True parallelism (multi-core) | ❌ | ❌ | ❌ | ✅ |
| Implicit async (no function coloring) | ✅ | ❌ | ❌ | ✅ |
| Native cancellation | ✅ | ✅ | ❌ | ✅* |
| Task/coroutine handle | ✅ | ✅ | ✅ | ❌ |
| State inspection | ✅ | ✅ | ❌ | ❌ |
| Deadlock detection | ✅ | ❌ | ❌ | ✅ |
| Graceful shutdown | ✅ | ❌ | ❌ | ❌ |
| Structured concurrency | ✅** | ✅*** | ❌ | ❌ |
| Sync code reuse in async | ✅ | ❌ | ❌ | ✅ |

*Via context.Context
**Via Scope RFC
***Via TaskGroup (Python 3.11+)

### Cancellation Features

| Feature | PHP | Python | JavaScript | Go |
|---------|:---:|:------:|:----------:|:---:|
| Cancel any task | ✅ | ✅ | ❌ | ✅* |
| Cancel with reason | ✅ | ✅ | ❌ | ✅** |
| Cancellation propagation | ✅ | ✅ | ❌ | ✅ |
| Self-cancellation | ✅ | ✅ | N/A | ✅ |
| Cancel exception separate from Exception | ✅ | ✅ | N/A | N/A*** |

*Via context
**Via `context.WithCancelCause` (Go 1.20+)
***Go uses error values, not exceptions

### State Inspection

| State Check | PHP | Python | JavaScript | Go |
|-------------|:---:|:------:|:----------:|:---:|
| Is started | ✅ | ❌ | ❌ | ❌ |
| Is running | ✅ | ❌ | ❌ | ❌ |
| Is suspended | ✅ | ❌ | ❌ | ❌ |
| Is queued | ✅ | ❌ | ❌ | ❌ |
| Is completed | ✅ | ✅ | ❌ | ❌ |
| Is cancelled | ✅ | ✅ | ❌ | ✅* |
| Get result | ✅ | ✅ | ❌** | ❌*** |
| Get exception | ✅ | ✅ | ❌** | ❌*** |
| Get trace | ✅ | ❌ | ❌ | ✅ |
| Get spawn location | ✅ | ❌ | ❌ | ❌ |

*Via `ctx.Err()`
**Must use .then()/.catch() callbacks
***Must use channels

### Multiple Tasks

| Operation | PHP | Python | JavaScript | Go |
|-----------|-----|--------|------------|-----|
| Wait all | Via Scope RFC | `asyncio.gather()` | `Promise.all()` | `sync.WaitGroup` |
| Wait all settled | N/A | `gather(return_exceptions=True)` | `Promise.allSettled()` | `errgroup` |
| Wait first | Via Scope RFC | `asyncio.wait(FIRST_COMPLETED)` | `Promise.race()` | `select` |
| Wait any success | Via Scope RFC | `asyncio.wait(FIRST_COMPLETED)` | `Promise.any()` | `select` |

## Exception/Error Hierarchy Comparison

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
│ ├── TypeError, RangeError, ...          │
│ └── AbortError (DOMException)           │
└─────────────────────────────────────────┘

Go (uses error values, not exceptions):
┌─────────────────────────────────────────┐
│ error (interface)                       │
│ ├── context.Canceled                    │
│ ├── context.DeadlineExceeded            │
│ └── custom errors                       │
│                                         │
│ panic/recover (for exceptional cases)   │
└─────────────────────────────────────────┘
```

## Code Migration Effort

| Migration Scenario | PHP | Python | JavaScript | Go |
|-------------------|-----|--------|------------|-----|
| Sync → Async function | None | Add `async def` | Add `async` | None* |
| Call async from sync | `spawn()` | `asyncio.run()` | Callbacks/`.then()` | `go` + channel |
| Use sync libs in async | Works | Need async alternatives | Need async alternatives | Works |
| Error handling changes | Minimal | Add `CancelledError` | Same patterns | Check `ctx.Err()` |

*But must add channel boilerplate for results

## Communication Model

| Aspect | PHP | Python | JavaScript | Go |
|--------|-----|--------|------------|-----|
| **Primary model** | Return values | Return values | Return values | Channels |
| **Philosophy** | Share memory | Share memory | Share memory | Share by communicating |
| **Bidirectional** | Shared state | Shared state | Shared state | Bidirectional channels |
| **Broadcast** | Via Scope | N/A | N/A | Close channel |

## Unique Features

### PHP True Async Only
- **Rich state inspection** - spawn location, suspend location, trace
- **Graceful shutdown** - controlled application termination
- **Coroutine as first-class object** - full control over lifecycle

### Python asyncio Only
- **Mature ecosystem** - extensive async library support
- **TaskGroup** - structured concurrency (Python 3.11+)

### JavaScript Only
- **Promise.allSettled** - get all results regardless of failures
- **Promise.any** - first successful result
- **Top-level await** - in ES modules

### Go Only
- **True parallelism** - multi-threaded execution on multiple CPU cores
- **Channels** - first-class communication primitive
- **Select** - multiplexing on multiple channels
- **Highly optimized runtime** - millions of goroutines
- **Static typing** - compile-time type safety

## Summary

| Aspect | Winner | Reason |
|--------|--------|--------|
| **True parallelism** | Go | Only one with multi-threaded execution |
| **Thread safety** | PHP/Python/JS | Single-threaded = no race conditions |
| **Ease of migration** | PHP/Go (tie) | No code changes for existing functions |
| **Native cancellation** | PHP | Simplest API, built into coroutine |
| **State inspection** | PHP | Most comprehensive debugging API |
| **Ecosystem** | Python | Most mature async library ecosystem |
| **Simplicity** | JavaScript | Promise is simpler concept |
| **CPU-bound tasks** | Go | Can utilize multiple CPU cores |
| **I/O-bound tasks** | All (tie) | All handle I/O concurrency well |
| **Safety** | PHP | Deadlock detection, graceful shutdown |
