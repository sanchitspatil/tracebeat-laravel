<?php

namespace TraceBeat\Laravel\Console;

use Illuminate\Console\Command;

/**
 * Runs the Nightwatch agent against your TraceBeat server. Keep it running
 * under Supervisor, systemd or a Forge daemon, like `nightwatch:agent`.
 */
class AgentCommand extends Command
{
    protected $signature = 'tracebeat:agent
        {--listen-on= : Address the application sends events to}
        {--server= : Name this server reports as}
        {--silent : Do not output any message}';

    protected $description = 'Run the TraceBeat agent (the Nightwatch agent pointed at your TraceBeat server)';

    public function handle(): int
    {
        if (blank(config('tracebeat.url'))) {
            $this->components->error('TRACEBEAT_URL is not set. Run `php artisan tracebeat:install` first.');

            return self::FAILURE;
        }

        if (blank(config('nightwatch.token'))) {
            $this->components->error('TRACEBEAT_TOKEN is not set. Create an environment in TraceBeat and copy its token.');

            return self::FAILURE;
        }

        return $this->call('nightwatch:agent', array_filter([
            '--listen-on' => $this->option('listen-on'),
            '--server' => $this->option('server'),
            '--silent' => $this->option('silent') ?: null,
        ]));
    }
}
