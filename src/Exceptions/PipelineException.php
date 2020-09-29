<?php

namespace Dynamo\Redis\Exceptions;

use Exception;

class PipelineException extends Exception {

    protected $pipeline;
    protected $reply;

    public function __construct(string $msg, array $pipeline, $reply = null)
    {
        parent::__construct($msg);
        $this->pipeline = $pipeline;
        $this->reply = $reply;
    }

    /**
     * Gets the commands queue, as an array of commands and its parameters
     *
     * @return array
     */
    public function getPipeline() : array
    {
        return $this->pipeline;
    }

    public function getReply()
    {
        return $this->reply;
    }
}