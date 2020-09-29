<?php

require __DIR__ . "/vendor/autoload.php";

use Dynamo\Redis\Streams\Consumer;

$client = new \Dynamo\Redis\Client(new \Redis);
$client->connect(
    ( getenv('REDIS_HOST') ?: 'host.docker.internal' ),
    ( getenv('REDIS_PORT') ?: 26379 )
);

$client->pipeline()
    ->xAdd('stream:curljobs', "*", [
        'method' => 'get',
        'url' => 'http://sarasa',
        'payload' => 'sarasa'
    ])
    ->xAdd('stream:curljobs', "*", [
        'method' => 'post',
        'url' => 'http://cocacola',
        'payload' => 'milanesa'
    ])
    ->exec();
