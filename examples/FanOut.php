<?php

declare(strict_types=1);

use Async\Scope;
use function Async\all;

function fetchUrl(string $url): string {
    $ctx = stream_context_create(['http' => ['timeout' => 5]]);
    return file_get_contents($url, false, $ctx);
}

function fetchAllUrls(array $urls): array
{
    async bounded $scope {
        foreach ($urls as $url) {
            spawn fetchUrl($url);
        }

        $results = [];
        
        foreach (await $scope->directTasks() as $url => $future) {
            $results[$url] = $future->getResult();
        }
        
        return $results;
    }
}

$urls = [
    'https://example.com',
    'https://php.net',
    'https://openai.com'
];

$results = await spawn fetchAllUrls($urls);
print_r($results);