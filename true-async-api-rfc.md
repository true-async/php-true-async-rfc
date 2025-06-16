# TrueAsync API RFC

* Version: 1.0
* Date: 2025-06-16
* Author: Edmond [HT], edmondifthen@proton.me
* Status: Under discussion
* First Published at: 2025-06-16 
* Git: https://github.com/true-async
* Related RFC: https://wiki.php.net/rfc/true_async

## Introduction
The **TrueAsync API** introduces a pluggable framework for asynchronous programming in `PHP`. 
It allows extensions to register their own scheduler, reactor and thread pool implementations while keeping 
the `Zend Engine` independent of any particular event loop library. 

The primary goal is to separate the core `PHP` functions from any specific asynchronous backend 
so that alternative implementations can be swapped in without modifying the engine.

## Motivation
`PHP` currently lacks a unified asynchronous interface, creating significant challenges for the ecosystem:

**Fragmented Async Ecosystem**: Each async library (`ReactPHP`, `Swoole`, `Amp`, etc.) implements its own async primitives, 
leading to incompatible ecosystems that cannot interoperate.

**Core System Limitations**: Many core PHP functions (network I/O, file operations, database connections) 
are inherently blocking and cannot be made async without core-level modifications.

**Garbage Collection Issues**: Since the garbage collector calls destructors during the collection cycle, 
and a destructor in PHP userland may trigger a context switch, the garbage collector must be adapted to support coroutines.

The `TrueAsync API` addresses these problems by providing standardized async primitives in the core. 
The `API` is part of the `Zend Engine` for the following reasons:

1. **Early Initialization**: As a core module it is available before extension initialization, 
enabling `scheduler` and `reactor` to cooperate correctly. 
Critical async infrastructure (like coroutine switch hooks) must be available during 
the earliest stages of the `PHP` lifecycle.

2. **Universal Access**: Core functions and extensions can always reference its symbols regardless of whether a backend is installed.

3. **Garbage Collection Integration**: The garbage collector uses async primitives 
to run cycle collection in dedicated coroutines, preventing user code from being blocked during cycle destruction. 

4. **System-Level Operations**: Core functions like network I/O, 
file operations, and process management need async variants.

## Specification

### API Architecture
The `TrueAsync API` defines a pluggable interface that enables different async backends to be registered by extensions while maintaining implementation flexibility. The core provides standardized async primitives without mandating specific implementations.

**Key Components**:
- **Events**: Low-level representation of sockets, timers and other readiness sources
- **Coroutines**: Stackful tasks
- **Scopes**: Hierarchical lifetime management enabling grouped cancellation
- **Wakers**: Event completion handlers that resume suspended coroutines

### CancellationException
A new root exception class for signaling coroutine cancellation. 
Positioned as a root exception (not extending `Exception`) to ensure generic 
catch blocks don't accidentally intercept cancellation signals. 
Defined in core because fundamental engine functions assume all throwable types are known at startup.

## Impact on Core
The `API` maintains separation between the `Zend Engine` and specific async implementations. Extensions implement async features using their preferred libraries (`libuv`, etc.) and register them during initialization. The engine interacts only through standardized interfaces, keeping implementation details isolated.

**Core Changes**:
- New async `API` files integrated into the engine
- Async-aware garbage collection improvements
- Network function wrappers for async delegation
- Registration interface for extension backends
- and more...

## Backward Compatibility
Full backward compatibility is maintained:
- `API` is disabled by default — no behavior changes without explicit backend registration
- No new special functions are being added to PHP 
- Existing extensions remain unaffected unless they opt-in to async functionality
- `CancellationException` is a new root exception, not extending `Exception`, 
ensuring it does not interfere with existing code.

## API Evolution
The implementation details of the `True Async API` are not fixed upon the acceptance of this `RFC` 
and are governed by a separate policy.

The `True Async API`, as a **binary interface**, may be changed in accordance with PHP's release
cycles until this status is explicitly revised by a separate policy.

Once the `True Async API` receives a fixed binary version, 
subsequent changes will be governed by a separate document.

## References
- Zend Async API implementation files: https://github.com/true-async/php-src/tree/true-async-api
- General changes to PHP functions + Async API: https://github.com/true-async/php-src/tree/true-async
- An extension that implements the API: https://github.com/true-async/php-async
- Additional documentation: https://github.com/true-async/php-src/blob/true-async-api/docs/source/true_async_api/coroutines.rst

