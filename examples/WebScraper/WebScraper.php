<?php

use function Async\delay;

function fetchWithRetry(string $url, int $maxRetries = 3): string
{
    $retries = 0;
    while (true) {
        try {
            return await spawn http_request("GET", $url);
        } catch (Exception $e) {
            if (++$retries >= $maxRetries) {
                throw new Exception("Failed to fetch $url after $maxRetries attempts: " . $e->getMessage());
            }
            delay(1000); // Back off before retry
        }
    }
}

function scrapeWebsites(array $urls, int $concurrency = 5):
array {
    async $scraperScope {
        // Rate limiter implementation
        $rateLimiter = new class($concurrency) {
            private int $active = 0;
            private array $queue = [];
            
            public function __construct(private int $limit) {}
            
            public function acquire(): Awaitable {
                if ($this->active < $this->limit) {
                    $this->active++;
                    return new Future(true);
                }
                
                $future = new Future();
                $this->queue[] = $future;
                return $future;
            }
            
            public function release(): void {
                $this->active--;
                if (!empty($this->queue)) {
                    $future = array_shift($this->queue);
                    $this->active++;
                    $future->complete(true);
                }
            }
        };
        
        $results = [];
        
        foreach ($urls as $url) {
            spawn use($url, $rateLimiter, &$results) {
                try {
                    await $rateLimiter->acquire();
                    $html = await fetchWithRetry($url);
                    $results[$url] = $html;
                } finally {
                    $rateLimiter->release();
                }
            };
        }
        
        await $scraperScope->allTasks();
        return $results;
    }
}

// Usage
$urls = [
    'https://example.com',
    'https://php.net',
    'https://github.com',
    // ...many more URLs
];

$results = scrapeWebsites($urls, 3);