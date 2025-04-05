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
    with new Scope()->asNotSafely() as $scope {
        
        $tasks = new \Async\TaskGroup($scope, true);
        
        foreach ($urls as $url) {
            $tasks->add(spawn fetchUrl($url));
        }

        $results = [];
        
        try {
            foreach (await $tasks as $url => $future) {
                $results[$url] = $future->getResult();
            }
        
            return $results;
        } finally {
            $tasks->dispose();
        }
    }
}

$urls = [
    'https://example.com',
    'https://php.net',
    'https://openai.com'
];

$results = await spawn fetchAllUrls($urls);
print_r($results);