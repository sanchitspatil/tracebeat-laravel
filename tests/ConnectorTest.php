<?php

use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use TraceBeat\Laravel\Mail\MailCapture;

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

it('sends the mail content to TraceBeat after the response', function () {
    Http::fake(['*' => Http::response(['stored' => true], 202)]);
    config(['tracebeat.capture_mail' => true]);

    $message = (new Email)->from('hello@app.test')->to(new Address('jane@example.com', 'Jane'))->subject('Welcome')->html('<h1>Hi</h1>')->text('Hi')->attach('terms', 'terms.txt', 'text/plain');
    event(new MessageSent(new SentMessage(new Symfony\Component\Mailer\SentMessage($message, Envelope::create($message))), ['mailer' => 'smtp', '__laravel_mailable' => 'App\\Mail\\Welcome']));

    Http::assertNothingSent();
    app()->terminate();

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://tracebeat.example.test/api/mail-content'
            && $request->hasHeader('Authorization', 'Bearer token-abc')
            && $body['subject'] === 'Welcome'
            && $body['html'] === '<h1>Hi</h1>'
            && $body['to'][0]['address'] === 'jane@example.com'
            && $body['class'] === 'App\\Mail\\Welcome'
            && $body['attachments'][0]['name'] === 'terms.txt';
    });
});

it('does nothing when mail capture is off, and lets an application redact', function () {
    Http::fake();
    config(['tracebeat.capture_mail' => false]);
    $message = (new Email)->from('a@b.test')->to('c@d.test')->subject('S')->text('t');
    $sent = new SentMessage(new Symfony\Component\Mailer\SentMessage($message, Envelope::create($message)));

    event(new MessageSent($sent, []));
    app()->terminate();
    Http::assertNothingSent();

    config(['tracebeat.capture_mail' => true]);
    MailCapture::redactUsing(fn (array $payload) => null);
    event(new MessageSent($sent, []));
    app()->terminate();
    Http::assertNothingSent();
    MailCapture::forgetRedactor();
});
