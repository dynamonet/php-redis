<?php

namespace Dynamo\Redis;

use Redis;

/**
 * Grouped Pipeline
 * 
 * Groups commands on repeated keys
 */
class GPipeline extends Pipeline
{
    protected $rPushMap;
    protected $hIncrMap;
    protected $expireMap;

    public function __construct(?Client $client = null)
    {
        parent::__construct($client);
    }

    public function rPush($listName, ...$args)
    {
        if(!isset($this->rPushMap[$listName])){
            $this->pipeline[] = (object) [
                'name' => 'rPush',
                'args' => array_merge([ $listName ], $args),
            ];
            $this->rPushMap[$listName] = count($this->pipeline) - 1;
        } else {
            $existingCommand = $this->pipeline[$this->rPushMap[$listName]];
            $existingCommand->args = array_merge($existingCommand->args, $args);
        }
    }

    public function hIncrBy($key, $field, $incr)
    {
        if(!isset($this->hIncrMap[$key][$field])){
            $this->pipeline[] = (object) [
                'name' => 'hIncrBy',
                'args' => [ $key, $field, $incr ],
            ];
            $this->hIncrMap[$key][$field] = count($this->pipeline) - 1;
        } else {
            $index = $this->hIncrMap[$key][$field];
            $existingCommand = $this->pipeline[$index];
            $existingCommand->args[2] += $incr;
        }
    }

    /**
     * Sets an expiration date (a timeout) on a key.
     * 
     * @param int $key Key name
     * @param int $ttl The key's remaining Time-To-Live, in seconds.
     */
    public function expire($key, $ttl)
    {
        if(!isset($this->expireMap[$key])){
            $this->pipeline[] = (object) [
                'name' => 'expire',
                'args' => [ $key, $ttl ],
            ];
            $this->expireMap[$key] = count($this->pipeline) - 1;
        } else {
            $index = $this->expireMap[$key];
            $existingCommand = $this->pipeline[$index];
            $existingCommand->args[1] = $ttl;
        }
    }
}