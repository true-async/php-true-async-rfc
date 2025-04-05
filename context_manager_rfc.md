### Context blocks

The `with` block allows you to create a code section in which
the `$scope` is explicitly created and explicitly disposed.

The following is a code example:

```php
$scope = new Scope();

try {
   await spawn with $scope {
       echo "Task 1\n";
   };     
} finally {
   $scope->disposeSafely();
}
```

Can be written using an `with` block as follows:

```php
async $scope {
   await spawn {
       echo "Task 1\n";
   };
}
```

The `with` block does the following:

1. It creates a new `Scope` object and assigns it to the variable specified at the beginning of the block as `$scope`.
2. All coroutines will, by default, be created within `$scope`.
   That is, expressions like `spawn <callable>` will be equivalent to `spawn with $scope <callable>`.
3. When the async block finishes its execution,
   it calls the `Scope::disposeSafely` method,
   or `Scope::dispose` if the `bounded` attribute is specified.

#### Motivation

The `with` block allows for describing groups of coroutines in a clearer
and safer way than manually using `Async\Scope`.

> The `with` block is similar to the `with` function in **JavaScript**, `suspended` in **Kotlin**, and **Python**.
> You can think of an `with` block as a direct analog of **colored functions**.
> This also means that if an RFC introducing colored functions is created, async blocks will no longer be needed.

* **Advantages**: Using `with` blocks improves code readability and makes it easier to analyze with static analyzers.
* **Drawback**: an `with` block is useless if `Scope` is used as an object property.

Consider the following code:

```php
function generateReport(): void
{
    $taskGroup = new TaskGroup(Scope::inherit());

    try {        
        spawn with $taskGroup fetchEmployees();
        spawn with $taskGroup fetchSalaries();
        spawn with $taskGroup fetchWorkHours();
    
        [$employees, $salaries, $workHours] = await $taskGroup;

        foreach ($employees as $id => $employee) {
            $salary = $salaries[$id] ?? 'N/A';
            $hours = $workHours[$id] ?? 'N/A';
            echo "{$employee['name']}: salary = $salary, hours = $hours\n";
        }

    } catch (Exception $e) {
        echo "Failed to generate report: ", $e->getMessage(), "\n";
    } finally {
        $taskGroup->dispose();
    }
}
```

The `with` statement allows you to group coroutines together and explicitly limiting the lifetime of the `Scope`:

```php
function generateReport(): void
{
    try {
        async inherit $scope {
        
            $taskGroup = new TaskGroup($scope);
        
            [$employees, $salaries, $workHours] = await $taskGroup->add([
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

Using an `with` block with `bounded` attribute makes it easier to describe a pattern
where a group of coroutines is created with one main coroutine and several secondary ones.  
As soon as the main coroutine completes, all the secondary coroutines will be terminated along with it.

```php
function startServer(): void
{
    async bounded $serverSupervisor {
    
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

#### With syntax

```php
with <expression> as [<var>] {
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

- `with` - a keyword that allows you to create a new `Scope` object
  and assign it to the variable specified in the `scope` parameter.

- `as` - a keyword that allows you to create a new `Scope` object
  and assign it to the variable specified in the `scope` parameter.

- `expression` - an expression that will be used to create a new `Scope` object.
  This expression must return an object of type `Async\Scope`.

options:

```php
// variable
async with $var as $scope {}
```

Function call:

```php
async with getScope() as $scope {}
```

Static method or class method:

```php
async with Scope::inherit() as $scope {}
async with $object->getScope() as $scope {}
```

Array element:

```php
async with $array[0] as $scope {}
```

- `codeBlock` - a block of code that will be executed in the `Scope` context.