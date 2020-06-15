<?php

namespace MidwestE\SlimRedirects;

class SlimRedirects implements \JsonSerializable
{
    private $json;

    public function __construct($mixed)
    {
        $this->setJson($mixed);
    }

    public static function factory($mixed)
    {
        return new self($mixed);
    }

    public function getJson()
    {
        return $this->json;
    }

    public function setJson($json)
    {
        $this->json = $json;
        return $this;
    }

    public function jsonSerialize()
    {
        return json_encode($this->getJson());
    }
}
