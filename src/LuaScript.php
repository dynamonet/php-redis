<?php

namespace Dynamo\Redis;

class LuaScript
{
    protected $filepath;
    protected $raw_script;
    protected $numkeys;
    protected $alias;
    protected $sha1;

    public $loaded = false;

    private function __construct(
        ?string $filepath,
        ?string $raw_script,
        int $numkeys,
        ?string $alias = null
    ){
        $this->filepath = $filepath;
        $this->raw_script = $raw_script;
        $this->numkeys = $numkeys;
        $this->alias = (
            $alias === null && $filepath !== null ?
            pathinfo($filepath)['filename'] :
            $alias
        );

        if($raw_script !== null){
            $this->sha1 = \sha1($raw_script);
        }
    }

    public static function fromFile(
        string $filepath,
        int $numkeys,
        ?string $alias = null
    ) : LuaScript
    {
        return new self($filepath, null, $numkeys, $alias);
    }

    public static function fromRawScript(
        string $raw_script,
        int $numkeys,
        string $alias
    ) : LuaScript
    {
        return new self(null, $raw_script, $numkeys, $alias);
    }

    public function getAlias()
    {
        return $this->alias;
    }

    public function getNumKeys() : int
    {
        return $this->numkeys;
    }

    public function getSha1() : ?string
    {
        if(
            $this->sha1 == null &&
            $this->raw_script == null &&
            $this->filepath != null
        ){
            $this->raw_script = \file_get_contents($this->filepath);
            $this->sha1 = \sha1($this->raw_script);
        }

        return $this->sha1;
    }

    public function setSha1(string $sha1)
    {
        $this->sha1 = $sha1;
    }

    public function getRawScript() : ?string
    {
        if($this->raw_script == null && $this->filepath != null){
            $this->raw_script = \file_get_contents($this->filepath);
        }

        return $this->raw_script;
    }
}