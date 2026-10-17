<?php

namespace TraceBeat\Laravel\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use TraceBeat\Laravel\TraceBeatServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [TraceBeatServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('tracebeat.url', 'https://tracebeat.example.test');
        $app['config']->set('nightwatch.token', 'token-abc');
    }
}
