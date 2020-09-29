<?php

require __DIR__ . "/vendor/autoload.php";

use Dynamo\Redis\Streams\Consumer;
use Dynamo\Redis\LuaScript;

$client = new \Dynamo\Redis\Client(new \Redis);
$client->connect(
    ( getenv('REDIS_HOST') ?: 'host.docker.internal' ),
    ( getenv('REDIS_PORT') ?: 26379 )
);

$client->loadScript(
    LuaScript::fromFile(
        __DIR__."/src/Streams/claimpending.lua",
        5,
        'xClaimPending'
    ),
    true
);

$result = $client->xClaimPending(
    'stream:curljobs',
    'CONSUMERS',
    'TESTER',
    10000,
    1
);

var_dump($result);

