<?php declare(strict_types=1);

use Dynamo\Redis\Client;
use Dynamo\Redis\Exceptions\PipelineException;
use PHPUnit\Framework\TestCase;
use Dynamo\Redis\GPipeline;

final class FunctionTest extends TestCase
{
    protected static $client;

    protected function getClient() : Client
    {
        if(!self::$client){
            self::$client = new Client([
                'host' => 'redis',
            ]);
        }

        return self::$client;
    }

    public function testFunctionLoad()
    {
        $client = $this->getClient();
        $result = $client->loadFunctionScript(__DIR__.'/testlib.lua', [
            'my_hset' => 1,
            'my_hgetall' => 1,
            'my_hlastmodified' => 1,
        ]);

        var_dump($result);
        
        $client->my_hset('testhash1', 'a', 1, 'b', 2, 'c', 3);
        $result = $client->hGetAll('testhash1');
        var_export($result);
        $this->assertIsArray($result);
    }

    public function testFunctionList(): void
    {
        $client = $this->getClient();
        $reply = $client->rawCommand('FUNCTION', 'LIST');
        
        $this->assertIsArray($reply);
    }

    public function testFunctions()
    {
        $client = $this->getClient();
        $client->my_hset('testhash1', 'a', 1, 'b', 2, 'c', 3);
        $result = $client->hGetAll('testhash1');
        var_export($result);
        $this->assertIsArray($result);
    }
}