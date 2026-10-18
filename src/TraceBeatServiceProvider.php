<?php

namespace TraceBeat\Laravel;

use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use TraceBeat\Laravel\Console\AgentCommand;
use TraceBeat\Laravel\Console\DeployCommand;
use TraceBeat\Laravel\Console\InstallCommand;
use TraceBeat\Laravel\Console\StatusCommand;
use TraceBeat\Laravel\Mail\MailCapture;

class TraceBeatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/tracebeat.php', 'tracebeat');

        $this->exposeBaseUrl();
    }

    public function boot(): void
    {
        $this->exposeBaseUrl();

        Event::listen(MessageSent::class, [MailCapture::class, 'handle']);

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([InstallCommand::class, AgentCommand::class, DeployCommand::class, StatusCommand::class]);

        $this->publishes([__DIR__.'/../config/tracebeat.php' => config_path('tracebeat.php')], 'tracebeat-config');
        $this->publishes([__DIR__.'/../stubs/nightwatch.php' => config_path('nightwatch.php')], 'tracebeat-nightwatch-config');
    }

    /**
     * The Nightwatch agent and the deploy command read NIGHTWATCH_BASE_URL
     * straight from the environment when they run, so TRACEBEAT_URL is
     * copied there once the application's configuration is loaded.
     */
    private function exposeBaseUrl(): void
    {
        $url = config('tracebeat.url');

        if (! is_string($url) || $url === '') {
            return;
        }

        $url = rtrim($url, '/');

        $_SERVER['NIGHTWATCH_BASE_URL'] = $_ENV['NIGHTWATCH_BASE_URL'] = $url;
        putenv('NIGHTWATCH_BASE_URL='.$url);
    }
}
