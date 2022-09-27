<?php

namespace Dynamo\Redis\Addons;

use Dynamo\Redis\Client;
use Dynamo\Redis\Pipeline;

abstract class Addon {

    /**
     * Executes a command.
     * 
     * @return mixed
     */
    public abstract function exec(string $cmd, array $args, Client $client);

    public function afterConnect(Client $client)
    {
        
    }

    public function hasCommand(string $cmd) : bool
    {
        return false;
    }

    public function batchCommand(string $cmd, array $args, Pipeline $batch) : bool
    {
        return false;
    }
}