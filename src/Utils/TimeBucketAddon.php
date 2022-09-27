<?php

namespace Dynamo\Redis\Utils;

use Dynamo\Redis\Addons\Addon;
use Dynamo\Redis\Client;
use Dynamo\Redis\Pipeline;

class TimeBucketAddon extends Addon {

    public function afterConnect(Client $client){
        $result = $client->loadFunctionScript(__DIR__.'/tblib.lua', [
            'tbadd' => 2,
            'tbavg' => 2,
        ]);
    }

    public function hasCommand(string $cmd) : bool
    {
        return in_array(strtolower($cmd), [
            'tbadd',
            'tbavg',
        ]);
    } 

    /**
     * Executes a command.
     * 
     * @return mixed
     */
    public function exec(string $cmd, array $args, Client $client)
    {

    }

    public function batchCommand(string $cmd, array $args, Pipeline $batch) : bool
    {
        $cmdqueue = $batch->getCommands();
        foreach($cmdqueue as $cmd){
            if($cmd->name == 'tbadd' && $cmd->args[0] == $args[0]){
                $cmd->args[1] += ($args[1] ?? 1);
                return true;
            }
        }
        
        return false;
    }

}