<?php

namespace Dynamo\Redis;

use Redis;

/**
 * Dynamo's Redis client (a wrapper for the phpredis client).
 * We created this wrapper in replacement of the predis library after profiling it.
 */
class Client
{
    protected $client;
    protected $scripts;

    public function __construct(Redis $client)
    {
        $this->client = $client;
        $this->scripts = [];
    }

    /**
     * Loads a Lua script. The script will be lazy-loaded (loaded only when needed).
     * @param string $filepath Absolute path the the Lua script
     * @param int $numkeys The number of arguments that the script must receives as KEYS arguments.
     * The rest of the arguments will be accessible using the ARGV array.
     * @param bool $loadNowIfNotExists Sets whether the script should be loaded
     * @param string $scriptAlias alias to invoke the script. If none is provided,
     * the basename of the lua file will be used
     * 
     */
    public function loadScript(
        LuaScript $script,
        bool $loadNowIfNotExists = false
    ){
        $scriptAlias = $script->getAlias();

        if($scriptAlias === null){
            throw new \Exception("LuaScript must have an alias on which it will be invoked");
        }

        $this->scripts[$scriptAlias] = $script;

        if($loadNowIfNotExists === true){
            $exists = (
                ($sha1 = $script->getSha1()) !== null &&
                ($reply = $this->client->script('exists', $sha1)) &&
                $reply[0] === 1
            );

            if(!$exists){
                $script->setSha1($this->client->script('load', $script->getRawScript()));
            }
            $script->loaded = true;
        }

        return true;
    }

    public function isUserScript(string $scriptAlias) : bool
    {
        return array_key_exists($scriptAlias, $this->scripts);
    }

    /**
     * @return bool Gets whether the script was explicitly loaded into Redis script cache
     * (using SCRIPT LOAD) by the current process. Please warn that if FALSE is returned,
     * it doesn´t mean the script is not loaded, it just means that the current process
     * does not know if the script is already loaded.
     */
    public function isScriptLoaded($scriptAlias) : bool
    {
        return (
            array_key_exists($scriptAlias, $this->scripts) &&
            $this->scripts[$scriptAlias]->loaded === true
        );
    }

    /**
     * Creates a pipeline to pipe/enqueue commands to be sent to Redis server
     * in a single round-trip
     *
     * @param callable|null $callable
     * @return Pipeline|array
     */
    public function pipeline(?callable $callable = null)
    {
        $result = null;
        $pipeline = new Pipeline($this);
        if($callable){
            $callable($pipeline);
            return $this->exec($pipeline);
        }

        return $pipeline;
    }

    public function exec(Pipeline $pipeline)
    {
        $commands = $pipeline->getCommands();
        //to-do: check if the pipeline has unloaded scripts
        /*if($pipeline->hasScriptCalls()){
            $invokedScripts = $pipeline->getInvokedScriptNames();
            foreach($invokedScripts as $scriptName){
                if(
                    array_key_exists($scriptName, $this->scripts) &&
                    !$this->scripts[$scriptName]->loaded
                ){
                    $script = $this->scripts[$scriptName];
                    if(!isset($script->raw_script)){
                        $script->raw_script = file_get_contents($scriptParams->filepath);
                    }
                    $script->sha1 = $this->client->script('load', $script->raw_script);
                if($scriptParams->sha1){
                    $scriptParams->loaded = true;
                }
                }
            }
        }*/

        if(count($commands) > 1){
            $transaction = $this->client->multi(\Redis::PIPELINE);
            foreach($commands as $command){
                if(array_key_exists($command->name, $this->scripts)){
                    // is user script
                    $script = $this->scripts[$command->name];
                    $transaction->evalSha(
                        $script->getSha1(),
                        $command->args,
                        $script->getNumKeys()
                    );
                } else {
                    $transaction->{$command->name}(...$command->args);
                }
            }
            $result = $transaction->exec();
        } else if(count($commands) === 1){
            //no pipeline. Invoke the command as a single command, but return the result inside an array reply
            $command = $commands[0];
            if(array_key_exists($command->name, $this->scripts)){
                // is user script
                $script = $this->scripts[$command->name];
                $reply = $this->client->evalSha(
                    $script->getSha1(),
                    $command->args,
                    $script->getNumKeys()
                );
                
            } else {
                $reply = $this->client->{$command->name}(...$command->args);
            }

            $result = [ $reply ];
        }

        return $result;
    }

    public function __call($name, $args)
    {
        $error = null;

        if(array_key_exists($name, $this->scripts)){
            $script = $this->scripts[$name];
            $result = $this->client->evalSha(
                $script->getSha1(),
                $args,
                $script->getNumKeys()
            );
            $error = $this->client->getLastError();
            if($error !== null && strpos($error, 'No matching script') !== false){
                // load the script into Redis script cache, and retry
                $script->setSha1($this->client->script('load', $script->getRawScript()));
                $script->loaded = true;
                $this->client->clearLastError();
                $result = $this->client->evalSha(
                    $script->getSha1(),
                    $args,
                    $script->getNumKeys()
                );
                $error = $this->client->getLastError();
            } else {
                $script->loaded = true;
            }
        } else {
            $result = $this->client->$name(...$args);
            $error = $this->client->getLastError();
        }

        if($error !== null){
            $this->client->clearLastError();
            throw new \Exception($error);
        }

        return $result;
    }
}