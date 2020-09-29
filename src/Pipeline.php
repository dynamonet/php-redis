<?php

namespace Dynamo\Redis;

use Dynamo\Redis\Exceptions\PipelineException;
use Redis;

/**
 * Dynamo's Redis client (a wrapper for the phpredis client).
 * We created this wrapper in replacement of the predis library after profiling it.
 */
class Pipeline implements \Countable
{
    protected $client;
    protected $scriptCalls;

    public function __construct(?Client $client = null)
    {
        $this->client = $client;
        $this->pipeline = [];
        $this->scriptCalls = [];
    }

    public function getClient() : Client
    {
        return $this->client;
    }

    public function __call($name, $args) : self
    {
        if($this->client->isUserScript($name) && !in_array($name, $this->scriptCalls)){
            $this->scriptCalls[] = $name;
        }

        $this->pipeline[] = (object) [
            'name' => $name,
            'args' => $args,
        ];

        return $this;
    }

    public function hasScriptCalls()
    {
        return ( count($this->scriptCalls) > 0 );
    }

    /**
     * @return array Gets an array with the names of the user (lua) scrips invoked on this pipeline
     */
    public function getInvokedScriptNames() : array
    {
        return $this->scriptCalls;
    }

    /**
     * Gets the enqueued commands
     */
    public function getCommands()
    {
        return $this->pipeline;
    }

    public function toArray() : array
    {
        return array_map(function($cmd){
            return array_merge(
                [ $cmd->name ],
                ( is_array($cmd->args) ? $cmd->args : [ $cmd->args ] )
            );
        }, $this->pipeline);
    }

    /**
     * @return integer The total ammount of enqueued commands
     */
    public function count() : int
    {
        return count($this->pipeline);
    }

    public function hasQueuedCommands() : bool
    {
        return ( count($this->pipeline) > 0 );
    }

    public function exec()
    {
        $reply = $this->client->exec($this);
        if(count($reply) != count($this->pipeline)){
            $this->flush();
            throw new PipelineException("Unexpected reply length", $this, $reply);
        }
        $this->flush();

        return $reply;
    }

    /**
     * Clears the commands queue.
     *
     * @return void
     */
    public function flush()
    {
        $this->pipeline = [];
        $this->scriptCalls = [];
    }
}