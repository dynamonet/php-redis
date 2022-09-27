# php-redis
A wrapper for the [phpredis](https://github.com/phpredis/phpredis) extension, with nice pipelining interfaces, Lua-scripting support, and other helpful utilities.

## Install

```
composer install dynamonet/redis
```

## Initialization

```php
use Dynamo\Redis\Client as RedisClient;

$redis = new RedisClient([
    'host' => 'localhost',
    'port' => 6379,
    //'timeout' => xxx,
    //'retry_interval' => xxx, //
]);

// you can now start sending commands, without the need for an explicit "connect" call
$redis->set('mykey', 'value');
```

## Pipelining

```php
$result = $redis->pipeline()
    ->set('key1', 'value1')
    ->incr('key2')
    ->get('key2')
    ->exec();

var_dump($result); // an array with the result for every queued command
```

## Redis 7 Functions

Redis 7 introduced the concept of "functions", which are named scripts, tipically written in Lua, which you invoke by the name you choose, like so:

```bash
FCALL myHelloFunction 1 "John Snow"
```

Prior to Redis functions, the only way to invoke a custom script was using the SHA1 string returned by the `LOAD SCRIPT` command :vomiting_face:

This library already has support for Redis 7 functions. All you have to do is register your functions, and then invoke them as if they were existing commands on the client.

```php
// Register my custom functions
$redis->loadFunctionScript($path_to_my_functions_script, [
    'functionA' => 1,
    'functionB' => 2,
    'functionC' => 0,
]);
```

Notice that associative array passed as the second argument. That argument is mandatory, and is the only way we have to tell the client which functions are exposed in the script, and how many key (mandatory) arguments have each of our custom functions.

```php
// Invoking my functions
$redis->functionA('Hello');
$redis->functionB('World', 2);
$redis->functionA();
```

## Lua scripting

Lua scripts in Redis are like traditional database Stored Procedures, but on steroids, because you write them in Lua, so you may use conditionals, loops, and all the flow control structures available in the Lua language. The only downside of Redis scripting (if your are using any version older than 7) is having to use a SHA1 string to invoke your scripts. Fortunately, this library will make your Lua scripting much more pleasant.

Recomended use: have your Lua scripts in separate .lua files, load them to your client, and assign each script an alias:

```php

use Dynamo\Redis\LuaScript;

$redis->loadScript(
    LuaScript::fromFile(
        $path_to_my_lua_script,
        2, // amount of mandatory arguments
        'myluacmd' //alias for the script
    ),
    true //register the script NOW. If "false" is provided, it will be lazy-loaded
);

//now, you can call your Lua command as if it were a regular Redis command
$redis->myluacmd('blablabla', 2);

//you can also call your custom commands in a pipeline
$result = $redis->pipeline()
    ->set('key1', 'value1')
    ->incr('key2')
    ->get('key2')
    ->myluacmd('blablabla', 2)
    ->exec();
```

