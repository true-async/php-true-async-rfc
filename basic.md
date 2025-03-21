# PHP True Async

* Version: 0.9
* Date: 2025-03-01
* Author: Edmond [HT], edmondifthen@proton.me
* Status: Draft
* First Published at: http://wiki.php.net/rfc/true_async

## Proposal

### Scheduler and Reactor

The **Scheduler** and **Reactor** components should be described in a separate **RFC**, 
which should focus on the low-level implementation in **C** and define **API** contracts for PHP extensions. 
These components should be developed as extensions that may be included 
in the PHP standard library but can also be replaced with custom implementations.

The **Scheduler** is a component responsible for managing the execution order of coroutines.  

> ⚠️ **Warning:** Users should not make assumptions about the execution order of coroutines unless 
> this is a specific goal of a particular **Scheduler** implementation.

The **Reactor** is a component that implements the **Event Loop**. 
It may be exposed as a separate API in **PHP-land**, 
but its behavior is not defined within this **RFC**.

> ⚠️ **Notice:** The logic of **Scheduler** and **Reactor** **MUST NOT** contradict the logic of this RFC!
> These components **MUST** implement the contracts defined in this document. 
> The **Scheduler** and **Reactor** **MAY** extend the behavior of this **RFC** by providing additional functionalities.

### Limitations

