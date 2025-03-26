### Async blocks and structured concurrency

**Structured concurrency** allows organizing coroutines into a group or hierarchy
to manage their lifetime or exception handling.

Structural concurrency implies that a parent cannot complete until all its child elements have finished executing.
This behavior helps prevent the release of resources allocated in the parent coroutine
until all children have completed their execution.

The `async` block allows you to create a code section in which the `$scope` is explicitly created and explicitly disposed.
The following is a code example:

```php
$scope = new Scope();

try {
   spawn with $scope {
       echo "Task 1\n";
   };     
} finally {
   $scope->disposeSafely();
   await $scope->allTasks();
}
```

Can be written using an `async` block as follows:

```php
async $scope {
   spawn {
       echo "Task 1\n";
   };
}
```

The `async` block does the following:

1. It creates a new `Scope` object and assigns it to the variable specified at the beginning of the block as `$scope`.
2. All coroutines will, by default, be created within `$scope`.
   That is, expressions like `spawn <callable>` will be equivalent to `spawn with $scope <callable>`.
3. When the execution of an async block comes to an end, all unfinished tasks are marked as **leaked**,
   appropriate warnings are displayed, and execution is halted until the tasks are completed or an exception occurs.

Thus, the async block defines a Scope:

1. Whose lifetime is strictly limited by the block itself
2. Whose execution will not proceed until all coroutines created within the `Scope` are completed,
   even if the programmer forgot to use `await`.

#### Motivation

The `async` block allows for describing groups of coroutines in a clearer
and safer way than manually using `Async\Scope`.

Consider the following code:

```php
function generateReport(): void
{
    $scope = Scope::inherit();

    try {
        [$employees, $salaries, $workHours] = await Async\all([
            spawn with $scope fetchEmployees(),
            spawn with $scope fetchSalaries(),
            spawn with $scope fetchWorkHours()
        ]);

        foreach ($employees as $id => $employee) {
            $salary = $salaries[$id] ?? 'N/A';
            $hours = $workHours[$id] ?? 'N/A';
            echo "{$employee['name']}: salary = $salary, hours = $hours\n";
        }

    } catch (Exception $e) {
        echo "Failed to generate report: ", $e->getMessage(), "\n";
    } finally {
        $scope->disposeSafely();
        await $scope->allTasks();
    }
}
```

The `async` statement allows you to group coroutines together and explicitly limiting the lifetime of the `Scope`:

```php
function generateReport(): void
{
    try {
        async inherit $scope {
            [$employees, $salaries, $workHours] = await Async\all([
                spawn fetchEmployees(),
                spawn fetchSalaries(),
                spawn fetchWorkHours()
            ]);
    
            foreach ($employees as $id => $employee) {
                $salary = $salaries[$id] ?? 'N/A';
                $hours = $workHours[$id] ?? 'N/A';
                echo "{$employee['name']}: salary = $salary, hours = $hours\n";
            }        
        }
        
    } catch (Exception $e) {
        echo "Failed to generate report: ", $e->getMessage(), "\n";
    }
}

```

Using an `async` block makes it easier to describe a pattern
where a group of coroutines is created with one main coroutine and several secondary ones.  
As soon as the main coroutine completes, all the secondary coroutines will be terminated along with it.

```php
function startServer(): void
{
    async $serverSupervisor {
    
      // Secondary coroutine that listens for a shutdown signal
      spawn use($serverSupervisor) {
         await Async\signal(SIGINT);
         $serverSupervisor->cancel(new CancellationException("Server shutdown"));
      }    
    
      // Main coroutine that listens for incoming connections
      await spawn {
            while ($socket = stream_socket_accept($serverSocket, 0)) {            
                connectionHandler($socket);
            }
        };
    }
}
```

In this example, the server runs until `stream_socket_accept` returns `false`, or until the user presses **CTRL-C**.

#### async syntax

```php
async [inherit] [<scope>] {
    <codeBlock>
}
```

**where:**

- `scope` - a variable that will hold the `Async\Scope` object.

options:

```php
// variable
async $scope {}
```

wrong use:

```php
// array element
async $scope[0] {}
async $$scope {}
// object property
async $object->scope {}
async Object::$scope {}
// function call
async getScope() {}
// The nullsafe operator is not allowed.  
async $object?->scope
// Using references is not allowed.
async &$object
```

- `inherit` - a keyword that allows inheriting the parent `Scope` object.

- `codeBlock` - a block of code that will be executed in the `Scope` context.