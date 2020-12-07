<?php

namespace Dynamo\Redis\Exceptions;

use Exception;
use Dynamo\Redis\Pipeline;

class PipelineException extends Exception {

    protected $commandQueue;
    protected $reply;

    public function __construct(
        string $msg,
        $commandQueue,
        $reply = null
    )
    {
        parent::__construct($msg);

        $this->commandQueue = array_map(function($cmd){
            return array_merge(
                [ $cmd->name ],
                $cmd->args
            );
        }, $commandQueue);

        $this->reply = $reply;
    }

    /**
     * Gets the commands queue, as an array of commands and its parameters
     *
     * @return array
     */
    public function getCommandQueue()
    {
        return $this->commandQueue;
    }

    /**
     * Gets the reply to the pipeline exec command, if any
     *
     * @return array|null
     */
    public function getReply()
    {
        return $this->reply;
    }
}