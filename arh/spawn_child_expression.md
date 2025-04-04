#### Spawn child expression

The `spawn with` expression allows you to create siblings relative to the specified scope.  
However, it can be useful to create a coroutine in a child scope to establish a clear hierarchy.

```php
use Async\Scope;

$scope = new Scope();

spawn with $scope wathcher();

spawn with $scope use($scope): void {
    foreach ($hosts as $host) {
        $child = Scope::inherit($scope);
        
        $coroutine = spawn with $scope {
            echo gethostbyname('php.net').PHP_EOL;
        };
        
        $coroutine->onComplete(fn() => $child->disposeSafely());
    }      
};

$scope->awaitIgnoringErrors();
```

**Structure:**

```
$scope = new Scope();
├── watcher()                   ← runs in the $scope
├── foreach($hosts)             ← runs in the $scope
├── $child = Scope::inherit($scope)
│   └── subtask1()              ← runs in the childScope
├── $child = Scope::inherit($scope)
│   └── subtask2()              ← runs in the childScope
├── $child = Scope::inherit($scope)
│   └── subtask3()              ← runs in the childScope
```

This structure separates main tasks belonging to the `$scope` and child tasks that are launched in child scopes.  
Each child task can be cancelled independently of the others, since it belongs to a separate scope (Supervisor pattern).

The `child` keyword is used to create a child scope from the specified scope.

```php
use Async\Scope;

$scope = new Scope();

spawn with $scope wathcher();

spawn with $scope use($scope): void {
    foreach ($hosts as $host) {
        spawn child {
            echo gethostbyname('php.net').PHP_EOL;
        };
    }      
};

$scope->awaitIgnoringErrors();
```

The expression `Spawn child` launches a coroutine in a child `Scope` derived from the specified one,
and this child `Scope` becomes tied to the lifetime of the newly created coroutine.