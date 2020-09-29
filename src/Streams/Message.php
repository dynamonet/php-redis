<?php

namespace Dynamo\Redis\Streams;

use Dynamo\Redis\Client;
use Dynamo\Redis\GPipeline;

class Message {
    protected $stream;
    protected $id;
    protected $payload;
    protected $isRetry;

    public function __construct(
        $payload,
        string $stream,
        string $id = '*',
        bool $isRetry = false
    )
    {
        $this->payload = $payload;
        $this->stream = $stream;
        $this->id = $id;
        $this->isRetry = $isRetry;
    }

    public function getPayload()
    {
        return $this->payload;
    }

    public function getStream()
    {
        return $this->stream;
    }

    public function getId()
    {
        return $this->id;
    }

}