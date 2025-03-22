<?php

declare(strict_types=1);

use Async\Scope;
use function Async\await;
use function Async\all;

function fetchUrl(string $url): string {
    $ctx = stream_context_create(['http' => ['timeout' => 5]]);
    return file_get_contents($url, false, $ctx);
}

function fetchAllUrls(array $urls): array
{
    $futures = [];
    
    foreach ($urls as $url) {
        $futures[$url] = spawn fetchUrl($url);
    }
    
    await all($futures);
    
    $results = [];
    
    foreach ($futures as $url => $future) {
        $results[$url] = $future->getResult();
    }
    
    return $results;
}

$urls = [
    'https://example.com',
    'https://php.net',
    'https://openai.com'
];

$results = await spawn fetchAllUrls($urls);
print_r($results);