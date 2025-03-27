<?php

use function Async\delay;

function fetchWithRetry(string $url, int $maxRetries = 3): string
{
    $retries = 0;
    
    while (true) {
        try {
            return http_request("GET", $url);
        } catch (Exception $e) {
            if (++$retries >= $maxRetries) {
                throw new Exception("Failed to fetch $url after $maxRetries attempts: " . $e->getMessage());
            }
            
            delay(1000); // Back off before retry
        }
    }
}

function scrapeWebsites(array $urls, int $concurrency = 5): array
{
    async $scraperScope {
        // Rate limiter implementation
        $rateLimiter = new \WebScraper\RateLimiter($concurrency);
        
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
        
        $scraperScope->awaitAll();
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