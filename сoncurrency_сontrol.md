# Concurrence control

This document analyzes the options for implementing concurrency control and is part of the RFC.

## Structures

### Coroutine Hierarchy
Coroutine hierarchy refers to structured concurrency. 
It allows linking the lifetimes of parent and child coroutines, 
where the parent waits for the completion of all child coroutines.

### Coroutine Grouping
Grouping coroutines helps manage multiple tasks as a single entity — 
to control their lifecycle, cancel them, and await their results.

### Group-Based Structured Concurrency
Using coroutine groups, it is also possible to build a hierarchy in 
which the parent-child relationship occurs not between individual coroutines, but between groups.

## Explicit Structured Concurrency

Explicit structured concurrency involves clearly defining the coroutine structure.

There are two implementations of explicit concurrency:
* Using syntactic blocks that explicitly indicate coroutine relationships
* Explicitly passing the parent to all child coroutines

An explicit implementation of group-based structured concurrency 
is only possible with the explicit passing of the group.

Using syntactic blocks for structured concurrency involves either defining 
a special code block to establish a hierarchy or adding a specific prefix to functions. For example:

```php
suspended function myFunction()
```

The keyword `suspended` indicates that `myFunction` is a coroutine. 
This kind of implementation is also referred to as an implementation with colored functions.
If you don't want to use colored functions, you are left with two other approaches, each of which requires 
EXPLICIT passing of the parent between function calls. 
This complicates the call semantics unless Dependency Injection (DI) is used. It also tightly couples functions to each other.

Essentially, explicitly passing the parent is equivalent to colored functions, 
but in this case, the "color" of the function is determined by the presence of a parameter.

How can this problem be solved?

## Implicit Structured Concurrency

Implicit-structured concurrency allows you to avoid passing the parent.

In this case, the relationships between coroutines are built automatically based on the order of their calls. 
The downside of this approach is that a coroutine may become a child when it shouldn't have. 
As a result, a coroutine might enter the hierarchy and extend the overall execution time 
of the coroutine group beyond what is necessary. This can be critical for server-side applications.

The second problem with this approach is that the programmer who developed the parent coroutine 
cannot be 100% sure that another programmer is using the hierarchy correctly, since the inheritance occurs implicitly.

## Two Models of Waiting for Child Coroutines

There are two models for waiting on child coroutines:

1. The parent waits for all descendants throughout the depth of the hierarchy.
2. The parent waits only for its direct children, which it explicitly launched.

The first approach is considered the classical implementation of structured concurrency 
and is present in all major languages that support it (Kotlin, Swift, etc.).  
**Advantage**: No dangling tasks (or **Orphaned coroutines**).  
**Disadvantage**: The execution time of the parent task may be undefined.

The second approach violates structured concurrency but provides better control over resource leaks.  
**Advantage**: The lifetime of the parent task is clearly defined.  
**Disadvantage**: Coroutines performing important actions may be prematurely interrupted.

### Mixed Implementation of Explicit and Implicit Concurrency

A mixed implementation of explicit and implicit concurrency can be considered:

* On one hand, the parent is passed implicitly between function calls
* On the other hand, coroutine creation must always be done explicitly

Here's how it might look:

```php
$scope = new Scope();

spawn with $scope {
    // Coroutine 1
    spawn child {
        // Coroutine 2
    };  
};
```

Compare with the example above:

```php
$scope = new Scope();

spawn with $scope {
    // Coroutine 1
    spawn {
        // Coroutine 2
    };  
};
```

In the first example, the parent `$scope` is passed to the child coroutine implicitly, 
but it is used EXPLICITLY via `spawn child`. 
An additional keyword after `spawn` helps clarify that the programmer specifically intends to create a child coroutine.

### Strict Usage of `spawn` Expressions

The use of `spawn` can be made strict by completely prohibiting its usage without explicitly 
specifying a `$scope` or a keyword that indicates a child coroutine — 
unless the current `$scope` is clearly understood from the context.

As a result, the user will no longer be able to use `spawn` in the global scope 
and will always be required to explicitly define a `Scope`. 
The additional keyword `child` will also make the expressions more verbose.

**Drawbacks:**
Nevertheless, the syntax with the `child` keyword does not change the fact that the connection 
between the parent and child coroutines remains implicit. 
This means that all the general issues of such an approach still persist.

### Combined Model with Default "Wait for All"

Classical-structured concurrency can be ensured by assuming that waiting 
for all child coroutines is the default behavior. 
This approach can be improved by introducing a special `async` block, similar to those found in `Kotlin`.

```php
async $scope {
    // waits for all child coroutines and does not return control until they are complete
};
```
