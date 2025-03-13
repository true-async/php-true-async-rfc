# PHP True Async

* Version: 0.9
* Date: 2025-03-01
* Author: Edmond [HT], edmondifthen@proton.me
* Status: Draft
* First Published at: http://wiki.php.net/rfc/true_async

## Proposal

### Coroutine

A `Coroutine` is an `execution container`, transparent to the code, 
that can be suspended on demand and resumed at any time.

Any function can be executed as a coroutine without any changes to the code.

```php
function example(string $name): void {
    echo "Hello, $name!";
}

spawn(example(...), 'World');

// With special syntax

spawn example('World');

// Or the same with a closure

$name = 'World';

spawn function use($name): void {
    echo "Hello, $name!";
};

// We can run as coroutine any valid function.
spawn file_get_contents('file1.txt');
spawn sleep(1);
spawn strlen('Hello, World!');

```

The `spawn` function execute the `example` function in an asynchronous context.

```php

$coroutine = spawn(function(string $name): void {
    echo "Hello, $name!";
}, 'World');

```

The `spawn` function returns a `Coroutine` object 
that can be used to control the execution of the coroutine:

```php

$coroutine = spawn(function(string $name): void {
    echo "Hello, $name!";
}, 'World');

$coroutine->cancel();
```

### Suspension

A coroutine can suspend itself at any time using the `suspend` function:

```php
function example(string $name): void {
    echo "Hello, $name!";
    suspend();
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

The `suspend` function can be used only for the current coroutine.

The `suspend` function has no parameters and does not return any values, 
unlike the yield operator.

The `suspend` function can be used in any function and in any place
including from the main execution flow:

```php
function example(string $name): void {
    echo "Hello, $name!";
    suspend();
    echo "Goodbye, $name!";
}

$coroutine = spawn example('World');

// suspend the main flow
suspend();

echo "Back to the main flow";

```

Expected output:

```
Hello, World!
Back to the main flow
Goodbye, World!
```

The suspend operator can be a throw point 
if someone resumes the coroutine externally with an exception.

```php

function example(string $name): void {
    echo "Hello, $name!";
    
    try {
        suspend();
    } catch (Exception $e) {
        echo "Caught exception: ", $e->getMessage();
    }
        
    echo "Goodbye, $name!";
}

$coroutine = spawn example('World');

// pass control to the coroutine
suspend();

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

The `await` function is used to wait for the completion of another coroutine:

```php

spawn function {
    echo "Start reading file1.txt\n";
    
    $result = await spawn function:string {
        return file_get_contents('file1.txt');    
    };
            
    echo "End reading file1.txt\n";
}

spawn function {
    echo "Sleep\n";
    sleep(1);    
}
```

await suspends the execution of the current coroutine until 
the awaited one returns a final result or completes with an exception.

### Lifetime limitation

    The lifecycle of a coroutine is the time limit within which the coroutine is allowed to execute.

When using coroutines, three models of responsibility distribution regarding lifecycle arise:

* **No limitation**. Coroutines are not limited in their lifetime and run as long as needed.
* **Top-down limitation**: Parent coroutines limit the lifetime of their children
* **Bottom-up limitation**: Child coroutines extend the execution time of their parents

These models do not contradict each other and can be implemented in a language in combination. 
Typically, one of these models is used by default, while the other two require explicit specification.

For example, in Go, the unrestricted model applies. 
Coroutines are independent of each other, 
and if such a dependency needs to be established, the programmer must specify it explicitly.

In languages like `Kotlin` and `Swift`, 
the Bottom-up constraint is used by default and serves as a way to implement structured concurrency.

In other languages, the `Actor` model assumes that the parent's lifetime restricts the lifetime of child Actors.

