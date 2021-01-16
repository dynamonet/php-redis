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

## Lua scripting

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
    myluacmd('blablabla', 2)
    ->exec();
```

