<?php declare(strict_types=1);

use Dynamo\Redis\Client;
use Dynamo\Redis\Exceptions\PipelineException;
use PHPUnit\Framework\TestCase;
use Dynamo\Redis\GPipeline;
use Dynamo\Redis\Utils\TimeBucketAddon;

final class TimeBucketAddonTest extends TestCase
{
    protected static $client;

    protected function getClient() : Client
    {
        if(!self::$client){
            self::$client = new Client([
                'host' => 'redis',
            ]);
            self::$client->addon(new TimeBucketAddon());
        }

        return self::$client;
    }

    public function testFunctionLoad()
    {
        $client = $this->getClient();
        $result = [];
        for($i = 0; $i < 6; $i++){
            // Add 5 random samples
            echo "Adding 5 random samples to bucket: " . time() . PHP_EOL;
            for($j = 0; $j < 5; $j++){
                $sample = random_int(5, 10);
                $client->tbadd("bucket", $sample);
            }
            sleep(1);
        }

        $this->assertIsArray($result);
    }

    public function testAverage()
    {
        $client = $this->getClient();
        $result = $client->tbavg("bucket", 5);
        $floatval = floatval($result);

        $this->assertIsString($result);
        $this->assertGreaterThan(24, $floatval);
    }

    public function testBatching()
    {
        $client = $this->getClient();
        $batch = $client->pipeline();
        // bucket 2
        $batch->tbadd("bucket2", 1);
        $batch->tbadd("bucket2", 2);
        $batch->tbadd("bucket2", 3);
        $batch->tbadd("bucket2", 4);
        $batch->tbadd("bucket2", 5);
        //bucket 3
        $batch->tbadd("bucket3", 6);
        $batch->tbadd("bucket3", 7);
        $batch->tbadd("bucket3", 8);
        $batch->tbadd("bucket3", 9);
        $batch->tbadd("bucket3", 10);
        $queue = $batch->getCommands();
        $this->assertCount(2, $queue);

        $cmd1 = $queue[0];
        $this->assertEquals('bucket2', $cmd1->args[0]);
        $this->assertEquals(15, $cmd1->args[1]);

        $cmd2 = $queue[1];
        $this->assertEquals('bucket3', $cmd2->args[0]);
        $this->assertEquals(40, $cmd2->args[1]);
    }
}