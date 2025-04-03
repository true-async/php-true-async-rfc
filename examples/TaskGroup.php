<?php

declare(strict_types=1);

use Async\Scope;

function taskGroup(array $files): void
{
    $fileReadTask = function (string $fileName): string {
        
        if(!is_file($fileName)) {
            throw new Exception("File not found: $fileName");
        }
        
        if(!is_readable($fileName)) {
            throw new Exception("File is not readable: $fileName");
        }
        
        $result = file_get_contents($fileName);
        
        if($result === false) {
            throw new Exception("Error reading file: $fileName");
        }
        
        return $result;
    };
    
    $scope = Scope::inherit();
    $tasks = new \Async\TaskGroup($scope);
    
    foreach ($files as $file) {
        $tasks->add(spawn in $scope $fileReadTask($file));
    }
    
    try {
        foreach (await $tasks as $result) {
            echo "File $result\n";
        }
    } catch (Exception $e) {
        echo "Caught exception: ", $e->getMessage();
    } finally {
        $tasks->dispose();
    }
}