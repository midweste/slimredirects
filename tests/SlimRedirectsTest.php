<?php

use MidwestE\SlimRedirects\Controller;
use PHPUnit\Framework\TestCase;

class SlimRedirectsTest extends TestCase
{

    private function slimRedirects(?string $requestUri = '/')
    {
        $_SERVER['REQUEST_URI'] = $requestUri;
        return new Controller();
    }

    public function testCreation()
    {
        $instance = $this->slimRedirects();
        $this->assertInstanceOf(Controller::class, $instance);
    }
}