| Aspect                     | No Limitation                                                                               | Bottom-up                                                                   | Top-down                                                          |
|----------------------------|---------------------------------------------------------------------------------------------|-----------------------------------------------------------------------------|-------------------------------------------------------------------|
| Execution Limit            | No lifetime restriction; coroutines run independently                                       | Parent coroutine waits until all child coroutines complete                  | Child coroutines cannot outlive their parent.                     |
| Resource Management        | Manual; programmer explicitly manages resources                                             | Automatic; resources held until child coroutines finish                     | Automatic; parent controls resources and cancellation             |
| Completion Guarantee       | No guarantee; programmer manually ensures coroutine completion                              | Guarantees completion of all child coroutines                               | Ensures that no child coroutine runs longer than the parent       |
| Nested Coroutines          | Independent; nested coroutines may run unnoticed                                            | Structured; nested coroutines explicitly affect parent's lifetime           | Hierarchical; children cannot outlive parents                     |
| Simplicity                 | Simple but requires careful manual synchronization                                          | Clear structure, prevents accidental misuse                                 | Easier to enforce controlled execution                            |
| **Risk of Resource Leaks** | Higher; leaks can include hanging coroutines and owned scopes; leaks may reach global scope | Lower*; structured concurrency reduces leaks, parent ensures proper cleanup | Low: parent always has control over lifetime                      |
| **Exception Propagation**  | Exceptions may escape to global scope and remain unhandled                                  | Exceptions propagate strictly along coroutine hierarchy                     | Exceptions are caught and handled within the parent scope         |
| **Deadlocks**              | Lower: since coroutines are independent                                                     | Higher: Accidental use of an incorrect external dependency                  | Lower: Parent dictates child lifecycle, reducing accidental locks |
| Explicitness               | Programmer explicitly defines synchronization                                               | Behavior inherently defined by model rules                                  | Cancellation is explicit; parent enforces lifecycle               |
| Semantic Complexity        | Minimal; fewer semantic constructs needed                                                   | May require additional semantic constructs for convenience                  | Slightly higher; requires explicit management of cancellations    |
| Use cases                  |                                                                                             | Useful for ensuring all subtasks complete before proceeding                 | Useful for managing complex task hierarchies                      |

Let's take a closer look at the differences between the models, their advantages and disadvantages, and use cases.

#### No Limitation

The main drawback of the **No Limitation** model is the need for manual resource management. 
However, this can also be considered an advantage, as it provides maximum control.

#### Bottom-up

The **Bottom-up** model leads to more ambiguous consequences. 
Intuitively, it seems that this model should prevent resource leaks, 
as it keeps parent coroutines alive, thereby preserving the code that awaits them. 
As a result, the code executed after waiting is more likely to clean up resources correctly.

One could say that the **Bottom-up model** makes concurrent programming less concurrent. 
However, this advantage easily turns into a disadvantage because **Bottom-up** potentially retains more
resources in memory than any other model. 
If a programmer spawns five tasks within a hierarchy, all five tasks will be retained in memory 
until their shared execution flow completes. But what if that never happens?

The situation becomes even worse if the **Bottom-up** strategy is always used by default. 
When different programmers write coroutine hierarchy code across various projects and libraries, 
it can lead to complex, hard-to-detect bugs. 
This happens because different teams may make different assumptions, 
unaware of how exactly their code might be used at higher or lower levels of the hierarchy. 
All of this makes the Bottom-up model more dangerous than useful in team-based development.

However, the **Bottom-up** model has an advantage in another scenario — 
when a parent coroutine spawns child coroutines whose completion is critically important.

Let's examine this case:

```php
function task(): void 
{
    spawn importantTask();
    
    spawn function {
        sleep(1);
        echo "Hello, PHP!";
    }; 
}

spawn task();
```

In this example, the `importantTask` coroutine is critical for the parent coroutine. If the parent coroutine completes 
before the child coroutine, it may abandon the execution of importantTask, leading to data loss.

This case is a positive aspect of the *Bottom-up* model and a negative aspect of the *Top-down* strategy.

However, this seems to be the only significant drawback of the **Top-down** strategy.

#### Top-down

The **Top-down** model limits the lifetime of child coroutines to the lifetime of the parent.  
It implements structured concurrency but, unlike the **Bottom-up** model,  
aims to release resources as quickly as possible.

```php
function task(): void 
{
    spawn(function {
        sleep(10);
        echo "Hello, World!";
    });

    await spawn(function {
        sleep(1);
        echo "Hello, PHP!";
    });    
}

spawn task();
```

In the example above, the lifetime of the parent coroutine explicitly matches 
the second child coroutine since the parent explicitly waits for its completion. 
The line of code echo `"Hello, World!"` will never execute because 
the parent will be terminated earlier.

