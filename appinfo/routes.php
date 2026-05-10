<?php
declare(strict_types=1);

return [
    'routes' => [
        // Talk bot webhook — NC Talk calls this when a bot message is received
        ['name' => 'bot#handle', 'url' => '/bot/handle', 'verb' => 'POST'],

        // File operations exposed to V1Ron characters
        ['name' => 'file_api#read',     'url' => '/api/file/read',     'verb' => 'POST'],
        ['name' => 'file_api#write',    'url' => '/api/file/write',    'verb' => 'POST'],
        ['name' => 'file_api#search',   'url' => '/api/file/search',   'verb' => 'POST'],
        ['name' => 'file_api#list',     'url' => '/api/file/list',     'verb' => 'POST'],
        ['name' => 'file_api#share',    'url' => '/api/file/share',    'verb' => 'POST'],

        // V1Ron API proxy — frontend uses this to talk to WordPress
        ['name' => 'v1ron_api#proxy',   'url' => '/api/v1ron/proxy',   'verb' => 'POST'],

        // Settings
        ['name' => 'settings#save',     'url' => '/api/settings',      'verb' => 'POST'],
        ['name' => 'settings#load',     'url' => '/api/settings',      'verb' => 'GET'],
    ],
];
