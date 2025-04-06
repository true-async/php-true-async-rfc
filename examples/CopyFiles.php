<?php

declare(strict_types=1);

function copyFiles(string $destinationDir, string ...$files): void
{
    $fileCopying = new Async\TaskGroup();
    $copiedFiles = [];
    
    foreach ($files as $file) {
        spawn with $fileCopying use($destinationDir, $file, &$copiedFiles) {
            
            if (!file_exists($file)) {
                return;
            }
            
            $fileName = basename($file);
            
            if(file_exists($destinationDir.'/'.$fileName)) {
                throw new \Exception("File $destinationDir/$fileName already exists");
            }
            
            if(copy($file, $destinationDir.'/'.$fileName) === false) {;
                throw new \Exception("Failed to copy file $file to $destinationDir");
            }
            
            $copiedFiles[] = $fileName;
        };
    }
    
    try {
        await $fileCopying;
    } catch (Throwable $exception) {
        
        // Rollback pattern
        $fileCopying->cancel();
        
        await $fileCopying;
        
        $rollback = new Async\Scope();
        
        foreach ($copiedFiles as $file) {
            spawn with $rollback unlink($destinationDir.'/'.$file);
        }
        
        $rollback->awaitIgnoringErrors();
    }
}