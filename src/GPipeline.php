<?php

namespace Dynamo\Redis;

use Redis;

/**
 * Grouped Pipeline
 * 
 * Groups commands on repeated keys.
 */
class GPipeline extends Pipeline
{
    protected $rPushMap;
    protected $hIncrMap;
    protected $expireMap;
    protected $xAckMap;
    protected $xDelMap;

    public function __construct(?Client $client = null)
    {
        parent::__construct($client);
    }

    public function rPush($listName, ...$args) : self
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

        return $this;
    }

    public function hIncrBy($key, $field, $incr) : self
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

        return $this;
    }

    /**
     * Sets an expiration date (a timeout) on a key.
     * 
     * @param int $key Key name
     * @param int $ttl The key's remaining Time-To-Live, in seconds.
     */
    public function expire($key, $ttl) : self
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

        return $this;
    }

    public function xAck($stream, $group, $id) : self
    {
        $id_arr = (
            is_array($id) ?
            $id :
            [ $id ]
        );

        if(!isset($this->xAckMap[$stream][$group])){
            $this->pipeline[] = (object) [
                'name' => 'xAck',
                'args' => [ $stream, $group, $id_arr ],
            ];
            $this->xAckMap[$stream][$group] = count($this->pipeline) - 1;
        } else {
            $index = $this->xAckMap[$stream][$group];
            $existingCommand = $this->pipeline[$index];
            $existingCommand->args[2] = array_unique(
                array_merge($existingCommand->args[2], $id_arr)
            );
        }

        return $this;
    }

    public function xDel($stream, $id) : self
    {
        $id_arr = (
            is_array($id) ?
            $id :
            [ $id ]
        );

        if(!isset($this->xDelMap[$stream])){
            $this->pipeline[] = (object) [
                'name' => 'xDel',
                'args' => [ $stream, $id_arr ],
            ];
            $this->xDelMap[$stream] = count($this->pipeline) - 1;
        } else {
            $index = $this->xDelMap[$stream];
            $existingCommand = $this->pipeline[$index];
            $existingCommand->args[1] = array_unique(
                array_merge($existingCommand->args[1], $id_arr)
            );
        }

        return $this;
    }

    protected function flush()
    {
        parent::flush();
        $this->rPushMap = null;
        $this->hIncrMap = null;
        $this->expireMap = null;
        $this->xAckMap = null;
        $this->xDelMap = null;
    }
}