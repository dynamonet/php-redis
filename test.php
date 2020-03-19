<?php

require __DIR__ . "/vendor/autoload.php";

$client = new \Dynamo\Redis\Client(new \Redis);
$client->connect(
    ( getenv('REDIS_HOST') ?: 'host.docker.internal' ),
    ( getenv('REDIS_PORT') ?: 26379 )
);

var_export($client->keys("*"));

$pipeline_result = $client->pipeline(function($pipe){
    $pipe->keys("*")
        ->incr("sarasa")
        ->get("sarasa")
        ->expire("sarasa", 10);
});

var_export($pipeline_result);