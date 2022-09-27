<?php

namespace Dynamo\Redis;

use Dynamo\Redis\Addons\Addon;
use Redis;

/**
 * Dynamo's Redis client (a wrapper for the phpredis client).
 * We created this wrapper in replacement of the predis library after profiling it.
 */
class Client
{
    protected $config;

    /**
     * \Redis
     */
    protected $client;

    protected $scripts;

    /**
     * @var \Dynamo\Redis\Addons\Addon[]
     */
    protected $addons = [];

    /**
     * User functions
     *
     * @var array
     */
    protected $functions = [];

    /**
     * Undocumented function
     *
     * @param array|Redis $config array, or a Redis instance https://github.com/phpredis/phpredis#class-redis
     * The config array may have the following keys:
     *  'host': string. can be a host, or the path to a unix domain socket.
     *  'port': int, optional. Default is 6379
     *  'timeout': float, value in seconds (optional, default is 0 meaning unlimited)
     *  'retry_interval': int, value in milliseconds (optional)
     *  'read_timeout': float, value in seconds (optional, default is 0 meaning unlimited)
     */
    public function __construct($config)
    {
        if(is_array($config)){
            $this->config = $config;
        } else {
            $this->client = $config;
        }

        $this->scripts = [];
    }

    public function addon(Addon $addon)
    {
        $key = get_class($addon);
        if(!array_key_exists($key, $this->addons)){
            $this->addons[$key] = $addon;
        }
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

            $this->checkClient();
            $sha1 = $script->getSha1();
            $reply = $this->client->script('exists', $sha1);
            
            $exists = (
                $sha1 !== null &&
                $reply &&
                $reply[0] === 1
            );

            if(!$exists){
                $load_result = $this->client->script('load', $script->getRawScript());
                if($load_result === false){
                    $error = $this->client->getLastError();
                    throw new \Exception("Failed to load Lua script '{$script->getAlias()}': {$error}");
                }
                $script->setSha1($load_result);
            }
            $script->loaded = true;
        }

        return true;
    }

    /**
     * Loads a functions script (only for Redis 7 and above), so that you can invoke your
     * custom functions by name, without using 'rawCommand', 'FCALL' nor specifying the number
     * of key arguments.
     *
     * @param string $path Path to the script where you define an register your Redis 7 functions
     * @param array $registered_functions An associative array having by key the name of a
     *              registered function, and the value of the key being the number of KEY arguments
     * @return void
     */
    public function loadFunctionScript(
        string $path,
        array $registered_functions,
        bool $load_now = true
    ){
        $this->checkClient();
        if($load_now){
            $load_result = $this->client->rawCommand("FUNCTION", "LOAD", "REPLACE", file_get_contents($path));
            if($load_result === false){
                $error = $this->client->getLastError();
                throw new \Exception("Failed to load functions script at '{$path}': {$error}");
            }
        }

        $this->functions = array_merge($this->functions, $registered_functions);
        
        return true;
    }

    public function isUserScript(string $scriptAlias) : bool
    {
        return array_key_exists($scriptAlias, $this->scripts);
    }

    public function isUserFunction(string $functionName) : bool
    {
        return array_key_exists($functionName, $this->functions);
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
        $this->checkClient();
        $commands = $pipeline->getCommands();
        //to-do: check if the pipeline has unloaded scripts

        if(count($commands) > 1){
            $batch = $this->client->multi(\Redis::PIPELINE);
            foreach($commands as $command){
                if($this->isUserFunction($command->name)){
                    $keycount = $this->functions[$command->name];
                    $batch->rawCommand('FCALL', $command->name, $keycount, ...$command->args);
                } else if(array_key_exists($command->name, $this->scripts)){
                    // is user script
                    $script = $this->scripts[$command->name];
                    $batch->evalSha(
                        $script->getSha1(),
                        $command->args,
                        $script->getNumKeys()
                    );
                } else {
                    $batch->{$command->name}(...$command->args);
                }
            }
            $result = $batch->exec();
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
        $this->checkClient();
        $error = null;

        if($this->isUserFunction($name)){
            $keycount = $this->functions[$name];
            $result = $this->client->rawCommand('FCALL', $name, $keycount, ...$args);
        } else if(array_key_exists($name, $this->scripts)){
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
        } else if(($addon = $this->isAddonCommand($name)) !== null){
            $addon->exec($name, $args, $this);
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

    protected function checkClient()
    {
        if(!$this->client && is_array($this->config)){
            $this->client = new Redis();
            $cfg = $this->config;
            $this->client->connect(
                $cfg['host'],
                (int) ($cfg['port'] ?? 6379),
                (float) ($cfg['timeout'] ?? 0),
                NULL,
                (int) ($cfg['retry_interval'] ?? 0)
            );

            foreach($this->addons as $addon){
                $addon->afterConnect($this);
            }
        }
    }

    public function isAddonCommand(string $name) : ?Addon
    {
        foreach($this->addons as $addon){
            if($addon->hasCommand($name)){
                return $addon;
            }
        }

        return null;
    }
}