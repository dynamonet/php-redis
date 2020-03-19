<?php

namespace Dynamo\Redis\Utils;

use Dynamo\Redis\Client;
use Dynamo\Redis\LuaScript;

// Dynamo's Fixed-Rate-Leaky-Bucket throttler,
// handled entirely on the Redis side by a lua script
// Requires only 1 round-trip to Redis
class LPopN_WithThrottling
{
    private $redisClient;
    private $throttleName;
    private $tps;
    private $initiated = false;
    private $tokens;

    public function __construct(
        Client $redisClient,
        string $throttleName,
        int $tps
    ){
        $this->redisClient = $redisClient;
        $this->throttleName = $throttleName;
        $this->tps = $tps;
    }

    protected function init()
    {
        $this->redisClient->hSet($this->throttleName, 'tps', $this->tps);
        $this->redisClient->loadScript(
            LuaScript::fromRawScript(
                file_get_contents(__DIR__.'/lpopnwt.lua'),
                2,
                'lpopnwt'
            ),
            true
        );
        $this->initiated = true;
    }

    /**
     * Pops $n items from the given queues
     */
    public function pop(array $queues, int $n = 1, bool $withThrottling = true)
    {
        if(!$this->initiated){
            $this->init();
        }

        
        $args = array_merge(
            [
                $n,
                ( $withThrottling ? $this->throttleName : false ),
            ],
            $queues
        );

        $requested = microtime(true);
        $reply = $this->redisClient->lpopnwt(...$args);

        var_dump($reply);

        if($withThrottling && $reply[2] !== false){
            $firstWait = floatval($reply[2][0]);
            $interWait = floatval($reply[2][1]);
        }

        foreach($reply[1] as $index => $job){
            if($withThrottling){
                $spot = $requested + (
                    $index === 0 ?
                    $firstWait :
                    $firstWait + ($interWait * $index)
                );
                $sleep = $spot - microtime(true);
                if($sleep > 0){
                    usleep($sleep * 1000000);
                }
            }
            
            yield $job;
        }
    }

    /**
     * Consumes the requested tokens
     */
    public function consume()
    {
        foreach($this->tokens as $index => $timestamp){
            $sleep = $timestamp - microtime(true);
            if($sleep > 0){
                usleep($sleep * 1000000);
            }
            yield $index;
        }
    }
}