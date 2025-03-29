# Colored Functions with async Modifier

This **RFC** proposes an alternative approach based 
on "colored functions" using the `async` keyword. 

This approach offers several advantages:

1. Function call hierarchy automatically maps to coroutine hierarchy
2. Simplified coroutine lifecycle management
3. Asynchronous nature explicitly indicated in function signatures

### Syntax

```php
async [final static public protected private] function function_name([parameters])[: return_type] {
    // function body
}

// Calling from another async function
async function another_function(): void {
    spawn function_name();
    function_name(); // Direct call is not allowed, must be spawned
}

// Calling from a regular function
function regular_function(): void {
    spawn function_name(); // Via spawn
}
```

Example:

```php
async function fetchData(string $url): array
{
    return file_get_contents($url);
}

async function getUserProfile(string $userId): array
{
    spawn fetchData("api/users/$userId");       // Automatically becomes a child coroutine
    spawn fetchUserSettings($userId);           // Also a child coroutine
    
    [$userData, $settings] = await tasks;       // Wait for direct children to complete

    return ['data' => $userData, 'settings' => $settings];
}

// From a regular function, coroutines must be explicitly spawned
function handleRequest(string $userId): void
{
    spawn getUserProfile($userId);
}
```

### Hierarchy

Coroutines form a hierarchy that matches the call stack of functions:

```php

function task(string $userId): void
{
    spawn subtask($userId); // Automatically becomes a child coroutine
}

function subtask(string $userId): void
{
    spawn subSubtask($userId); // Automatically becomes a child coroutine
}

function subSubtask(): void 
{
    // ...
}

```

### Structured Concurrency

With colored functions, structured concurrency becomes built into the language at the syntax level:

```php
async function fetchData(string $url): array
{
    return file_get_contents($url);
}

async function fetchUserSettings(string $userId): array
{
    // Simulate fetching user settings
    return await spawn fetchData("api/users/$userId/settings");
}

async function fetchUserProfile(string $userId): array
{
    // called asynchronously
    spawn fetchUserSettings($userId); // Automatically becomes a child coroutine    
    
    return await fetchData("api/users/$userId");
    
    // Parent coroutine will wait for all child coroutines to complete!
}

async function processUser(string $userId): array
{
    // All async function calls automatically become child coroutines
    $profile = await fetchUserProfile($userId);
    $orders = await fetchUserOrders($userId);
    $recommendations = await getRecommendations($userId);

    return [
        'profile' => $profile,
        'orders' => $orders,
        'recommendations' => $recommendations
    ];
}
```

```text
processUser($userId)                // Parent coroutine
├── fetchUserProfile($userId)       // Child coroutine
│   ├── fetchUserSettings($userId)   // Child coroutine (spawned but not awaited explicitly)
│   └── fetchData("api/users/$userId")   // Child coroutine, awaited and completes first
├── fetchUserOrders($userId)      // Child coroutine
└── getRecommendations($userId)   // Child coroutine
```

With this approach:
- Functions with the `async` modifier can call each other directly
- Regular functions must use `spawn` to call `async` functions
- Canceling a parent coroutine automatically cancels all child coroutines

### Comparison with Current Proposal

The current RFC rejects colored functions in favor of transparent concurrency:

```php
// Current RFC approach
function processUser(string $userId): array
{
    async $scope {
        spawn fetchUserProfile($userId);
        spawn fetchUserOrders($userId);
        spawn getRecommendations($userId);

        return await $scope->allTasks();
    }
}
```

The proposed alternative approach with `async`:

```php
// Alternative approach
async function processUser(string $userId): array
{
    spawn fetchUserProfile($userId);
    spawn fetchUserOrders($userId);
    spawn getRecommendations($userId);

    // Wait for all child coroutines to complete
    return await allTasks(); 
}
```

### Advantages

1. **Explicit asynchronicity**: Function signature indicates its asynchronous nature
2. **Less boilerplate code**: No need to manually create and manage scopes
3. **Safety**: Automatic cancellation of all child coroutines when parent is cancelled
4. **Natural hierarchy**: Function call tree = coroutine tree
5. **Compatibility with other languages**: Similar to Kotlin and C# (`async`)

### Limitations

1. Transition to colored functions requires codebase changes
2. Mixing synchronous and asynchronous code becomes more explicit

# Comparative Table of PHP Asynchronous Approaches

| Aspect                                 | Scope Approach                                         | Colored Functions                                                                                        |
|----------------------------------------|--------------------------------------------------------|----------------------------------------------------------------------------------------------------------|
| **Syntax**                             | Explicit use of `spawn`, `async`, `await`              | `async` function modifier, automatic coroutine creation                                                  |
| **Coroutine Hierarchy**                | Manual management through scopes                       | Automatically corresponds to function call hierarchy                                                     |
| **Coroutine Creation**                 | `spawn`                                                | In async functions: automatically when calling another async function. In regular functions: via `spawn` |
| **Parallel Execution**                 | Explicit use of `spawn` for each task                  | Requires explicit `spawn` call within async function                                                     |
| **Awaiting Results**                   | `await $coroutine`                                     | `await function()`, `await`, `await allTasks()`                                                          |
| **Coroutine Cancellation**             | Explicit: `$scope->cancel()` or `$coroutine->cancel()` | Canceling parent coroutine automatically cancels all child coroutines                                    |
| **Structured Concurrency**             | Through `async $scope {}` blocks                       | Built into syntax via the `async` modifier                                                               |
| **Explicit Asynchronicity**            | Asynchronicity not visible in function signature       | Asynchronicity explicitly indicated in signature via `async`                                             |
| **Codebase Changes**                   | Doesn't require changing function signatures           | Requires adding `async` modifier to all asynchronous functions                                           |
| **Compatibility with Other Languages** | Unique approach for PHP                                | Similar to Kotlin and C# (`async`)                                                                       |
| **Sequential Execution**               | Requires explicit `await` for each task                | Requires explicit `await` for result waiting                                                             |
| **Boilerplate Code**                   | More boilerplate code for scope management             | Less boilerplate code, asynchronicity managed automatically                                              |

**Key Difference**: In colored_functions each asynchronous call is marked with the `async` modifier, 
and the coroutine hierarchy corresponds to the function call hierarchy, 
making code more readable and reducing explicit code for managing asynchronicity.