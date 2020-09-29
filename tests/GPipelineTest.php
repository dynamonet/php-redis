<?php declare(strict_types=1);

use Dynamo\Redis\Client;
use PHPUnit\Framework\TestCase;
use Dynamo\Redis\GPipeline;

final class GPipelineTest extends TestCase
{
    protected $client;

    protected function getClient() : Client
    {
        if(!$this->client){
            $redis = new \Redis();
            $redis->connect(
                getenv('REDIS_HOST') ?: 'host.docker.internal',
                (int) (getenv('REDIS_PORT') ?: '6379')
            );
            $this->client = new Client($redis);
        }

        return $this->client;
    }
    public function testRpush(): void
    {
        $pipe = new GPipeline($this->getClient());
        $pipe->rPush('mylist', 'sarasa');
        $pipe->rPush('mylist', 'milanga');
        $pipe->rPush('mylist', 'porotos');
        $commands = $pipe->getCommands();
        var_dump($commands);
        $this->assertCount(1, $commands);
        $reply = $pipe->exec();
        $this->assertIsArray($reply);
        var_dump($reply);
    }

    public function testMixedCommands(): void
    {
        $pipe = new GPipeline($this->getClient());
        $pipe->del('mylist')
            ->del('myhash')
            ->rPush('mylist', 'sarasa')
            ->hIncrBy('myhash', 'counter', 1)
            ->rPush('mylist', 'milanga')
            ->hIncrBy('myhash', 'counter', 2)
            ->rPush('mylist', 'porotos')
            ->hIncrBy('myhash', 'counter', 3);

        $commands = $pipe->getCommands();
        var_dump($commands);
        $this->assertCount(4, $commands);
        $reply = $pipe->exec();
        $this->assertIsArray($reply);
        var_dump($reply);
    }

    public function testXAckGrouping(): void
    {
        $pipe = new GPipeline();
        $pipe->xAck('mystream', 'mygroup', '12345-1');
        $pipe->xAck('mystream', 'mygroup', ['12345-2', '12345-3']);
        $pipe->xAck('mystream', 'mygroup', '12345-4');
        $commands = $pipe->getCommands();
        $this->assertCount(1, $commands);
        $xAckCommand = $commands[0];
        $this->assertIsObject($xAckCommand);
        $this->assertEquals('xAck', $xAckCommand->name);
        $this->assertCount(3, $xAckCommand->args);
        $this->assertIsArray($xAckCommand->args[2]);
        $this->assertCount(4, $xAckCommand->args[2]);
        //var_dump($commands);
    }

    public function testXDelGrouping(): void
    {
        $pipe = new GPipeline();
        $pipe->xDel('mystream', '12345-1');
        $pipe->xDel('mystream', ['12345-2', '12345-3']);
        $pipe->xDel('mystream', '12345-4');
        $commands = $pipe->getCommands();
        $this->assertCount(1, $commands);
        $xDelCommand = $commands[0];
        $this->assertIsObject($xDelCommand);
        $this->assertEquals('xDel', $xDelCommand->name);
        $this->assertCount(2, $xDelCommand->args);
        $this->assertIsArray($xDelCommand->args[1]);
        $this->assertCount(4, $xDelCommand->args[1]);
        //var_dump($commands);
    }
}