To wait for direct descendants, the `await children` operator can be used:

```php
function task(): void 
{
    spawn(function {
        sleep(10);
        echo "Hello, World!";
    });

    spawn(function {
        sleep(1);
        echo "Hello, PHP!";
    });
    
    // parent lifetime is limited by direct descendants
    await children;
}

spawn task();
```

In this case, the **parent coroutine** will wait for the completion 
of the first and second child coroutines but not their descendants. 
This differs from the logic of the **Bottom-up** model, 
where all nested descendants affected the parent's lifetime.

Example:

```php
function task(): void 
{
    spawn function {
        spawn function {
            sleep(10); // <-- not affected to task lifetime
            
            echo "This code will not be executed";
        };
        
        sleep(2); // <-- affected to task lifetime
        echo "Hello, World!";
    };

    spawn function {
        sleep(1);
        echo "Hello, PHP!";
    };
    
    // parent lifetime is limited by direct descendants
    await children;
}

spawn task();
```

You can use the `CoroutineScope` primitive to write similar code:

```php
function task(): void 
{
    $coroutineScope = new CoroutineScope();
    
    $coroutineScope->spawn(function {    
        sleep(10);
        echo "Hello, World!";
    });

    $coroutineScope->spawn(function {
        sleep(1);
        echo "Hello, PHP!";
    });
    
    await $coroutineScope;
}
```

The **Top-down** model does not provide a way to wait for all descendants without depth restrictions.  
This can be considered an advantage of the model, as it forces the programmer to explicitly define  
the expectations of parent coroutines.

A drawback of the **Top-down** model, as well as the **Bottom-up** model,  
is the need for a special syntax to create a coroutine that will execute independently of the parent.

For example:

```php
function task(): void 
{
    globalScope()->spawn(function { // <-- create a coroutine in the global scope
        sleep(10);
        echo "Hello, World!";
    });

    spawn function {
        sleep(1);
        echo "Hello, PHP!";
    };
    
    spawn function {
        echo "Hello, PHP2!";
    };
    
    // await only "Hello, PHP!" and "Hello, PHP2!"
    await children;
}
```

#### Use Cases

Let's briefly review typical use cases for each model:

| Use Case                                   | No Limitation                         | Bottom-up                                | Top-down                                        |
|--------------------------------------------|---------------------------------------|------------------------------------------|-------------------------------------------------|
| Asynchronous tasks in UI                   | ± Allows independent execution        | - May delay UI responsiveness            | - May prematurely interrupt child tasks         |
| Data processing where tasks spawn subtasks | - Requires manual synchronization     | + Ensures subtasks finish before parent  | - Risk of losing subtasks and data              |
| **Long-running background tasks**          | + Suitable for independent execution  | + Guarantees complete execution          | - Risks premature cancellation                  |
| **Server request handling**                | - Risk of resource leaks              | - Can lead to high memory consumption    | + Ensures resource cleanup                      |
| Parallel execution of independent tasks    | + Best for unrelated concurrent tasks | - Additional structure required          | - Poor; risks unwanted task cancellations       |
| **Hierarchical task management**           | - No built-in hierarchy               | ± Acceptable, but not ideal              | + Strongly hierarchical, controlled             |
| Actor-based concurrency                    | - Requires manual management          | - Actors may unintentionally block parent| + Ideal for actor-based concurrency             |


To make a choice in favor of a particular model, we should consider the nature of **PHP** as a language  
designed for business logic, tolerant to errors, easy to use, and focused on BackEnd applications.

Note that the **Top-down** model is best suited for frameworks because it allows **restricting**  
user code that is invoked lower in the hierarchy.

At the same time, **Top-down** will complicate the code of libraries or framework components  
that are invoked lower in the hierarchy if they need to modify coroutine behavior.

Such overhead is acceptable since frameworks and libraries typically take on **more** responsibility,  
implementing specific patterns or algorithms while hiding complexity behind contracts for the user.

The **primary argument** for choosing the **Top-down** model is that it forces the programmer to extend 
the lifetime of coroutines when necessary, rather than restricting them as in the Bottom-up model, 
which leads to resource minimization.