<?php

namespace TraceBeat\Laravel\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'tracebeat:install
        {--url= : Base URL of your TraceBeat server}
        {--token= : Environment token}
        {--force : Overwrite config/nightwatch.php}';

    protected $description = 'Connect this application to TraceBeat: publish the config and write the .env lines';

    public function handle(): int
    {
        $target = config_path('nightwatch.php');

        if (! File::exists($target) || $this->option('force')) {
            File::copy(__DIR__.'/../../stubs/nightwatch.php', $target);
            $this->components->info('Published config/nightwatch.php (reads TRACEBEAT_TOKEN).');
        } else {
            $this->components->warn('config/nightwatch.php already exists. Use --force to replace it, or add TRACEBEAT_TOKEN to its token line yourself.');
        }

        $url = $this->option('url') ?: $this->ask('TraceBeat URL (for example https://tracebeat.example.com)', config('tracebeat.url'));
        $token = $this->option('token') ?: $this->secret('Environment token');

        $env = base_path('.env');

        if ($url && $token && File::exists($env)) {
            $contents = (string) File::get($env);
            $contents = $this->set($contents, 'TRACEBEAT_URL', rtrim((string) $url, '/'));
            $contents = $this->set($contents, 'TRACEBEAT_TOKEN', (string) $token);
            File::put($env, $contents);
            $this->components->info('Wrote TRACEBEAT_URL and TRACEBEAT_TOKEN to .env.');
        }

        $this->newLine();
        $this->line('Keep the agent running next to your application:');
        $this->line('  php artisan tracebeat:agent');
        $this->line('Check the connection at any time with:');
        $this->line('  php artisan tracebeat:status');
        $this->line('Mark deploys from your deploy script with:');
        $this->line('  php artisan tracebeat:deploy "$(git rev-parse HEAD)"');

        return self::SUCCESS;
    }

    private function set(string $contents, string $key, string $value): string
    {
        $line = $key.'='.$value;

        return preg_match('/^'.preg_quote($key, '/').'=.*$/m', $contents) === 1
            ? (string) preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $line, $contents)
            : rtrim($contents)."\n".$line."\n";
    }
}