This **RFC** does not implement "colored functions" 
(see: https://journal.stuffwithstuff.com/2015/02/01/what-color-is-your-function/). 
Instead, it provides **transparent concurrency**, allowing **any function** to be asynchronous.

This **RFC** does not contradict a potential multitasking implementation 
where possible but does not assume its existence.

This **RFC** assumes the ability to create coroutines in other **Threads** using the **Scheduler API** 
or separate extensions but does not describe this capability.

This **RFC** also assumes functionality expansion using **SharedMemory**, 
specifically designed shared memory objects, through a separate API that is not part of this **RFC**.

This *RFC* is a core part of the **PHP TRUE ASYNC** contracts 
but assumes the development and approval of the following RFCs:

- **True Async API** – a low-level API for PHP extensions
- **Scheduler/Reactor API** – additional API for managing coroutines and the event loop
- **Primitives:** `Channel`, `Future`, `Iterator`, `Interval`
- **Inter-thread communication / Shared Memory objects**

### Namespace

All functions, classes, and constants defined in this **RFC** are located in the `Async` namespace.
Extensions for **Scheduler/Reactor** are allowed to extend this namespace with functions and classes, 
provided that they are directly related to concurrency functionality.

### Coroutine

> A `Coroutine` is an `execution container`, transparent to the code, 
> that can be suspended on demand and resumed at any time.

Any function can be executed as a coroutine without any changes to the code.

To start a coroutine, the `spawn` expression is used, 
resulting in an instance of the `Async\Coroutine` class.

A coroutine can stop itself bypassing control to the `Scheduler`. 
However, it cannot be stopped externally.

A suspended coroutine can be resumed at any time.

A coroutine can be resumed with an **exception**, in which case an exception 
will be thrown from the suspension point.

```php
function example(string $name): void {
    echo "Hello, $name!";
}

spawn example('World');

// We can run as coroutine any valid function.
spawn file_get_contents('file1.txt');
spawn sleep(1);
spawn strlen('Hello, World!');
```

### Spawn

The `spawn` construct is available in two variations:
* `spawn function_call` - creates a coroutine from a callable expression
* `spawn closure` - creates a coroutine and defines a closure

```php
// Executing a known function
spawn [with <scope>] <function_call>;

// Closure form
spawn [with <scope>] function() [use(<parameters>)][: <returnType>] {
    <codeBlock>
};
```

*where:* 

`function_call` - a valid function call expression:

- call a standard PHP function

```php

spawn file_get_contents('file1.txt');
```

- call a user-defined function

```php
function example(string $name): void {
    echo "Hello, $name!";
}

spawn example('World');
```

- call a static method

```php
spawn Mailer::send($message);
```

- call a method of an object

```php
$object = new Mailer();
spawn $object->send($message);
```

- self, static or parent keyword:

```php
spawn self::send($message);
spawn static::send($message);
spawn parent::send($message);
```

- call `$class` method

```php
$className = 'Mailer';
spawn $className::send($message);
```

- expression

```php
// Use result of foo()
spawn (foo())();
// Use foo as a closure
spawn (foo(...))();
// Use ternary operator
spawn ($option ? foo() : bar())();
// Scary example
spawn (((foo())))();
```

- call array dereference

```php
$array = [fn() => sleep(1)];
spawn $array[0]();
```

- new dereference

```php
class Test {
    public function wait(): void {
        sleep(1);
    }
}

spawn new Test->wait();
```

- call dereferenceable scalar:

```php
spawn "sleep"(5);
```

- call short closure

```php
spawn (fn() => sleep(1))();
```

#### Spawn closure syntax

Allows creating a coroutine from a closure directly when using `spawn`:

```php
spawn [with <scope>] <inline_function>;
```

where `inline_function`:

- a full closure definition.

```php
$name = 'World';

spawn function() use($name): string {
    return "Hello, $name!";
};
```

#### In scope expression

The `in` keyword allows specifying the scope in which the coroutine.

```php
$scope = new Async\Scope();

$coroutine = spawn in $scope function:string {
    return "Hello, World!";
};

function test(): string {
    return "Hello, World!";
}

spawn in $scope test();
```

The `scope` expression can be:
- A variable

```php
spawn in $scope function:void {
    echo "Hello, World!";
};
```

- The result of a method or function call

```php
spawn in $this->scope $this->method();
spawn in $this->getScope() $this->method();
```
> **Warning:** The `spawn` function does not allow passing reference data as parameters. 
> This limitation can be overcome using the `spawn` keyword.

### Suspension

A coroutine can suspend itself at any time using the `suspend` keyword.:

```php
function example(string $name): void {
    echo "Hello, $name!";
    suspend;
    echo "Goodbye, $name!";
}

spawn example('World');
spawn example('Universe');
```

Expected output:

```
Hello, World!
Hello, Universe!
Goodbye, World!
Goodbye, Universe!
```

**Basic syntax:**

```php
    suspend;
```

**Wrong use:**

```php
    suspend();
    suspend + $x;
    my_function(suspend);
```

The `suspend` keyword can be used only for the current coroutine.

The `suspend` keyword has no parameters and does not return any values, 
unlike the `yield` keyword.

The `suspend` keyword can be used in any function including from the **main execution flow**:

```php
function example(string $name): void {
    echo "Hello, $name!";
    suspend;
    echo "Goodbye, $name!";
}

$coroutine = spawn example('World');

// suspend the main flow
suspend;

echo "Back to the main flow";

```

Expected output:

```
Hello, World!
Back to the main flow
Goodbye, World!
```

The suspend keyword can be a throw point 
if someone resumes the coroutine externally with an exception.

```php
function example(string $name): void {
    echo "Hello, $name!";
    
    try {
        suspend;
    } catch (Exception $e) {
        echo "Caught exception: ", $e->getMessage();
    }
        
    echo "Goodbye, $name!";
}

$coroutine = spawn example('World');

// pass control to the coroutine
suspend;

$couroutine->cancel();
```

Expected output:

```
Hello, World!
Caught exception: cancelled at ...
Goodbye, World!
```

### Input/Output Operations And Implicit Suspension

I/O operations invoked within the coroutine's context transfer control implicitly:

```php
spawn function:void {
    echo "Start reading file1.txt\n";
    file_get_contents('file1.txt');
    echo "End reading file1.txt\n";
}
spawn function:void {
    echo "Start reading file2.txt\n";
    file_get_contents('file2.txt');
    echo "End readingfile2.txt\n";  
}

echo "Main flow";
```

Expected output:

```
Start reading file1.txt
Start reading file2.txt
Main flow
End reading file1.txt
End reading file2.txt
```

Inside each coroutine, 
there is an illusion that all actions are executed sequentially, 
while in reality, operations occur asynchronously.

### Await

The `await` keyword is used to wait for the completion of another coroutine:

```php
spawn function() {
    echo "Start reading file1.txt\n";
    
    $result = await spawn function():string {
        $result = file_get_contents('file1.txt');
        
        if($result === false) {
            throw new Exception("Error reading file1.txt");
        }
        
        return $result;
    };

    echo "End reading file1.txt\n";
};

spawn function() {
    echo "Sleep\n";
    sleep(1);    
};
```

`await` suspends the execution of the current coroutine until
the awaited one returns a final result or completes with an exception.

```php
function test(): void {
    throw new Exception("Error");
}

try {
    await test();
} catch (Exception $e) {
    echo "Caught exception: ", $e->getMessage();
}
```

**Await basic syntax:**

```php
    [<resultExp> = ] await <awaitExp> [until <cancellationExp>];
```

**where:**

- `resultExp` - An expression that will receive the result of the awaited operation.
- `awaitExp` - An expression whose result must be an object with the `Async\Awaitable` interface. 
- `cancellationExp` - An expression that limits the waiting time. 
Must be an object with the `Async\Awaitable` interface.

**Await expression:**

- A variable of the `Awaitable` interface

```php
    $readFileJob = spawn file_get_contents('file1.txt');
    
    $result = await $readFileJob;
```

- A function that returns an `Awaitable` object

```php
    function getContentsJobStarter(string $fileName): \Async\Coroutine {
        return spawn file_get_contents($fileName);
    }

    $result = await getContentsJobStarter('file1.txt');
```

- A new coroutine

```php
    $result = await spawn file_get_contents('file1.txt');
```

- A new Awaitable object

```php
    $result = await new Async\Future();
```

- A static method

```php
    $result = await SomeClass::create();
```

- A method of an object

```php
    $service = new Mailer();
    $result = await $service->sendMail("test@mail.com", "Hello!");
```

- A method of a class

```php
    $serviceClass = 'Mailer';
    $result = await $serviceClass::sendAll();
```

- A valid expression

```php
    $result = await ($bool ? foo() : bar());
```

**CancellationExp**:

- A variable of the `Async\Awaitable` interface

```php
    $cancellation = new Future();
    $result = await $coroutine until $cancellation;
```

- A function that returns an `Awaitable` object

```php
    function getCancellation(): \Async\Awaitable {
        return new Future();
    }

    $result = await $coroutine until getCancellation();
```

- A new coroutine

```php
    $result = await $coroutine until spawn sleep(5);
```

### Edge Behavior

The use of `spawn`/`await`/`suspend` is allowed in almost any part of a PHP program.
This is possible because the PHP script entry point forms the **main execution thread**, 
which is also considered a coroutine.  

As a result, keyword like `suspend` and `currentCoroutine()` will behave the same way as in other cases.

If only **one coroutine** exists in the system, calling `suspend` will immediately return control.

The `register_shutdown_function` handler operates in synchronous mode, 
after asynchronous handlers have already been destroyed. 
Therefore, the `register_shutdown_function` code should not use the concurrency API.
The `suspend` keyword will have no effect, and the `spawn` operation will not be executed at all.

### Awaitable interface

The `Awaitable` interface is a contract that allows objects to be used in the `await` expression.

The interface does not have any methods on the user-land side 
and is intended for objects implemented as PHP extensions, such as:

- `Future`
- `Cancellation`

The following classes from this **RFC** also implement this interface:

- `Coroutine`
- `Scope`

### Scope and structured concurrency

**Structured concurrency** allows organizing coroutines into 
a group or hierarchy to manage their lifetime or exception handling.

The `Async\Scope` class enables grouping coroutines together, 
allowing to create a hierarchy of groups (i.e., a hierarchy of `Async\Scope`).

```php
$scope = new Async\Scope();

spawn in $scope function() {

    spawn function() {
        echo "Task 1-1\n";
    };

    echo "Task 1\n";
};

spawn in $scope function() {
    echo "Task 2\n";
};

await $scope;
```

**Expected output:**

```
Task 1
Task 2
Task 1-1
```

In this example, **all three child coroutines** belong to the same `$scope` 
and can be awaited using the `await` keyword or canceled using the `cancel()` method.

Note that the coroutine that prints the text `"Task 1-1"` also belongs to `$scope`, 
meaning it is at the same level as the `"Task 1"` coroutine.

> By default, coroutines that are called from other coroutines 
> inherit the same `Scope` and are at the same level in the hierarchy.

The `Scope` primitive can be used in an `await` expression, 
in which case the code will pause until all child tasks are completed.

#### Coroutine Scope waiting

The `await` keyword can be used with a `Scope` object:

```php
function task(): void 
{
    $scope = new Scope();
    
    spawn in $scope function() {
        spawn function() {
            sleep(1);
            echo "Task 1-1\n";
        };
    
        sleep(1);
        echo "Task 1-1\n";
    };
    
    $scope->spawn(function() {
        sleep(2);
        echo "Task 2\n";
    });
    
    // wait for all child coroutines
    await $scope;
}

spawn task();
```

The expression `$await $scope` suspends the current coroutine 
until all coroutines in the `$scope` group have completed or an exception occurs.

#### Scope cancellation

The `cancel` method cancels all child coroutines:

```php
$scope = new Scope();
$scope->spawn(function() {
    sleep(1);
    echo "Task 1\n";
});

$scope->spawn(function() {
    sleep(2);
    echo "Task 2\n";
});

$scope->cancel();
```

#### Scope Hierarchy

With `Scope`, you can build a hierarchy of coroutine groups to manage the lifetime of all child groups. 
Each `Scope` in the hierarchy can be controlled separately, 
participate independently in an `await` expression, 
and be canceled individually. 

However, canceling a parent `Scope` results in the cancellation of all child `Scopes` 
and all coroutines within those Scopes.

```php
use Async\Scope;
use Async\CancellationException;

function connectionChecker($socket, callable $cancelToken): void
{
    while (true) {
        if(feof($socket)) {
            $cancelToken();
            return;
        }                               
        
        sleep(1);
    }
}

function connectionHandler($socket): void
{
    $scope = Scope::inherit();

    spawn in $scope function() use($socket, $scope) {

        $cancelToken = fn() => $scope->cancel(new CancellationException("The connection was closed by user"));

        // A separate coroutine checks that the socket is still active.    
        spawn connectionChecker($socket, $cancelToken);
    
        try {
            sendResponse($socket, dispatchRequest(parseRequest($socket)));
        } catch (Throwable $exception) {
            fwrite($socket, "HTTP/1.1 500 Internal Server Error\r\n\r\n");
        } finally {
            fclose($socket);
            $scope->dispose();
        }
    };
}

function socketListen(): void
{
    $scope = new Scope();    

    $scope->spawn(function() {
        while ($socket = stream_socket_accept($serverSocket, 0)) {            
            connectionHandler($socket);
        }
    });

    await $scope;
}
```
Let's examine how this example works.

1. `socketListen` creates a new Scope for coroutines that will handle all connections.
2. Each new connection is processed using `connectionHandler()` in a separate Scope, which is inherited from the main one.
3. `connectionHandler` creates another coroutine, `connectionChecker()`, to monitor the connection's activity. 
As soon as the client disconnects, `connectionChecker` will cancel all coroutines related to the request.
4. If the main Scope is closed, all coroutines handling requests will also be canceled.

```
GLOBAL <- globalScope
│
├── socketListen (Scope) <- rootScope
│   │
│   ├── connectionHandler (Scope) <- request scope1
│   │   └── connectionChecker (Coroutine)
│   │
│   ├── connectionHandler (Scope) <- request scope2
│   │   └── connectionChecker (Coroutine)
│   │
```

### Context

#### Motivation

Libraries and frameworks often use variables that are shared within a request to store common data. 
These variables are not **Global** in the general sense, 
but they essentially reflect a shared state related to the request or execution scope.

For example, the `TokenStorage` class 
(https://github.com/symfony/symfony/blob/7.3/src/Symfony/Component/Security/Core/Authentication/Token/Storage/TokenStorage.php) 
from `Symfony` allows retrieving the user token multiple times, as it is stored in a variable.

Or `/src/Illuminate/Auth/TokenGuard.php` from `Laravel`:

```php
    /**
     * Get the currently authenticated user.
     */
    public function user()
    {
        // If we've already retrieved the user for the current request we can just
        // return it back immediately. We do not want to fetch the user data on
        // every call to this method because that would be tremendously slow.
        if (! is_null($this->user)) {
            return $this->user; // <-- Shared state
        }

        $user = null;

        $token = $this->getTokenForRequest();
        
        // some code skipped

        return $this->user = $user;
    }
```

This code assumes that a single `process`/`thread` always handles only one request at a time. 
However, in a concurrent web server environment, 
shared states can no longer be used because the execution context may switch unexpectedly.

You can use `Coroutine ID` and `Map` to associate a unique coroutine ID with specific data. 
However, in this case, you must ensure that the data is properly released 
when the coroutine ceases to exist.

In addition to storing request-specific data, 
concurrent code must also ensure the proper handling of input/output descriptors. 
For example, when implementing a protocol, data must be sent in a specific sequence. 
If a socket is used by two coroutines simultaneously for reading/writing, 
the order of operations may be disrupted.

Another example is database transactions. 
Code that starts a transaction cannot release the database connection socket until 
the transaction is completed.

The `Async\Context` class is designed to help solve these issues.

#### Context API

The `Async\Context` class defines three groups of methods:
* Methods for retrieving values from the Map, considering parent contexts
* Methods for retrieving values only from the current context
* Methods for modifying or removing keys in the current context

| Method                                                                | Description                                             |
|-----------------------------------------------------------------------|---------------------------------------------------------|
| `find(string\|object $key): mixed`                                    | Find a value by key in the current or parent Context.   |
| `get(string\|object $key): mixed`                                     | Get a value by key in the current Context.              |
| `has(string\|object $key): bool`                                      | Check if a key exists in the current Context.           |
| `findLocal(string\|object $key): mixed`                               | Find a value by key only in the local Context.          |
| `getLocal(string\|object $key): mixed`                                | Get a value by key only in the local Context.           |
| `hasLocal(string\|object $key): bool`                                 | Check if a key exists in the local Context.             |
| `set(string\|object $key, mixed $value, bool $replace = false): self` | Set a value by key in the Context.                      |
| `unset(string\|object $key): self`                                    | Delete a value by key from the Context.                 |


**Context Slots** are an efficient mechanism for managing memory 
associated with `Scope` or coroutine lifetimes.  
Once all coroutines owning the Scope complete, 
or the Scope itself is terminated, all data in the slots will be released.

This helps the programmer associate data with coroutines without writing explicit cleanup code.

To ensure data encapsulation between different components, 
**Coroutine Scope Slots** provide the ability to associate data using **key objects**.  
An object instance is unique across the entire application, 
so code that does not have access to the object cannot read the data associated with it.

This pattern is used in many programming languages and is represented in JavaScript by a special class, **Symbol**.

```php
$key = 'pdo connection';

if(currentContext()->has($key)) {
    $pdo = currentContext()->get($key);
} else {
    $pdo = new PDO('sqlite::memory:');
    currentContext()->set($key, new PDO('sqlite::memory:'));
}
```

**Coroutine Scope Slots** can automatically dereference **WeakReference**.  
If you assign a **WeakReference** to a slot and then call `find()`, 
you will receive the original object or `NULL`.

```php
function task(): void 
{
    // Should return the original object
    $pdo = currentContext()->find('pdo');
}

$pdo = new PDO('sqlite::memory:');
currentContext()->set('pdo', new WeakReference($pdo));

spawn task();
```

#### Context inheritance

The context belongs to the `Scope` and is created along with it.  
If a `Scope` is inherited from a parent, the new context also inherits the parent.  
Thus, the hierarchy of Scope objects forms exactly the same hierarchy of contexts.

```php

use Async\Scope;
use function \Async\currentContext;
use function \Async\rootContext;

function handleRequest($socket): void
{
    echo currentContext()->get('request_id')."\n"; // <-- From request context
    echo currentContext()->get('server_id')."\n"; // <-- From server context
    echo rootContext()->get('request_id')."\n"; // <-- Should be NULL
}

function startRequestHandler($socket): void
{
    $requestScope = Scope::inherit(); // <-- Inherit server context
    $requestScope->context->set('request_id', uniqid()); // <-- Override server context slot
    
    // Handle request in separate coroutine and scope
    spawn in $requestScope handleRequest($socket);
}

function startServer(): void
{
    $serverScope = new Scope();
    $serverScope->context->set('server_id', uniqid());
    $serverScope->context->set('request_id', null);
    
    while (true) {
        $socket = stream_socket_accept($serverSocket, 0);
        startRequestHandler($socket);
    }    
}
```

The special functions `Async\currentContext()` and `Async\rootContext()` help
quickly access the current context from any function.

`Async\rootContext()` returns the context at the very root of the hierarchy,
if it exists, or the global application context if it does not.

#### Coroutine local context

While a `Scope` can serve as a shared context in the coroutine hierarchy, 
a coroutine's **local context** is a personal data store strictly tied to the coroutine's lifetime. 
The local context allows associating data slots that are automatically freed once the coroutine completes.

The local coroutine context is accessible via the `Async\coroutineContext()` function, 
which returns an `Async\Context` object. 
The `Async\Context` class provides the same methods for working with slots as the `Scope` class:

```php
function task(): void 
{
    coroutineContext()->set('data', 'This local data');
    
    spawn function() {
         // No data will be found
         echo coroutineContext()->find('data')."\n";
    };
}
```

Using a coroutine's local context can be useful for associating objects with a coroutine that **MUST** be unique to each coroutine.  
For example, a database connection.

```php
<?php

namespace Async;

use PDO;
use RuntimeException;

class ConnectionProxy
{
    private PDO $connection;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }
    
    public function __destruct()
    {
        getGlobalConnectionPool()->releaseConnection($this->connection);
    }
}

class ConnectionPool
{
    private array $pool = [];
    private int $maxConnections = 10;

    public function getConnection(): ConnectionProxy
    {
        if (!empty($this->pool)) {
            return new ConnectionProxy(array_pop($this->pool));
        }

        if (count($this->pool) < $this->maxConnections) {
            return new ConnectionProxy(PDO("mysql:host=localhost;dbname=test", "user", "password"));
        }

        throw new RuntimeException("No available database connections.");
    }

    public function releaseConnection(PDO $connection): void
    {
        $this->pool[] = $connection;
    }
}

function getDb(): ConnectionProxy
{
    static $key = new Key('db_connection');
    
    $context = Async\coroutineContext();

    if ($context->has($key)) {
        return $context->get($key);
    }

    $pool = getGlobalConnectionPool();
    $connection = $pool->getConnection();

    $context->set($key, $connection);

    return $connection;
}

function getGlobalConnectionPool(): ConnectionPool
{
    static $pool = null;
    if ($pool === null) {
        $pool = new ConnectionPool();
    }
    return $pool;
}

function printUser(int $id): void 
{
    $db = getDb();
    $stmt = $db->query("SELECT * FROM users WHERE id = $id");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($users);
}

spawn printUser(1);
spawn printUser(2);
```

This code relies on the fact that an instance of the `ConnectionProxy` 
class will be destroyed as soon as the coroutine completes.  
The destructor will be called, and the connection will automatically return to the pool.

### Error Handling

An uncaught exception in a coroutine follows this flow:

1. If the coroutine is awaited using the `await` keyword, 
the exception is propagated to the awaiting points. 
If multiple points are awaiting, each will receive the same exception
(**Each await point will receive the exact same exception object, not cloned**).
2. The exception is passed to the `Scope`.
3. If the `Scope` has an exception handler defined, it will be invoked.
4. If the `Scope` does not have an exception handler, the `cancel()` method is called, 
canceling all coroutines in this scope from top to bottom in the hierarchy, including all child scopes.
5. If the `Scope` has responsibility points, i.e., the construction `await $scope`, 
all responsibility points receive the exception.
6. Otherwise, the exception is passed to the parent scope if it is defined.
7. If there is no parent scope, the exception falls into `globalScope`, 
where the same rules apply as for a regular scope.

```puml
@startuml
start
:Unhandled Exception Occurred;
if (Await keyword is used?) then (Yes)
    :Exception is propagated to await points;
else (No)
    while (Scope exists?)
        :Exception is passed to Scope;
        if (Scope has an exception handler?) then (Yes)
            :Invoke exception handler;
            stop
        else (No)
            :Scope::cancel();
        endif
        if (Scope has responsibility points?) then (Yes)
            :All responsibility points receive the exception;
            stop
        endif
        if (Parent Scope exists?) then (Yes)
            :Pass exception to Parent Scope;
        else (No)
            :Pass exception to globalScope;
            break
        endif
    endwhile
endif
stop
@enduml
```

**Example:**

```php
use Async\Scope;

$scope = new Scope();
$scope->spawn(function() {
    throw new Exception("Task 1");
});

$exception1 = null;
$exception2 = null;

$scope2 = new Scope();

$scope2->spawn(function() use($scope, &$exception1) {
    try {
        await $scope;
    } catch (Exception $e) {
        $exception1 = $e;
        echo "Caught exception1: {$e->getMessage()}\n";
    }
});

$scope2->spawn(function use($scope, &$exception2) {
    try {
        await $scope;
    } catch (Exception $e) {
        $exception2 = $e;
        echo "Caught exception1: {$e->getMessage()}\n";
    }
});

await $scope2;

echo $exception1 === $exception2 ? "The same exception\n" : "Different exceptions\n";
```


If an exception reaches `globalScope` and is not handled in any way, 
it triggers **Graceful Shutdown Mode**, which will terminate the entire application.

The `BoundedScope` class allows defining an exception handler that can prevent exception propagation.

For this purpose, two methods are used:
- **`setExceptionHandler`** – triggers for any exceptions thrown within this **Scope**.
- **`setChildScopeExceptionHandler`** – triggers for exceptions from **child Scopes**.

**Example:**

```php
$scope = new BoundedScope();
$scope->setExceptionHandler(function (Async\Scope $scope, Async\Coroutine $coroutine, Throwable $e) {
    echo "Caught exception: {$e->getMessage()}\n in coroutine: {$coroutine->getSpawnLocation()}\n";
});

$scope->spawn(function() {
    throw new Exception("Task 1");
});

Async\await($scope);
```

Using these handlers, 
you can implement the **Supervisor** pattern, i.e., 
a **Scope** that will not be canceled when an exception occurs in coroutines.

The **`setChildScopeExceptionHandler`** method allows handling exceptions only from **child Scopes**, 
which can be useful for implementing an algorithm where the **main Scope** runs core tasks, 
while **child Scopes** handle additional ones.

For example:

```php
final class Service
{
    private Scope $scope;
    
    public function __construct()
    {
        $this->scope = new Scope();
        
        $this->scope->setChildScopeExceptionHandler(
        static function (Scope $scope, Coroutine $coroutine, \Throwable $exception): void {
            echo "Occurred an exception: {$exception->getMessage()} in Coroutine {$coroutine->getSpawnLocation()}\n";
        });
    }
    
    public function start(): void
    {
        $this->scope->spawn($this->run(...));
    }
    
    public function stop(): void 
    {
        $this->scope->cancel();
    }
    
    private function run(): void
    {
        while (($socket = $this->service->receive()) !== null) {
            Scope::inherit($this->scope)->spawn($this->handleRequest(...), $socket);
        }
    }
}
```

`$this->scope` listens for new connections on the server socket.  
Canceling `$this->scope` means shutting down the entire service.

Each new connection is handled in a separate **Scope**, which is inherited from `$this->scope`.  
If an exception occurs in a coroutine created within a **child Scope**, 
it will be passed to the `setChildScopeExceptionHandler` handler and will not affect 
the operation of the service as a whole.

```puml
@startuml
actor Client
participant "Service (Main Scope)" as Service
participant "Request Handler (Child Scope)" as Handler

Client -> Service : New connection request
Service -> Handler : Create child scope and spawn coroutine

loop For each request
    Client -> Handler : Send request
    Handler -> Client : Send response
end

alt Exception in child scope
    Handler -> Service : Exception propagated to setChildScopeExceptionHandler
    note right: Exception is logged, service continues running
end

alt Main scope cancelled
    Service -> Handler : Cancel all child scopes
    Handler -> Client : Disconnect
end

@enduml
```

#### Responsibility points

A **responsibility point** is code that explicitly waits for the completion of a coroutine or a `Scope`:

```php
$scope = new Scope();

$scope->spawn(function() {
  throw new Exception("Task 1");        
});

try {
    await $scope;
} catch (\Throwable $e) {
     echo "Caught exception: {$e->getMessage()}\n";
}      
```

A **responsibility point** has a chance to receive 
not only the result of the coroutine execution but also an unhandled exception.

#### Exception Handling

The `BoundedScope` class provides a method for handling exceptions:

```php
$scope = new BoundedScope();

$scope->spawn(function() {
  throw new Exception("Task 1");        
});

$scope->setExceptionHandler(function (Exception $e) {
    echo "Caught exception: {$e->getMessage()}\n";
});

await $scope;
```

An exception handler has the right to suppress the exception.  
However, if the exception handler throws another exception, 
the exception propagation algorithm will continue.

#### onCompletion

The `onCompletion` method allows defining a callback function that will be invoked when a coroutine or scope completes.  
This method can be considered a direct analog of `defer` in Go.

```php
$scope = new Scope();

$scope->spawn(function() {
  throw new Exception("Task 1");        
});

$scope->onCompletion(function () {
    echo "Task 1 completed\n";
});

await $scope;
```

Or for coroutines:

```php
function task(): void 
{
    throw new Exception("Task 1");
}

$coroutine = spawn task();

$coroutine->onCompletion(function () {
    echo "Task completed\n";
});

```

The `onCompletion` semantics are most commonly used to release resources, 
serving as a shorter alternative to `try-finally` blocks:

```php
function task(): void 
{
    $file = fopen('file.txt', 'r');    
    onCompletion(fn() => fclose($file));
    
    throw new Exception("Task 1");
}

spawn task();
```

### Cancellation

The cancellation operation is available for coroutines and scopes 
using the `cancel()` method:

```php
function task(): void {}

$coroutine = spawn task();

// cancel the coroutine
$coroutine->cancel(new Async\CancellationException('Task was cancelled'));
```

The cancellation operation is implemented as follows:

1. If a coroutine has not started, it will never start.
2. If a coroutine is suspended, its execution will resume with an exception.
3. If a coroutine has already completed, nothing happens.

The `CancellationException`, if unhandled within a coroutine, is automatically suppressed after the coroutine completes.

> ⚠️ **Warning:** You should not attempt to suppress `CancellationException` exception, 
> as it may cause application malfunctions.

```php
$scope = new Scope();

$scope->spawn(function() {
    sleep(1);        
    echo "Task 1\n";
});

$scope->cancel(new Async\CancellationException('Task was cancelled'));
```

Canceling a `Scope` triggers the cancellation of all coroutines 
within that `Scope` and all child `Scopes` in hierarchical order.

>
> **Note:** `CancellationException` can be extended by the user 
> to add metadata that can be used for debugging purposes.
> 

#### CancellationException propagation

The `CancellationException` affects PHP standard library functions differently. 
If it is thrown inside one of these functions that previously did not throw exceptions, 
the PHP function will terminate with an error.

In other words, the `cancel()` mechanism does not alter the existing function contract. 
PHP standard library functions behave as if the operation had failed.

Additionally, the `CancellationException` will not appear in `get_last_error()`, 
but it may trigger an `E_WARNING` to maintain compatibility with expected behavior 
for functions like `fwrite` (if such behavior is specified in the documentation).

#### exit and die keywords

The `exit`/`die` keywords called within a coroutine result in the immediate termination of the application.  
Unlike the `cancel()` operation, they do not allow for proper resource cleanup.

### Graceful Shutdown

When an **unhandled exception** occurs in a **Coroutine**
the **Graceful Shutdown** mode is initiated. 
Its goal is to safely terminate the application.

**Graceful Shutdown** cancels all coroutines in `globalScope`, 
then continues execution without restrictions, allowing the application to shut down naturally.  
**Graceful Shutdown** does not prevent the creation of new coroutines or close connection descriptors. 
However, if another unhandled exception is thrown during the **Graceful Shutdown** process, 
the second phase is triggered.

**Second Phase of Graceful Shutdown**
- All **Event Loop descriptors** are closed.
- All **timers** are destroyed.
- Any remaining coroutines that were not yet canceled will be **forcibly canceled**.

The further shutdown logic may depend on the specific implementation of the **Scheduler** component, 
which can be an external system and is beyond the scope of this **RFC**.

The **Graceful Shutdown** mode can also be triggered using the function:

```php
Async\gracefulShutdown(\Throwable|null $throwable = null): void {}
```

from anywhere in the application.

### Deadlocks

A situation may arise where there are no active **Coroutines** in the execution queue 
and no active handlers in the event loop. 
This condition is called a **Deadlock**, and it represents a serious logical error.

When a **Deadlock** is detected, the application enters **Graceful Shutdown** mode 
and generates warnings containing information about which **Coroutines** are in a waiting state 
and the exact lines of code where they were suspended.

### Tools

The `Coroutine` class implements methods for inspecting the state of a coroutine.

| Method                                 | Description                                                                                                                                                                                            |
|----------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **`getSpawnFileAndLine():array`**      | Returns an array of two elements: the file name and the line number where the coroutine was spawned.                                                                                                   |
| **`getSpawnLocation():string`**        | Returns a string representation of the location where the coroutine was spawned, typically in the format `"file:line"`.                                                                                |
| **`getSuspendFileAndLine():array`**    | Returns an array of two elements: the file name and the line number where the coroutine was last suspended. If the coroutine has not been suspended, it may return `['',0]`.                           |
| **`getSuspendLocation():string`**      | Returns a string representation of the location where the coroutine was last suspended, typically in the format `"file:line"`. If the coroutine has not been suspended, it may return an empty string. |
| **`isSuspended():bool`**               | Returns `true` if the coroutine has been suspended                                                                                                                                                     |
| **`isCancelled():bool`**               | Returns `true` if the coroutine has been cancelled, otherwise `false`.                                                                                                                                 |
| **`getTrace():array`**                 | Returns the stack trace of the coroutine.                                                                                                                                                              |

The `Coroutine::getAwaitingInfo()` method returns an array with debugging information 
about what the coroutine is waiting for, if it is in a waiting state.

The format of this array depends on the implementation of the **Scheduler** and the **Reactor**.

The `Async\getCoroutines()` method returns an array of all coroutines in the application.

### Prototypes

* [Async functions](./examples/Async/Async.php)
* [Coroutine](./examples/Async/Coroutine.php)
* [Coroutine Context](./examples/Async/Context.php)
* [Coroutine Scope](./examples/Async/Scope.php)

## Backward Incompatible Changes

Simultaneous use of the **True Async API** and the **Fiber API** is not possible.

- If `new Fiber()` is called first, the `Async\spawn` function will fail with an error.
- If `Async\spawn` is called first, any attempt to create a **Fiber** will result in an error.

## Proposed PHP Version(s)

PHP 8.6/ PHP 9.0