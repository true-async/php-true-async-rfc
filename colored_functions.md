# Colored Functions

Consider the following code:

```php
function logUsersStatus(BackedEnum $newStatus, string ...$users): void
{
    $scope = new Scope();

    foreach ($users as $user) {
        spawn with $scope fetchUserStatus($user);
    }
    
    foreach ($oldStatuses await $scope->directTasks() as $oldStatus) {
        // Write data to log
    }
}
```

The function does not return any result.
It does not make significant changes to the database.
Its execution depends only on the input data, and the result is not needed.
Therefore, the function can be invoked as a coroutine.

A stricter assertion holds true: **this function should always be called as a coroutine**.

However, according to this **RFC**,
any function can be invoked both as a coroutine and as a regular function. This rule has serious consequences:

1. It creates ambiguity in the use of the function.
2. It becomes impossible to build an automatic hierarchy of coroutine calls.
3. The hierarchy of function calls does not match the hierarchy of coroutine calls.

```php
function fetchUserData(string $userId): array
{
    spawn fetchUserProfile($userId);
    spawn fetchUserSettings($userId);
        
    [$userProfile, $userSettings] = await Async\directTasks();

    $userProfile['settings']  = $userSettings;
    
    return $userProfile;
}

function someFunction(string $userId): void 
{
    fetchUserData(string $userId);
}

// various calls
spawn fetchUserData();
fetchUserData();
someFunction();
spawn someFunction();
```

The `fetchUserData` function creates child coroutines and uses the `$scope` primitive to control them.
An `async` block can also be used to achieve the same effect.

But why make it so complicated? Wouldn't it be more natural to consider `fetchUserData` a coroutine?

If `fetchUserData` is always a coroutine,
it should always have its own scope, which doesn't need to be created explicitly.
In this case, `fetchUserData` should always take care of its child coroutines, not just occasionally.

The downside (which is also an advantage) of using a scope is that the programmer has the choice of
whether or not to control the coroutine space.
But is that really a good thing? If the programmer can opt out of control,
this increases the likelihood of errors.

Yes, the ability to forgo structural concurrency makes the code as flexible and expressive as possible,
but it also increases the risk of errors. So, what is more important?

The author of the article *"What Color is Your Function?"* presents the contamination of a function with a "color"
attribute as a negative argument and sees it as a problem. The author of the article assumes that functions can be colorless.
However, if a language supports structural concurrency, functions are always colored.
Therefore, the conclusions of the article are relevant only for languages like **Go**
and do not apply to languages that support structural concurrency.

```php
function fetchUserData(string $userId): array // <- Explicitly colored function
{
    $scope = Scope::inherit();
    // ...
    $scope->dispose();
```

The `fetchUserData` function creates a new scope to organize a structure of coroutines.
Using `Scope::inherit()` creates a new node in the hierarchy tree.
However, this code also makes the `fetchUserData` function colored because it assumes
that the function will be called DIRECTLY from another coroutine.

This is an invisible but logical contract. We can imagine the following code:

```php

function someFunction2(mixed $userId): void 
{
    // validate $userId and extra processing
    fetchUserData((string)$userId);
}

function someFunction1(array $user): void
{
    someFunction2($user['userId']);
}

function fetchUserData(string $userId): array
{
    $scope = Scope::inherit();
    // ...
    $scope->dispose();
}

spawn someFunction1($DATA['user']);
```

The `fetchUserData` function is called through two regular functions, `someFunction1()` and `someFunction2()`,
which are not coroutines. The functions `someFunction2`/`someFunction1` can easily act as adapter 
functions that perform additional actions before `fetchUserData` is actually used. But...

The invocation of intermediate functions `someFunction1` and `someFunction2`
breaks the explicit connection in the coroutine structure, and restoring it mentally is a complex and non-trivial task.
In other words, this is an anti-example, an **anti-pattern** of how **not** to use structural concurrency.

A general rule can be formulated:
> Coroutines should only be launched from **other coroutines** or **root scopes**.

In languages with structured concurrency (e.g., Kotlin), functions that work
with coroutines are implicitly "colored" because they depend on the execution context.
This is reflected in:

- Explicit passing of `CoroutineScope` or the use of `suspend` functions.
- The need to maintain the coroutine hierarchy to manage their lifecycle.

Example from Kotlin:

```kotlin
suspend fun fetchUserData(userId: String): Result { // suspend marks the function as a colored function
    coroutineScope {
        async { /* ... */ }
        async { /* ... */ }
    }
}
```

Explicit marking (`suspend`, `async`, `CoroutineScope`) makes the control flow predictable.
Thus, colored functions help better describe structured concurrency, 
but they also eliminate the ability to use adapter functions.

The article *"What Color is Your Function?"* states that colored functions require refactoring 
of the code that wants to call them. In other words, if `fetchUserData` needs to be called not as a coroutine 
but as a regular function, the code must be modified. 

Specifically, lines related to `$scope = Scope::inherit()` need to be removed 
and moved to another function, such as `someFunction2`.

Is this really a problem?

Suppose that `someFunction2` is an adapter that performs some dirty work before calling `fetchUserData`. 
There are several ways to use `someFunction2` without modifying `fetchUserData`:

- You can call `someFunction2` as a regular function and then call `fetchUserData` as a coroutine.
- You can make `someFunction2` a coroutine that then calls `fetchUserData`.
- You can use `someFunction2` as a filter that is invoked inside `fetchUserData`.

However, if `someFunction2` has greater responsibility, these approaches won’t work. 
In that case, the only option is to make `someFunction2` a coroutine and `fetchUserData` a regular function, 
effectively changing the areas of responsibility.

In other words, we conclude that 
if `fetchUserData` needs to be reused as a regular function due to changes in usage logic, code changes are unavoidable.

This nullifies the argument of the article *"What Color is Your Function?"* because 
there is no real difference in which code is modified—whether 
it’s adding a new attribute to a function or removing lines related to `$scope`.

Thus, we can conclude:
1. If coroutines use **structured concurrency**, i.e., they are required to monitor their child coroutines.
2. They should ONLY be used as coroutines, not as regular functions.
3. Using colored functions within the framework of structured concurrency
is a natural way to explicitly describe the structure.
