# Structured concurrency

This section will explore various models and implementations that can help
realize structural competition for `PHP`.

Let's analyze the following code:

```php
function task()
{
    spawn function {
        // ...
    };

    spawn function {
        // ...
    };
}

spawn task();
```

* What exactly did the programmer intend to achieve? 
* Why did they define two coroutines inside another one? 
* What is the purpose?

And:

* If the two coroutines inside `task()` are completely independent of it, why do they exist within it?
* If the two coroutines depend on `task()`, in what way do they rely on it?
* If a language allows a programmer to write code that has no meaningful purpose, 
are we really doing everything correctly?

Structured concurrency helps answer the questions above 
by defining a model for *interaction between coroutines*.

**It helps answer three key questions:**

* Should the coroutine's execution be awaited?
* When a coroutine completes, which other coroutines should also be completed? 
In other words, how are coroutine lifetimes interconnected?
* Which coroutines should be terminated in case of an error?

Or, in other words:
* Which code will limit the coroutine's lifetime and manage its resources?
* Which code will be responsible for handling the result?
* Which code will handle error processing?

In modern programming languages, three solution patterns can be identified:

- **Fire-and-forget** → Starts a coroutine/task without waiting for completion.
- **Run-and-wait** → Starts multiple coroutines/tasks and waits for all to finish.
- **Supervised execution** → Runs tasks with lifecycle control (failures don’t break everything).

| **Pattern**              | **Python**              | **Kotlin**           | **Go**             | **Elixir**        |
|--------------------------|-------------------------|----------------------|--------------------|-------------------|
| **Fire-and-forget**      | `asyncio.create_task()` | `launch {}`          | `go func() {}`     | `Task.async`      |
| **Run-and-wait**         | `asyncio.gather()`      | `coroutineScope {}`  | `sync.WaitGroup`   | `Task.await`      |
| **Supervised execution** | `asyncio.TaskGroup`     | `supervisorScope {}` | `context.Context`  | `Task.Supervisor` |

As we can see, different languages use two approaches to implement structured concurrency:
* Introducing special operators (as in Kotlin)
* Using methods and objects (as in Go, Python)

It is important to note that the structural unit is not a coroutine itself but another entity,
which is called `CoroutineScope` in Kotlin, `TaskGroup` in Python, and `Context` in Go.

It is worth noting that the classes and methods that provide structured concurrency 
are not part of the language itself. 
Therefore, they do not offer significant advantages in terms of expressiveness.

Should we rely solely on classes and methods? 
Or should we follow Kotlin’s approach and introduce 
a separate syntax element for defining a `Scope` block?

Adding a separate code block in `PHP` is not a simple task. 
It requires a comprehensive approach and analysis, 
including defining variable scope rules and possibly making deep changes at the virtual machine level.

From this perspective, it may be more beneficial 
to take a different approach and use functions themselves as natural structural elements. 
In other words, a function call within another function can be considered as a concurrency hierarchy.

In other words, we could say that the following code represents a structure:

```php
function task()
{
    spawn function1();
    spawn function2();
}

spawn task();
```

However, if we consider a different piece of code, we can notice a serious logical issue:

```php
function task()
{
    spawn function1();
    spawn function2();
}

task();
```

In the first case, `task` is called as a coroutine, while in the second case, it is called as a regular function.  
`task` itself has no way of knowing how it was invoked.

As a result, the programmer cannot make assumptions about which code the expression `spawn task()` belongs to.

This means that a function cannot be used as a structural scope.
Thus, we will use `Scope` as an element of structured concurrency, 
following the same model as in Python, Go, and other similar languages.

Now that the structural elements have been defined, 
it is necessary to determine how the relationships between coroutines will be described.

To answer this question, 
we need to examine how the lifetimes of coroutines within 
a hierarchy can be related to each other.

## Lifetime limitation

Let's take a closer look at the aspect of coroutine lifetime management and how it can be implemented.

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

In other languages (like `Erlang`/`Elixir`), the `Actor` model assumes
that the parent's lifetime restricts the lifetime of child Actors.

> **Actors** are a model of code interaction, whereas **coroutines** are an execution mechanism,
> and both approaches can be used together. Here, we are only interested in the approach
> to resource management, which can be implemented within coroutines.

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

### No Limitation

The main drawback of the **No Limitation** model is the need for manual resource management.
However, this can also be considered an advantage, as it provides maximum control.

### Bottom-up

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

### Top-down

The **Top-down** model limits the lifetime of child coroutines to the lifetime of the parent.  
It implements structured concurrency but, unlike the **Bottom-up** model,  
aims to release resources as quickly as possible.

```php
function task(): void 
{
    spawn(function() {
        sleep(10);
        echo "Hello, World!";
    });

    await spawn(function() {
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

To wait for direct descendants, the `await spawned` operator can be used:

```php
function task(): void 
{
    spawn(function() {
        sleep(10);
        echo "Hello, World!";
    });

    spawn(function() {
        sleep(1);
        echo "Hello, PHP!";
    });
    
    // parent lifetime is limited by direct descendants
    await spawned;
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
    await spawned;
}

spawn task();
```

The `Scope` primitive allows implementing **Bottom-Up** behavior within the **Top-Down** model.  
In the following example, the parent coroutine will wait for the completion of all child coroutines at every depth level.

```php
function task(): void 
{
    $coroutineScope = new Scope();
    
    $coroutineScope->spawn(function() {    
        sleep(10);
        echo "Hello, World!";
    });

    $coroutineScope->spawn(function() {
        sleep(1);
        echo "Hello, PHP!";
    });
    
    // parent lifetime is extended to all descendants
    await $coroutineScope;
}
```

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
    await spawned;
}
```

### Use Cases

Let's briefly review typical use cases for each model:

| Use Case                                   | No Limitation                         | Bottom-up                                    | Top-down                                        |
|--------------------------------------------|---------------------------------------|----------------------------------------------|-------------------------------------------------|
| Asynchronous tasks in UI                   | ± Allows independent execution        | - May delay UI responsiveness                | - May prematurely interrupt child tasks         |
| Data processing where tasks spawn subtasks | - Requires manual synchronization     | + Ensures subtasks finish before parent      | - Risk of losing subtasks and data              |
| **Long-running background tasks**          | + Suitable for independent execution  | + Guarantees complete execution              | - Risks premature cancellation                  |
| **Server request handling**                | - Risk of resource leaks              | - Can lead to high memory consumption        | + Ensures resource cleanup                      |
| Parallel execution of independent tasks    | + Best for unrelated concurrent tasks | - Additional structure required              | - Poor; risks unwanted task cancellations       |
| **Hierarchical task management**           | - No built-in hierarchy               | + Ensures the execution of child coroutines. | + Strongly hierarchical, controlled             |
| Actor-based concurrency                    | - Requires manual management          | - Actors may unintentionally block parent    | + Ideal for actor-based concurrency             |


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