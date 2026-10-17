<?php

namespace TraceBeat\Laravel\Console;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Checks the URL and token by doing what the agent does at start-up:
 * authenticating against /api/agent-auth.
 */
class StatusCommand extends Command
{
    protected $signature = 'tracebeat:status';

    protected $description = 'Check that this application can reach and authenticate with TraceBeat';

    public function handle(): int
    {
        $url = rtrim((string) config('tracebeat.url'), '/');
        $token = (string) config('nightwatch.token', config('tracebeat.token'));

        if ($url === '' || $token === '') {
            $this->components->error('TRACEBEAT_URL and TRACEBEAT_TOKEN must both be set.');

            return self::FAILURE;
        }

        try {
            $response = Http::acceptJson()->withToken($token)->timeout(10)->post($url.'/api/agent-auth', []);
        } catch (ConnectionException $e) {
            $this->components->error('Could not reach '.$url.': '.$e->getMessage());

            return self::FAILURE;
        }

        if ($response->successful()) {
            $this->components->info('Connected. Events will be sent to '.$response->json('ingest_url').'.');
            $this->line('Token valid for '.$response->json('expires_in').' seconds (the agent refreshes it by itself).');

            return self::SUCCESS;
        }

        $this->components->error(match ($response->status()) {
            401 => 'TraceBeat does not know this token. Copy it again from the environment settings.',
            403 => 'This environment is paused or suspended: '.$response->json('message'),
            429 => 'Your plan\'s event quota is used up: '.$response->json('message'),
            default => 'Unexpected answer ('.$response->status().').',
        });

        return self::FAILURE;
    }
}
