<?php

namespace Tests;

use PHPUnit\Runner\BeforeFirstTestHook;
use PHPUnit\Runner\AfterLastTestHook;
use PHPUnit\Runner\BeforeTestHook;
use PHPUnit\Runner\AfterTestHook;

/**
 * Add to php.xml after including this file
  <extensions>
    <extension class="Tests\SlimRedirectsTestRunner"/>
  </extensions>
 */
/**
 * https://phpunit.readthedocs.io/en/8.2/extending-phpunit.html?highlight=BeforeFirstTestHook#extending-the-testrunner
  AfterIncompleteTestHook
  AfterLastTestHook
  AfterRiskyTestHook
  AfterSkippedTestHook
  AfterSuccessfulTestHook
  AfterTestErrorHook
  AfterTestFailureHook
  AfterTestWarningHook
  AfterTestHook
  BeforeFirstTestHook
  BeforeTestHook
 */
final class SlimRedirectsTestRunner implements BeforeFirstTestHook, AfterLastTestHook, BeforeTestHook, AfterTestHook
{

    private $suiteDuration;

    public function __construct()
    {
        $this->testingDir = 'results';
        $this->phpExe = PHP_BINDIR . '/php';
    }

    public function executeBeforeFirstTest(): void
    {
        $this->suiteDuration = microtime(true);
    }

    public function executeAfterLastTest(): void
    {
        self::consolePrint("Testing time...(" . self::secondsSince($this->suiteDuration) . " s)");
    }

    public function executeBeforeTest(string $test): void
    {
        self::consolePrint("->$test", '', '');
    }

    public function executeAfterTest(string $test, float $time): void
    {
        $rounded = round($time, 2);
        self::consolePrint(" ({$rounded}s)");
    }

    public static function consolePrint($variable, $prefix = '', $suffix = PHP_EOL)
    {
        printf($prefix . $variable . $suffix);
    }

    public static function secondsSince($time)
    {
        return round(microtime(true) - $time, 2);
    }
}
