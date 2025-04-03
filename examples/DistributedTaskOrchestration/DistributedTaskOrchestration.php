<?php

use function Async\currentContext;

function orchestrateDistributedProcess(array $nodes, array $taskConfig): array
{
    async $orchestratorScope {
        $context = $orchestratorScope->context;
        $context->set('job_id', uniqid('job_'));
        $context->set('start_time', microtime(true));
        $context->set('node_results', new ArrayObject());
        
        $nodeHealth = [];
        $activeTasks = [];
        
        // Health check phase
        $countNodes = count($nodes);
        echo "Performing health checks on {$countNodes} nodes...\n";
        
        async inherited $healthCheckScope {
            foreach ($nodes as $nodeId => $nodeConfig) {
                spawn use($nodeId, $nodeConfig, &$nodeHealth) {
                    try {
                        $healthResult = checkNodeHealth($nodeConfig['endpoint']);
                        $nodeHealth[$nodeId] = $healthResult;
                        echo "Node $nodeId health: {$healthResult['status']}\n";
                    } catch (Exception $e) {
                        $nodeHealth[$nodeId] = [
                            'status' => 'offline',
                            'error' => $e->getMessage()
                        ];
                        echo "Node $nodeId is offline: {$e->getMessage()}\n";
                    }
                };
            }
            
            // Wait for all health checks to complete
            $healthCheckScope->awaitIgnoringErrors();
        }
        
        // Filter out unhealthy nodes
        $availableNodes = array_filter($nodeHealth, fn($h) => $h['status'] === 'online');
        
        if (empty($availableNodes)) {
            throw new Exception("No healthy nodes available for task execution");
        }
        
        echo "Starting distributed task on " . count($availableNodes) . " nodes\n";
        
        // Task distribution phase
        async inherited $distributionScope {
            $distributedTasks = distributeTaskLoad($taskConfig, array_keys($availableNodes));
            
            foreach ($distributedTasks as $nodeId => $nodeTasks) {
                spawn use($nodeId, $nodeTasks, $nodes, &$activeTasks) {
                    try {
                        $nodeConfig = $nodes[$nodeId];
                        $nodeJobId = currentContext()->get('job_id') . "-$nodeId";
                        
                        echo "Submitting " . count($nodeTasks) . " tasks to node $nodeId\n";
                        
                        // Submit tasks to node
                        $submitResult = submitTasksToNode($nodeConfig['endpoint'], $nodeJobId, $nodeTasks);
                        $activeTasks[$nodeId] = $submitResult['taskIds'];
                        
                        // Monitor task execution
                        while (true) {
                            Async\delay(2000);
                            
                            $status = checkTaskStatus($nodeConfig['endpoint'], $nodeJobId);
                            
                            echo "Node $nodeId progress: {$status['completed']}/{$status['total']}\n";
                            
                            if ($status['completed'] === $status['total']) {
                                // All tasks complete, fetch results
                                $results = fetchTaskResults($nodeConfig['endpoint'], $nodeJobId);
                                
                                // Store in context
                                $nodeResults = currentContext()->get('node_results');
                                $nodeResults[$nodeId] = $results;
                                
                                break;
                            }
                        }
                    } catch (Exception $e) {
                        echo "Error with node $nodeId: {$e->getMessage()}\n";
                        
                        // Store error in results
                        $nodeResults = currentContext()->get('node_results');
                        $nodeResults[$nodeId] = ['error' => $e->getMessage()];
                    }
                };
            }
            
            $distributionScope->awaitIgnoringErrors();
        }
        
        // Process and merge results
        $duration = microtime(true) - $context->get('start_time');
        $nodeResults = $context->get('node_results');
        
        $aggregatedResults = aggregateResults($nodeResults);
        
        return [
            'job_id' => $context->get('job_id'),
            'duration' => $duration,
            'node_count' => count($availableNodes),
            'results' => $aggregatedResults,
            'node_details' => $nodeResults
        ];
    }
}
