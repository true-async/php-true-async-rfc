# PHP True Async vs Go: API Comparison

This document compares the coroutine APIs of PHP True Async and Go's goroutines/channels model.

## Overview

| Aspect                    | PHP True Async                      | Go                                  |
|---------------------------|-------------------------------------|-------------------------------------|
| **Execution Model**       | Single-threaded concurrency         | Multi-threaded parallelism          |
| **Threading**             | One OS thread (event loop)          | Multiple OS threads (M:N scheduler) |
| **Model**                 | Coroutines with await               | Goroutines with channels            |
| **Concurrency Primitive** | `Coroutine` object                  | Goroutine (no object)               |
| **Launch**                | `spawn(callable, ...args)`          | `go func()`                         |
| **Awaiting**              | `await($completable)`               | Channel receive `<-ch`              |
| **Communication**         | Return values                       | Channels                            |
| **Cancellation**          | Built-in `cancel()`                 | `context.Context`                   |

> **Important distinction:**
> - **PHP True Async** provides **concurrency** (interleaved execution on a single thread)
> - **Go** provides **parallelism** (simultaneous execution on multiple CPU cores)
>
> PHP coroutines never run simultaneously — they cooperatively yield control. Go goroutines can execute truly in parallel on different CPU cores.

## Coroutine/Goroutine Definition

| Feature               | PHP True Async      | Go                                |
|-----------------------|---------------------|-----------------------------------|
| **Syntax**            | Regular function    | Regular function                  |
| **Marking**           | None required       | None required                     |
| **Return handling**   | Direct return value | Must use channel or shared memory |
| **Function coloring** | No                  | No                                |

### Code Examples

**PHP True Async:**
```php
// Any function can be a coroutine
function fetchData(string $url): string {
    return file_get_contents($url);
}

// Launch and get result directly
$coro = spawn(fetchData(...), 'https://php.net');
$result = await($coro);
```

**Go:**
```go
// Any function can be a goroutine
func fetchData(url string) string {
    resp, _ := http.Get(url)
    body, _ := io.ReadAll(resp.Body)
    return string(body)
}

// Must use channel to get result
ch := make(chan string)
go func() {
    ch <- fetchData("https://go.dev")
}()
result := <-ch
```

## Launching Concurrent Tasks

| Feature            | PHP True Async                        | Go                                     |
|--------------------|---------------------------------------|----------------------------------------|
| **Syntax**         | `spawn(callable, ...args)`            | `go func()` or `go funcName()`         |
| **Returns**        | `Coroutine` object                    | Nothing (fire and forget)              |
| **Arguments**      | Passed to spawn                       | Passed to function or captured         |
| **Start behavior** | Queued, starts on scheduler iteration | Starts immediately (runtime scheduled) |
| **Handle**         | Coroutine object for control          | No handle (must use channels/context)  |

### Code Examples

**PHP True Async:**
```php
use function Async\spawn;

// Get coroutine handle
$coro = spawn(processData(...), $data);

// Can check status, cancel, await
echo $coro->getId();
$coro->cancel();
```

**Go:**
```go
// No handle returned - fire and forget
go processData(data)

// To get control, must pass channel/context
done := make(chan struct{})
go func() {
    processData(data)
    close(done)
}()
<-done // wait for completion
```

## Awaiting Results

| Feature             | PHP True Async                | Go                              |
|---------------------|-------------------------------|---------------------------------|
| **Syntax**          | `await($completable)`         | `<-channel` or `sync.WaitGroup` |
| **Returns value**   | Yes, directly                 | Via channel                     |
| **Multiple awaits** | Same result (idempotent)      | Channel consumed once*          |
| **With timeout**    | `await($coro, $cancellation)` | `select` with `time.After`      |

*Unless using buffered channel or special patterns

### Code Examples

**PHP True Async:**
```php
use function Async\spawn;
use function Async\await;

// Simple await
$result = await(spawn(fetchData(...), $url));

// With timeout
try {
    $result = await(
        spawn(fetchData(...), $url),
        spawn('sleep', 5)
    );
} catch (AwaitCancelledException $e) {
    echo "Timeout!";
}
```

**Go:**
```go
// Using channel
ch := make(chan string)
go func() {
    ch <- fetchData(url)
}()
result := <-ch

// With timeout using select
select {
case result := <-ch:
    fmt.Println(result)
case <-time.After(5 * time.Second):
    fmt.Println("Timeout!")
}
```

## Yielding Control (Suspension)

| Feature              | PHP True Async           | Go                      |
|----------------------|--------------------------|-------------------------|
| **Function**         | `suspend()`              | `runtime.Gosched()`     |
| **Purpose**          | Yield to scheduler       | Yield to scheduler      |
| **Throws on cancel** | `Cancellation` exception | N/A (use context check) |

### Code Examples

**PHP True Async:**
```php
use function Async\suspend;

function processItems(array $items): void {
    foreach ($items as $item) {
        process($item);
        suspend(); // Yield control
    }
}
```

