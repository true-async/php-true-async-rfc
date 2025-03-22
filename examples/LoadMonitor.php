<?php

declare(strict_types=1);

function walker(string $entity, int $limit): \Generator
{
    $total = getTotalRecords();
    $from = 0;
    
    while (($records = getRecords($entity, $from, $limit)) !== null) {
        foreach ($records as $record) {
            yield $record;
        }
        
        $from += $limit;
        
        suspend;
    }
}

function processAll(string $entity): void
{
    foreach ($walker($entity, 1000) as $record) {
        processRecord($record);
    }
}

function loadMonitor(\Async\Coroutine $task, float $threshold = 1.00, int $checkInterval = 5): void
{
    while (true) {
        exec('cat /proc/loadavg', $output);
        $parts = explode(' ', $output[0]);
        $load = (float)$parts[0];
        
        if ($load > $threshold) {
            $task->cancel(new \Async\CancellationException("High load detected"));
            return;
        }
        
        sleep($checkInterval);
        unset($output);
    }
}

simpleLoadMonitor();

$task = spawn processAll('users');
$monitor = spawn simpleLoadMonitor($task, 0.5, 5);

try {
    await $task;
} finally {
    $monitor->cancel();
}