<?php

namespace Dynamo\Redis\Utils;

use Dynamo\Redis\Client;
use Dynamo\Redis\LuaScript;
use Traversable;

// Dynamo's Fixed-Rate-Leaky-Bucket throttler,
// handled entirely on the Redis side by a lua script
// Requires only 1 round-trip to Redis
class Throttle
{
    private $redisClient;
    private $throttleName;
    private $tps;
    private $initiated = false;
    private $itemsToConsume;
    private $requested;
    private $firstWait;
    private $interWait;

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
                file_get_contents(__DIR__.'/throttle.lua'),
                1,
                'throttle'
            ),
            true
        );
        $this->initiated = true;
    }

    public function getCurrentTps()
    {
        return (int) $this->tps;
    }

    public function changeTps(int $newTps)
    {
        $this->tps = $newTps;
        $this->redisClient->hSet($this->throttleName, 'tps', $newTps);
    }

    /**
     * Requests $tokenCount tokens to Redis
     * @param int|Countable $tokenCount  
     */
    public function request($tokenCount)
    {
        if(!$this->initiated){
            $this->init();
        }

        $count = (
            $tokenCount instanceof \Countable ?
            $tokenCount->count() :
            (int) $tokenCount
        );

        $this->requested = microtime(true);
        $reply = $this->redisClient->throttle($this->throttleName, $count);
        $this->firstWait = floatval($reply[0]);
        $this->interWait = floatval($reply[1]);

        if($tokenCount instanceof \Traversable){
            $this->itemsToConsume = $tokenCount;
        } else {
            $this->itemsToConsume = [];
            for($i = 0; $i < $count; $i++){
                $this->itemsToConsume[$i] = $i;
                /*$this->itemsToConsume[$i] = $requested + (
                    $i === 0 ?
                    $firstWait :
                    $firstWait + ( $interWait * $i )
                );*/
            }
        }
        

        
    }

    /**
     * Consumes the requested tokens
     */
    public function consume()
    {
        foreach($this->itemsToConsume as $index => $item){
            $timestamp = $this->requested + (
                $index === 0 ?
                $this->firstWait :
                $this->firstWait + ( $this->interWait * $index )
            );

            $sleep = $timestamp - microtime(true);

            if($sleep > 0){
                usleep($sleep * 1000000);
            }

            yield $item;
        }
    }

    /**
     * Consumes only the first token
     */
    /*public function consumeFirst()
    {
        if($this->tokens && count($this->tokens) > 0){
            $timestamp = array_shift($this->tokens);
            $sleep = $timestamp - microtime(true);
            if($sleep > 0){
                usleep($sleep * 1000000);
            }
        }
    }*/
}