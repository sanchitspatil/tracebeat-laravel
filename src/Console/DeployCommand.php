<?php

namespace TraceBeat\Laravel\Console;

use Illuminate\Console\Command;

/**
 * Tells TraceBeat about a deployment so charts can mark it and issues can
 * say "first seen in deploy X". Run it from your deploy script.
 */
class DeployCommand extends Command
{
    protected $signature = 'tracebeat:deploy
        {deploy? : A unique value for the deploy (default: NIGHTWATCH_DEPLOY or the Forge commit)}
        {--ref= : The git ref (tag or hash)}
        {--name= : A human readable name}
        {--url= : A link with information about the deploy}';

    protected $description = 'Send deployment metadata to TraceBeat';

    public function handle(): int
    {
        return $this->call('nightwatch:deploy', array_filter([
            'deploy' => $this->argument('deploy'),
            '--ref' => $this->option('ref'),
            '--name' => $this->option('name'),
            '--url' => $this->option('url'),
        ]));
    }
}
