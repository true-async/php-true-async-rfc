<?php

declare(strict_types=1);

namespace ProcessPool;

use Async\CancellationException;
use Async\Scope;

final class ProcessPool
{
    private Scope $watcherScope;
    private Scope $poolScope;
    private Scope $jobsScope;
    private array $pipes = [];
    private array $descriptors = [];
    
    public function __construct(readonly public string $entryPoint, readonly public int $max, readonly public int $min)
    {
        // Define the coroutine scopes for the pool, watcher, and jobs
        $this->poolScope = new Scope();
        $this->watcherScope = new Scope();
        $this->jobsScope = new Scope();
    }
    
    public function __destruct()
    {
        $this->watcherScope->dispose();
        $this->poolScope->dispose();
        $this->jobsScope->dispose();
    }
    
    public function start(): void
    {
        spawn in $this->watcherScope $this->processWatcher();
        
        for ($i = 0; $i < $this->min; $i++) {
            spawn in $this->poolScope $this->startProcess();
        }
    }
    
    public function stop(): void
    {
        $this->watcherScope->cancel();
        $this->poolScope->cancel();
        $this->jobsScope->cancel();
    }
    
    /**
     * Execute a job in a process.
     *
     * @param mixed    $job
     * @param callable $resultHandle
     */
    public function executeJob(mixed $job, callable $resultHandle): void
    {
        // Find a free process
        $pid = array_search(true, $this->descriptors, true);
        
        if ($pid === false && count($this->descriptors) < $this->max) {
            spawn in $this->poolScope $this->startProcess();
            
            // Try to find a free process again after a short delay
            spawn in $this->jobsScope use($job, $resultHandle) {
                usleep(100);
                
                $pid = array_search(true, $this->descriptors, true);
                
                if($pid === false) {
                    $resultHandle(new \Exception('No free process'));
                }
                
                $this->sendJobAndReceiveResult($pid, $job, $resultHandle);
            };
            
            return;
        } elseif ($pid === false) {
            $resultHandle(new \Exception('No free process'));
        } else {
            $this->descriptors[$pid] = false;
            spawn in $this->jobsScope $this->sendJobAndReceiveResult($pid, $job, $resultHandle);
        }
    }
    
    private function processWatcher(): void
    {
        while (true) {
            
            try {
                /**
                 * We expect all major tasks to be within the Scope for processes.
                 * Once a process is completed, the coroutine handling it should throw
                 * a StopProcessException or another exception.
                 * The exception will interrupt the infinite wait.
                 * If the coroutine exits with an exception other than StopProcessException,
                 * we will start another process. Otherwise, nothing needs to be done.
                 */
                await $this->poolScope->tasks();
            } catch (CancellationException) {
                return;
            } catch (StopProcessException $exception)  {
                echo "Process was stopped with message: {$exception->getMessage()}\n";
                
                // Start a new process if we are below the minimum
                if(count($this->descriptors) < $this->min) {
                    spawn in $this->poolScope $this->startProcess();
                }
                
            } catch (\Throwable $exception) {
                echo "Pool Exception: {$exception->getMessage()}\n";
                
                // restart
                spawn in $this->poolScope $this->startProcess();
            }
        }
    }
    
    private function startProcess(): void
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        
        $process = proc_open(['php', $this->entryPoint], $descriptorSpec, $pipes);
        
        if (!is_resource($process)) {
            throw new \RuntimeException('Failed to start process');
        }
        
        // get Pid
        $status = proc_get_status($process);
        
        if ($status === false) {
            throw new \RuntimeException('Failed to get process status');
        }
        
        $pid = $status['pid'];
        
        $this->pipes[$pid] = $pipes;
        // True if the process is free
        $this->descriptors[$pid] = true;
        
        // wait for a process to finish
        // Calling this function takes control of the coroutine until the process completes execution.
        // And that’s exactly what we need!
        $exitCode = proc_close($process);
        
        unset($this->pipes[$pid]);
        unset($this->descriptors[$pid]);
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        
        if($exitCode === 0) {
            throw new StopProcessException('Process finished with code 0');
        } else {
            throw new \Exception("Process finished with code $exitCode");
        }
    }
    
    private function sendJobAndReceiveResult(int $pid, mixed $job, callable $resultHandle): void
    {
        try {
            if(!isset($this->pipes[$pid])) {
                $resultHandle(new \Exception('Process not found'));
                return;
            }
            
            // Try to write to the process
            $written = fwrite($this->pipes[$pid][0], serialize($job));
            
            if ($written === false) {
                $resultHandle(new \Exception('Failed to write to process'));
                return;
            }
            
            // Read the result
            $result = stream_get_contents($this->pipes[$pid][1]);
            
            if ($result === false) {
                $resultHandle(new \Exception('Failed to read from process'));
                return;
            }
            
            $resultHandle(unserialize($result));
            
        } finally {
            // Free the process
            if(isset($this->descriptors[$pid]))
            {
                $this->descriptors[$pid] = true;
            }
        }
    }
}