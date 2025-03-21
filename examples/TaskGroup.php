<?php

declare(strict_types=1);

use Async\Scope;

function taskGroup(array $files): void
{
    $scope = Scope::inherit();
    
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
    
    $tasks = [];
    
    foreach ($files as $file) {
        $tasks[$file] = spawn in $scope $fileReadTask($file);
    }
    
    try {
        foreach (await Async\all($tasks) as $file => $result) {
            echo "File $file: $result\n";
        }
    } catch (Exception $e) {
        echo "Caught exception: ", $e->getMessage();
    }
}