<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

it('registers the four commands', function () {
    $commands = array_keys(Artisan::all());

    expect($commands)->toContain('tracebeat:install', 'tracebeat:agent', 'tracebeat:deploy', 'tracebeat:status');
});

it('copies TRACEBEAT_URL to the variable the Nightwatch agent reads', function () {
    expect($_SERVER['NIGHTWATCH_BASE_URL'])->toBe('https://tracebeat.example.test')
        ->and(getenv('NIGHTWATCH_BASE_URL'))->toBe('https://tracebeat.example.test');
});

it('reports a working connection', function () {
    Http::fake(['tracebeat.example.test/api/agent-auth' => Http::response(['token' => 't', 'expires_in' => 3600, 'refresh_in' => 3000, 'ingest_url' => 'https://tracebeat.example.test/api/ingest'])]);

    $this->artisan('tracebeat:status')->expectsOutputToContain('Connected')->assertSuccessful();

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer token-abc'));
});

it('explains an unknown token', function () {
    Http::fake(['*' => Http::response(['message' => 'Unknown token.'], 401)]);

    $this->artisan('tracebeat:status')->expectsOutputToContain('does not know this token')->assertFailed();
});

it('explains a stopped environment and an exhausted quota', function () {
    Http::fake(['*' => Http::sequence()->push(['message' => 'paused'], 403)->push(['message' => 'quota'], 429)]);

    $this->artisan('tracebeat:status')->expectsOutputToContain('paused or suspended')->assertFailed();
    $this->artisan('tracebeat:status')->expectsOutputToContain('event quota')->assertFailed();
});

it('refuses to run the agent without a url or token', function () {
    config(['tracebeat.url' => null]);
    $this->artisan('tracebeat:agent')->expectsOutputToContain('TRACEBEAT_URL')->assertFailed();

    config(['tracebeat.url' => 'https://x.test', 'nightwatch.token' => null]);
    $this->artisan('tracebeat:agent')->expectsOutputToContain('TRACEBEAT_TOKEN')->assertFailed();
});

it('publishes the config and writes the env lines', function () {
    $env = base_path('.env');
    File::put($env, "APP_NAME=Test\nTRACEBEAT_URL=old\n");
    File::delete(config_path('nightwatch.php'));

    $this->artisan('tracebeat:install --url=https://tb.example.test/ --token=secret-token')->assertSuccessful();

    expect(File::get($env))->toContain('TRACEBEAT_URL=https://tb.example.test')->toContain('TRACEBEAT_TOKEN=secret-token')->not->toContain('TRACEBEAT_URL=old')
        ->and(File::exists(config_path('nightwatch.php')))->toBeTrue()
        ->and(File::get(config_path('nightwatch.php')))->toContain("env('TRACEBEAT_TOKEN'");

    File::delete([$env, config_path('nightwatch.php')]);
});
