<?php

namespace Dynamo\Redis\Exceptions;

use Exception;
use Dynamo\Redis\Pipeline;

class PipelineException extends Exception {

    protected $pipeline;
    protected $reply;

    public function __construct(string $msg, Pipeline $pipeline, $reply = null)
    {
        parent::__construct($msg);
        $this->pipeline = $pipeline;
        $this->reply = $reply;
    }

    /**
     * Gets the commands queue, as an array of commands and its parameters
     *
     * @return Pipeline
     */
    public function getPipeline() : Pipeline
    {
        return $this->pipeline;
    }

    public function getReply()
    {
        return $this->reply;
    }
}