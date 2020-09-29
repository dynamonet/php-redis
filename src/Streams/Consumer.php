<?php

namespace Dynamo\Redis\Streams;

use Dynamo\Redis\Client;
use Dynamo\Redis\GPipeline;
use Dynamo\Redis\LuaScript;

class Consumer {

    private $shutdown = false;

    /**
	 * @var \Dynamo\Redis\Client
	 */
	protected $client;
    
	/**
	 * @var array Array of all associated queues for this worker.
	 */
	protected $streams;

	/**
	 * The Redis stream GROUP to which this worker/consumer belongs
	 *
	 * @var string
	 */
	protected $group;

	/**
	 * @var string String identifying this worker.
	 */
	protected $id;

	/**
	 * Current pipeline
	 *
	 * @var \Dynamo\Redis\GPipeline
	 */
    protected $pipeline;
    
    /**
     * Message handler
     *
     * @var \callable
     */
    protected $processMessage;

    /**
     * Undocumented function
     *
     * @param Client|null $client
     * @param array $streams
     * @param string $group Name of the consumer's group to which this consumer will belong
     */
    public function __construct(
        ?Client $client,
        array $streams,
        string $group,
        ?callable $processMessage = null
	){
        $this->client = $client;
        $this->streams = $streams;
		$this->group = $group;
    }

    protected function init()
	{
        $this->registerSigHandlers();
        $id = $this->client->incr('dynamonet/redis:streams:nextconsumer');
        if($id === false){
            throw new \Exception("Failed to get next worker ID");
		}
        $this->id = "CONSUMER-{$id}";

		//Create the group in every queue (stream), if it doesn't exists
		foreach($this->streams as $stream){
            try{
                $this->client->xGroup('CREATE', $stream, $this->group, 0, true);
            } catch(\Exception $exc){}
        }
        
        $this->client->loadScript(
            LuaScript::fromFile(
                __DIR__."/claimpending.lua",
                5,
                'xClaimPending'
            ),
            true
        );
    }

    /**
     * Undocumented function
     *
     * @param integer $count
     * @param integer $timeout
     * @param integer $pendingToTake
     * @param integer $minIdleTime
     * @return \Ds\Vector
     */
    protected function takeMany(
		int $count,
        int $timeout = 5000,
        int $pendingToTake = 0,
        int $minIdleTime = 60000
	){
		if($this->pipeline === null){
			$this->pipeline = new GPipeline($this->client);
        }
        
        //if($this->pipeline->count() > 0){
        //    echo "Contenido del pipeline: \n";
        //    var_dump($this->pipeline->getCommands());
        //}

        //foreach($this->streams as $stream){
        //    $this->pipeline->xLen($stream);
        //}

        if($pendingToTake > 0){
            foreach($this->streams as $stream){
                $this->pipeline->xClaimPending(
                    $stream,
                    $this->group,
                    $this->id,
                    $minIdleTime,
                    $pendingToTake
                );
            }
        }

		// Blocking pop
		$this->pipeline->xReadGroup(
			$this->group,
			$this->id,
			$this->getStreamReadArgs(),
			$count,
			$timeout
		);

        $xReadReplyIndex = $this->pipeline->count() - 1;
        //var_dump($this->pipeline->getCommands());
        $reply = $this->pipeline->exec();

        if($reply === false){
            throw new \Exception("Pipeline error");
        }

        //Mostramos las longitudes de los streams
        /*for(
            $i = 0, $n = count($this->streams), $j = $xReadReplyIndex - $n;
            $i < $n;
            $i++, $j++
        ){
            printf("XLEN \"%s\": %d\n", $this->streams[$i], $reply[$j]);
        }*/

        $result = new \Ds\Vector();

        if($pendingToTake > 0){
            foreach($this->streams as $s => $stream){
                $xClaimReply = $reply[$xReadReplyIndex - count($this->streams) + $s];
                for($i = 0, $n = count($xClaimReply); $i < $n; $i += 2){
                    $id = $xClaimReply[$i];
                    $payload = $xClaimReply[$i + 1];
                    $result->push(new Message($payload, $stream, $id, true));
                }
            }
        }

		$xReadReply = $reply[$xReadReplyIndex];

        
        foreach($xReadReply as $stream => $messages){
            foreach($messages as $id => $payload){
                $result->push(new Message($payload, $stream, $id));
            }
        }

		return $result;
    }

    public function ack(Message $msg)
    {
        if($this->pipeline === null){
			$this->pipeline = new GPipeline($this->client);
        }
        
        $this->pipeline->xAck(
            $msg->getStream(),
            $this->group,
            $msg->getId()
        );
    }

    /**
     * Acks the message first and then removes it from the stream
     *
     * @param Message $msg
     * @return void
     */
    public function ackAndDelete(Message $msg)
    {
        if($this->pipeline === null){
			$this->pipeline = new GPipeline($this->client);
        }
        
        $this->pipeline->xAck(
            $msg->getStream(),
            $this->group,
            $msg->getId()
        );

        $this->pipeline->xDel(
            $msg->getStream(),
            $msg->getId()
        );
    }
    
    private function getStreamReadArgs() : array
	{
		return array_reduce($this->streams, function($carry, $item){
			$carry[$item] = '>';
			return $carry;
		}, []);
	}

    /**
     * Undocumented function
     *
     * @param callable $process Message callback
     * @return void
     */
    public function run(callable $process)
    {
        $this->init();

        while(!$this->shutdown){
            $messages = $this->takeMany(10, 5, 10);
            if($messages){
                foreach($messages as $msg){
                    if($this->shutdown){
                        break;
                    }
                    $process($msg);
                }
            }
        }

        if($this->pipeline && $this->pipeline->count() > 0){
            $this->pipeline->exec();
        }
    }

    public function shutdown($signal = null)
	{
		//$this->logger()->LogWarn("SHUTDOWN REQUESTED. SIGNAL: ".json_encode($signal));
		$this->shutdown = true;
	}

    private function registerSigHandlers()
	{
		if(!function_exists('pcntl_signal')) {
			return;
		}

		pcntl_async_signals(true);

		pcntl_signal(SIGTERM, array($this, 'shutdown'));
		//pcntl_signal(SIGKILL, array($this, 'shutdown'));
		pcntl_signal(SIGINT, array($this, 'shutdown'));
		pcntl_signal(SIGQUIT, array($this, 'shutdown'));
		//pcntl_signal(SIGUSR1, array($this, 'killChild'));
		pcntl_signal(SIGUSR2, array($this, 'pauseProcessing'));
		pcntl_signal(SIGCONT, array($this, 'unPauseProcessing'));
		//$this->logger()->LogInfo('OS signals successfuly registered');
	}
    

    

}