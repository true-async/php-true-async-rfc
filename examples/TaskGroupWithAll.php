<?php

declare(strict_types=1);

use Async\Scope;

function taskGroup(array $files): void
{
    async inherit $scope {
        
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
        
        foreach ($files as $file) {
            spawn $fileReadTask($file);
        }
        
        try {
            foreach (await $scope->tasks() as $file => $result) {
                echo "File $file: $result\n";
            }
        } catch (Exception $e) {
            echo "Caught exception: ", $e->getMessage();
        }
    }
}