<?php

use PHPUnit\Framework\TestCase;
use Midweste\SlimRedirects\RedirectRule;
use Midweste\SlimRedirects\RedirectUri;

class RedirectRuleTest extends TestCase
{


    private function loadRedirects()
    {
        $redirects = json_decode(file_get_contents(__DIR__ . '/slimredirects.json'))->redirects;
        return $redirects;
    }

    public function testCreation()
    {
        $rule = [
            "id" => "1",
            "source" => "/wild/*/card",
            "type" => "path",
            "destination" => "/wildcard/*",
            "httpStatus" => 301,
            "active" => 1
        ];
        $rule = new RedirectRule();
        foreach ($rule as $property => $value) {
            $rule->{'set' . $property}($value);
        }
        $this->assertInstanceOf(RedirectRule::class, $rule);
    }


    public function testSettersGetters()
    {
        $rule = [
            "id" => 1,
            "source" => "/wild/*/card",
            "type" => "path",
            "destination" => "/wildcard/*",
            "httpStatus" => 301,
            "active" => 1
        ];
        $redirectRule = new RedirectRule();
        foreach ($rule as $property => $value) {
            $redirectRule->{'set' . $property}($value);
        }
        $get = [];
        foreach ($rule as $property => $value) {
            $get[$property] = $redirectRule->{'get' . $property}($value);
        }
        $this->assertEquals($rule, $get);
    }

    public function testCreationFactory()
    {
        $rule = [
            "id" => "1",
            "source" => "/wild/*/card",
            "type" => "path",
            "destination" => "/wildcard/*",
            "httpStatus" => 301,
            "active" => 1
        ];
        $rule = RedirectRule::factory($rule);
        $this->assertInstanceOf(RedirectRule::class, $rule);
    }

    public function testToArray()
    {
        $rule = [
            "id" => "1",
            "source" => "/wild/*/card",
            "type" => "path",
            "destination" => "/wildcard/*",
            "httpStatus" => 301,
            "active" => 1
        ];
        $redirectRule = RedirectRule::factory($rule);
        $ruleArray = $redirectRule->toArray();
        $this->assertEquals($rule, $ruleArray);
    }

    public function testToObject()
    {
        $rule = (object) [
            "id" => 1,
            "source" => "/wild/*/card",
            "type" => "path",
            "destination" => "/wildcard/*",
            "httpStatus" => 301,
            "active" => 1
        ];
        $redirectRule = RedirectRule::factory($rule);
        $ruleArray = $redirectRule->toObject();
        $this->assertEquals($rule, $ruleArray);
    }
}
