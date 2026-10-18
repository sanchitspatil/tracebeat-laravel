<?php

namespace TraceBeat\Laravel\Mail;

use Closure;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Http;
use Laravel\Nightwatch\Core;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Part\DataPart;
use Throwable;

/**
 * Sends the content of every outgoing mail to TraceBeat so it can be
 * previewed exactly as it was sent. The Nightwatch agent only reports the
 * subject and recipient counts; this adds the body, addresses and
 * attachment list. The server stores it only when the environment has
 * "Store mail content" switched on.
 *
 * The request is made after the response, so it never slows a page down.
 */
class MailCapture
{
    private const MAX_BODY = 1_000_000;

    private static ?Closure $redactor = null;

    /**
     * Change the payload before it leaves the application (mask addresses,
     * strip tokens from links, drop the body...). Return null to skip a mail.
     *
     * @param  Closure(array<string, mixed>): (array<string, mixed>|null)  $redactor
     */
    public static function redactUsing(Closure $redactor): void
    {
        self::$redactor = $redactor;
    }

    public static function forgetRedactor(): void
    {
        self::$redactor = null;
    }

    public function handle(MessageSent $event): void
    {
        $url = rtrim((string) config('tracebeat.url'), '/');
        $token = (string) config('nightwatch.token', config('tracebeat.token'));

        if (! config('tracebeat.capture_mail') || $url === '' || $token === '') {
            return;
        }

        try {
            $payload = $this->payload($event);
        } catch (Throwable) {
            return;
        }

        if (self::$redactor !== null) {
            $payload = (self::$redactor)($payload);

            if ($payload === null) {
                return;
            }
        }

        app()->terminating(function () use ($url, $token, $payload): void {
            try {
                Http::acceptJson()->withToken($token)->timeout(3)->post($url.'/api/mail-content', $payload);
            } catch (Throwable) {
                // Never let monitoring break the application.
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(MessageSent $event): array
    {
        $message = $event->message;
        $data = $event->data ?? [];

        return [
            ...$this->execution(),
            'timestamp' => microtime(true),
            'mailer' => (string) ($data['mailer'] ?? ''),
            'class' => (string) ($data['__laravel_mailable'] ?? $data['__laravel_notification'] ?? ''),
            'subject' => (string) $message->getSubject(),
            'from' => $this->addresses($message->getFrom()),
            'to' => $this->addresses($message->getTo()),
            'cc' => $this->addresses($message->getCc()),
            'bcc' => $this->addresses($message->getBcc()),
            'reply_to' => $this->addresses($message->getReplyTo()),
            'html' => mb_strcut((string) $message->getHtmlBody(), 0, self::MAX_BODY),
            'text' => mb_strcut((string) $message->getTextBody(), 0, self::MAX_BODY),
            'attachments' => array_map(fn (DataPart $part): array => [
                'name' => $part->getFilename(),
                'type' => $part->getMediaType().'/'.$part->getMediaSubtype(),
                'size' => strlen((string) $part->getBody()),
            ], $message->getAttachments()),
        ];
    }

    /**
     * Ties the mail to the request, command or job that sent it, using the
     * identifiers the Nightwatch sensors already assigned.
     *
     * @return array<string, string>
     */
    private function execution(): array
    {
        try {
            $state = app(Core::class)->executionState;

            return [
                'trace_id' => (string) $state->trace,
                'execution_id' => (string) $state->id,
                'execution_source' => (string) $state->source,
                'execution_preview' => (string) $state->executionPreview,
                'execution_stage' => (string) $state->stage->value,
                'deploy' => (string) $state->deploy,
                'server' => (string) $state->server,
            ];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  array<int, Address>  $addresses
     * @return list<array{address: string, name: string}>
     */
    private function addresses(array $addresses): array
    {
        return array_values(array_map(fn (Address $a): array => ['address' => $a->getAddress(), 'name' => $a->getName()], $addresses));
    }
}
