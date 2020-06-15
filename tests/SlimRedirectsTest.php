<?php

use MidwestE\SlimRedirects\SlimRedirects;
use PHPUnit\Framework\TestCase;

class SlimRedirectsTest extends TestCase
{

    private function jsonData()
    {
        return file_get_contents(__DIR__ . '/slimredirects.json');
    }

    private function slimRedirects()
    {
        return new SlimRedirects($this->jsonData());
    }

    public function testCreation()
    {
        $instance = $this->slimRedirects();
        $this->assertInstanceOf(SlimRedirects::class, $instance);

        $factory = SlimRedirects::factory($this->jsonData());
        $this->assertInstanceOf(SlimRedirects::class, $factory);
    }

    public function testJsonSerialize()
    {
        $instance = $this->slimRedirects();
        $json = $instance->getJson();
        $this->assertNotEmpty($json);

        $object = json_decode($json);
        $this->assertObjectHasAttribute('schema', $object);
    }
}
