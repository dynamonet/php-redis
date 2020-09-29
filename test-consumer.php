<?php

require __DIR__ . "/vendor/autoload.php";

use Dynamo\Redis\Streams\Consumer;

$client = new \Dynamo\Redis\Client(new \Redis);
$client->connect(
    ( getenv('REDIS_HOST') ?: 'host.docker.internal' ),
    ( getenv('REDIS_PORT') ?: 26379 )
);

$consumer = new Consumer(
    $client,
    [ 'stream:curljobs' ],
    'CONSUMERS'
);

$consumer->run(function($message) use($consumer){
    echo "Procesando mensaje...\n";
    var_dump($message);
    sleep(3);
    //echo "Ack...\n";
    $consumer->ackAndDelete($message);
});