**Go:**
```go
import "runtime"

func processItems(items []Item) {
    for _, item := range items {
        process(item)
        runtime.Gosched() // Yield control
    }
}
```

## Cancellation

| Feature             | PHP True Async              | Go                       |
|---------------------|-----------------------------|--------------------------|
| **Mechanism**       | `$coroutine->cancel()`      | `context.WithCancel()`   |
| **Exception/Error** | `\Cancellation`             | `context.Canceled` error |
| **Propagation**     | Automatic via await         | Manual (pass context)    |
| **Check cancelled** | `isCancellationRequested()` | `ctx.Err() != nil`       |
| **Cooperative**     | Yes                         | Yes                      |

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

**Go:**
```go
import "context"

ctx, cancel := context.WithCancel(context.Background())

go func(ctx context.Context) {
    select {
    case <-time.After(10 * time.Second):
        fmt.Println("Completed")
    case <-ctx.Done():
        fmt.Println("Cancelled:", ctx.Err())
    }
}(ctx)

cancel() // Cancel the goroutine
```

## State Inspection

| Feature              | PHP True Async            | Go                              |
|----------------------|---------------------------|---------------------------------|
| **Get ID**           | `$coro->getId()`          | `runtime.GoID()` (unofficial)   |
| **Is running**       | `$coro->isRunning()`      | N/A                             |
| **Is completed**     | `$coro->isCompleted()`    | N/A (track manually)            |
| **Is cancelled**     | `$coro->isCancelled()`    | `ctx.Err() == context.Canceled` |
| **Get result**       | `$coro->getResult()`      | N/A (use channel)               |
| **Get exception**    | `$coro->getException()`   | N/A (use channel)               |
| **Get trace**        | `$coro->getTrace()`       | `runtime.Stack()`               |
| **Count goroutines** | `count(get_coroutines())` | `runtime.NumGoroutine()`        |

### Code Examples

**PHP True Async:**
```php
$coro = spawn(task(...));

echo "ID: " . $coro->getId();
echo "Running: " . ($coro->isRunning() ? 'yes' : 'no');
echo "Completed: " . ($coro->isCompleted() ? 'yes' : 'no');

if ($coro->isCompleted()) {
    $result = $coro->getResult();
    $error = $coro->getException();
}
```

**Go:**
```go
// Must track state manually
type TaskResult struct {
    Value interface{}
    Error error
    Done  bool
}

result := &TaskResult{}
go func() {
    defer func() { result.Done = true }()
    // ... task logic
}()

// Check completion
fmt.Println("Done:", result.Done)
fmt.Println("Goroutines:", runtime.NumGoroutine())
```

## Cleanup (Finally/Defer)

| Feature             | PHP True Async                       | Go                        |
|---------------------|--------------------------------------|---------------------------|
| **Syntax**          | `$coro->finally(cb)` / `finally(cb)` | `defer func()`            |
| **Timing**          | On coroutine completion              | On function return        |
| **Execution order** | Concurrent                           | LIFO (last in, first out) |
| **Receives result** | Yes (coroutine passed)               | No (use named returns)    |

### Code Examples

**PHP True Async:**
```php
use function Async\spawn;
use function Async\finally;

function task(): void {
    $file = fopen('file.txt', 'r');
    finally(fn() => fclose($file));

    // Work with file...
    throw new Exception("Error");
    // finally still executes
}
```

**Go:**
```go
func task() {
    file, _ := os.Open("file.txt")
    defer file.Close() // Executes on return

    // Work with file...
    panic("Error")
    // defer still executes
}
```

## Multiple Concurrent Tasks

| Operation         | PHP True Async     | Go                   |
|-------------------|--------------------|----------------------|
| **Wait all**      | Via Scope RFC      | `sync.WaitGroup`     |
| **Wait first**    | Via Scope RFC      | `select` on channels |
| **Fan-out**       | Multiple `spawn()` | Multiple `go`        |
| **Fan-in**        | Via Scope RFC      | Merge channels       |
| **Get all tasks** | `get_coroutines()` | N/A                  |

### Code Examples

**PHP True Async:**
```php
use function Async\spawn;
use function Async\await;

// Fan-out
$coro1 = spawn(fetchData(...), 'url1');
$coro2 = spawn(fetchData(...), 'url2');

// Wait all (basic)
$result1 = await($coro1);
$result2 = await($coro2);
```

**Go:**
```go
import "sync"

// Fan-out with WaitGroup
var wg sync.WaitGroup
results := make([]string, 2)

wg.Add(2)
go func() {
    defer wg.Done()
    results[0] = fetchData("url1")
}()
go func() {
    defer wg.Done()
    results[1] = fetchData("url2")
}()

wg.Wait() // Wait all

// Wait first with select
ch1 := make(chan string)
ch2 := make(chan string)

go func() { ch1 <- fetchData("url1") }()
go func() { ch2 <- fetchData("url2") }()

select {
case r := <-ch1:
    fmt.Println("First:", r)
case r := <-ch2:
    fmt.Println("First:", r)
}
```

