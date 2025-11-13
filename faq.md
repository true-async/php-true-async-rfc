# PHP True Async RFC - Frequently Asked Questions

### Q: What is the main goal of this RFC?

**A:** The RFC aims to provide a standardized way to write concurrent code in PHP without requiring developers 
to rewrite existing synchronous code. The key value proposition is that existing code works **exactly the same** 
inside a coroutine without modifications, unlike explicit async/await models.

### Q: How is this different from Fibers?

**A:** Fibers and True Async serve different purposes:
- **Fibers** are low-level symmetric execution contexts where programmers explicitly control switching
- **True Async** is a high-level API where coroutine switching is managed automatically by the scheduler

They are incompatible because they manage the same resources (execution context, stacks) 
in fundamentally different ways. 
Using Fibers violates the "Strict Layering" principle by exposing low-level primitives in a high-level language.

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

**50+ PHP functions** have been adapted to work asynchronously:

**DNS Functions:**
- `gethostbyname()`, `gethostbyaddr()`, `gethostbynamel()`

**Database Functions:**
- **PDO MySQL**: `PDO::__construct()`, `PDO::prepare()`, `PDO::exec()`, `PDOStatement::execute()`, `PDOStatement::fetch()`
- **MySQLi**: `mysqli_connect()`, `mysqli_query()`, `mysqli_prepare()`, `mysqli_stmt_execute()`, `mysqli_fetch_*()`

**CURL Functions:**
- `curl_exec()`, `curl_multi_exec()`, `curl_multi_select()`, `curl_multi_getcontent()`, etc.

**Socket Functions:**
- `socket_connect()`, `socket_accept()`, `socket_read()`, `socket_write()`, `socket_send()`, `socket_recv()`, etc.

**Stream Functions:**
- `file_get_contents()`, `fread()`, `fwrite()`, `fopen()`, `fclose()`, `stream_socket_client()`, `stream_socket_server()`, etc.

**Process Execution:**
- `proc_open()`, `exec()`, `shell_exec()`, `system()`, `passthru()`

**Sleep/Timer Functions:**
- `sleep()`, `usleep()`, `time_nanosleep()`, `time_sleep_until()`

**Output Buffer Functions:**
- `ob_start()`, `ob_flush()`, `ob_clean()`, `ob_get_contents()`, `ob_end_clean()` (with coroutine isolation)

**Key principle:** All these functions automatically become non-blocking when used in async context, 
allowing other coroutines to continue execution while waiting for I/O operations to complete. 
From the developer's perspective, the code looks identical to synchronous code.

See full list: https://github.com/true-async/php-async#adapted-php-functions

---

**Note:** This FAQ is based on RFC version 1.5 and discussions through November 2025. For the most current information, see:
- RFC: http://wiki.php.net/rfc/true_async
- GitHub: https://github.com/true-async
- Discussion archives: https://externals.io/
