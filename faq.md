# PHP True Async RFC - Frequently Asked Questions

## Executive Summary: What This RFC Proposes

This RFC proposes adding **built-in concurrency support** to PHP through two core components:

### 1. Coroutines via `spawn()`

Launch any PHP function as a lightweight coroutine that can be suspended and resumed:
```php
use function Async\spawn;
use function Async\await;

$coroutine = spawn(file_get_contents(...), 'https://php.net');
$result = await($coroutine);
```

### 2. Non-blocking I/O Functions
**50+ existing PHP functions** automatically become non-blocking when used inside coroutines:
- **Database**: PDO MySQL, MySQLi operations
- **Network**: CURL, sockets, streams, DNS lookups
- **Files**: `file_get_contents()`, `fread()`, `fwrite()`
- **Process**: `exec()`, `shell_exec()`, `proc_open()`
- **Timers**: `sleep()`, `usleep()`

**Key principle:** From the developer's perspective, these functions work identically to their synchronous versions. 
The difference is that they suspend only the current coroutine instead of blocking the entire PHP process.

See full list: https://github.com/true-async/php-async#adapted-php-functions

### What This RFC Does NOT Propose
- **No changes to existing synchronous behavior** - code without coroutines works exactly as before
- **No new syntax keywords** - uses function calls (`spawn()`, `await()`, `suspend()`)
- **No changes to Fiber API** - Fibers and True Async are mutually exclusive by design
- **No structured concurrency primitives** - covered in separate [Scope RFC](https://wiki.php.net/rfc/true_async_scope)

## General Questions

### Q: What is the main goal of this RFC?

**A:** The RFC aims to provide a standardized way to write concurrent code in PHP without requiring developers 
to rewrite existing synchronous code. The key value proposition is that existing code works **exactly the same** 
inside a coroutine without modifications, unlike explicit async/await models.

### Q: How is this different from Fibers?

**A:** Fibers and True Async serve fundamentally different purposes and cannot coexist:

**Fibers:**
- Low-level symmetric execution contexts
- Programmer explicitly controls switching (`$fiber->resume()`, `Fiber::suspend()`)
- Direct access to execution stack management
- Suitable for building custom scheduling solutions

**True Async:**
- High-level asymmetric coroutines
- Automatic switching managed by the scheduler
- Transparent to the developer
- Designed for business logic, not infrastructure

**Why they can't work together:**

1. **Resource conflicts**: Both manage the same low-level resources (execution context, CPU stack) in incompatible ways
2. **Architectural incompatibility**: Mixing symmetric (Fibers) and asymmetric (coroutines) models creates unpredictable behavior
3. **Abstraction level**: Fibers expose low-level primitives in a high-level language, violating the "Strict Layering" principle

**Why not map Fiber::suspend() to Async\suspend()?**

This would create a leaky abstraction:
- Fibers require explicit scheduling decisions (who to resume? when?)
- True Async scheduler makes these decisions automatically
- Mixing both models would break scheduler guarantees and lead to race conditions
- The execution models are fundamentally incompatible (symmetric vs asymmetric)

If you need Fibers' explicit control, use Fibers. 
If you want automatic concurrency for I/O-bound applications, use True Async. 
Attempting to unify them would result in a solution that's neither simple nor safe.

### Q: Isn't this just Fibers 2.0?

**A:** No. While both deal with execution contexts:
- Fibers require explicit switching and manual control
- True Async provides automatic scheduling and high-level primitives
- They solve different problems at different abstraction levels
- They are mutually exclusive by design

### Q: Can I use this with FPM?

**A:** Yes! True Async works in all execution modes including FPM. 
The reactor activates within the context of `php_request_startup/php_request_shutdown()`, 
requiring no SAPI modifications.

### Q: What about `exit` and `die`?

**A:** They always trigger **Graceful Shutdown** mode:
- All coroutines in globalScope are cancelled
- Application continues execution without restrictions to shut down naturally
- This allows proper cleanup operations

### Q: Do I need to rewrite my existing code?

**A:** No. The main value of this implementation is that existing synchronous code works inside coroutines without modification. 
You can gradually adopt async features where beneficial.

### Q: How does this RFC affect I/O functions?

**A:** From the coroutine's perspective, I/O functions **do not change their behavior**
they work exactly as they always have. 
However, functions that previously blocked the entire PHP process now only suspend the current coroutine, 
allowing other coroutines to continue executing.

---

**Note:** This FAQ is based on RFC version 1.5 and discussions through November 2025. For the most current information, see:
- RFC: http://wiki.php.net/rfc/true_async
- GitHub: https://github.com/true-async
- Discussion archives: https://externals.io/