## Error Handling

| Feature                    | PHP True Async             | Go                            |
|----------------------------|----------------------------|-------------------------------|
| **Mechanism**              | Exceptions                 | Return errors / panic-recover |
| **Unhandled in coroutine** | Triggers graceful shutdown | Panic crashes program         |
| **Cancellation**           | `\Cancellation` exception  | `context.Canceled` error      |
| **Propagation**            | Automatic via await        | Manual (return error)         |

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
```

**Go:**
```go
// Using error channel
errCh := make(chan error, 1)
resultCh := make(chan string, 1)

go func() {
    result, err := riskyOperation()
    if err != nil {
        errCh <- err
        return
    }
    resultCh <- result
}()

select {
case err := <-errCh:
    fmt.Println("Error:", err)
case result := <-resultCh:
    fmt.Println("Result:", result)
}

// Using panic-recover
go func() {
    defer func() {
        if r := recover(); r != nil {
            fmt.Println("Recovered:", r)
        }
    }()
    panic("Error")
}()
```

## Communication Patterns

| Pattern           | PHP True Async       | Go                          |
|-------------------|----------------------|-----------------------------|
| **Return value**  | Direct via `await()` | Channel                     |
| **Bidirectional** | Via shared state     | Channels (bidirectional)    |
| **Broadcast**     | Via Scope RFC        | Close channel / `sync.Cond` |
| **Pipeline**      | Coroutine chains     | Channel pipelines           |

### Code Examples

**Go Channel Pipeline:**
```go
func gen(nums ...int) <-chan int {
    out := make(chan int)
    go func() {
        for _, n := range nums {
            out <- n
        }
        close(out)
    }()
    return out
}

func sq(in <-chan int) <-chan int {
    out := make(chan int)
    go func() {
        for n := range in {
            out <- n * n
        }
        close(out)
    }()
    return out
}

// Usage: pipeline
for n := range sq(gen(1, 2, 3)) {
    fmt.Println(n) // 1, 4, 9
}
```

## Graceful Shutdown

| Feature                | PHP True Async                     | Go                                 |
|------------------------|------------------------------------|------------------------------------|
| **Trigger**            | Unhandled exception / `shutdown()` | Signal handling / context          |
| **Behavior**           | Cancels all coroutines             | Manual cancellation required       |
| **Exit/Die**           | Triggers graceful shutdown         | `os.Exit()` terminates immediately |
| **Deadlock detection** | Built-in, throws `DeadlockError`   | Runtime detects, panics            |

### Code Examples

**PHP True Async:**
```php
use function Async\shutdown;

// Graceful shutdown cancels all coroutines
shutdown(new \Cancellation("Shutting down"));
```

**Go:**
```go
import (
    "context"
    "os/signal"
    "syscall"
)

ctx, stop := signal.NotifyContext(
    context.Background(),
    syscall.SIGINT, syscall.SIGTERM,
)
defer stop()

go worker(ctx)

<-ctx.Done()
fmt.Println("Shutting down...")
```

## Key Philosophical Differences

| Aspect                     | PHP True Async                           | Go                                                                   |
|----------------------------|------------------------------------------|----------------------------------------------------------------------|
| **Execution model**        | Concurrency (single-threaded)            | Parallelism (multi-threaded)                                         |
| **CPU utilization**        | One core (I/O-bound workloads)           | Multiple cores (CPU-bound + I/O-bound)                               |
| **Race conditions**        | Impossible (no true parallelism)         | Possible (requires mutexes/channels)                                 |
| **Communication**          | Via Scope RFC                            | "Don't communicate by sharing memory; share memory by communicating" |
| **Concurrency handle**     | First-class Coroutine object             | No handle (fire and forget)                                          |
| **Result retrieval**       | Built-in await                           | Must use channels                                                    |
| **Cancellation**           | Built-in to coroutine                    | Separate context pattern                                             |
| **State inspection**       | Rich API                                 | Minimal (by design)                                                  |
| **Error handling**         | Exceptions                               | Return values + panic                                                |
| **Structured concurrency** | Via Scope RFC                            | Manual with WaitGroup/errgroup                                       |

## Comparison Summary

| Aspect              | PHP True Async                       | Go                                   |
|---------------------|--------------------------------------|--------------------------------------|
| **Execution**       | Concurrency (single-threaded)        | Parallelism (multi-threaded)         |
| **Best for**        | I/O-bound workloads                  | CPU-bound + I/O-bound workloads      |
| **Thread safety**   | No races (single thread)             | Requires synchronization             |
| **Learning curve**  | Familiar async/await                 | Requires understanding channels      |
| **Boilerplate**     | Minimal                              | More verbose for results             |
| **Type safety**     | Dynamic                              | Static (compile-time)                |
| **Debugging**       | Rich introspection                   | Minimal by design                    |
| **Performance**     | Depends on implementation            | Highly optimized runtime             |
| **Cancellation**    | Simpler (built-in)                   | More flexible (context tree)         |
