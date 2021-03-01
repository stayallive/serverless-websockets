<?php

namespace Stayallive\ServerlessWebSockets\Handlers;

use Bref\Context\Context;
use Bref\Event\Sqs\SqsEvent;
use Bref\Event\Sqs\SqsRecord;
use Bref\Event\Sqs\SqsHandler;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class WebhookHandler extends SqsHandler
{
    public function handleSqs(SqsEvent $event, Context $context): void
    {
        foreach ($event->getRecords() as $record) {
            $this->handleRecord($record);
        }
    }

    private function handleRecord(SqsRecord $record): void
    {
        $message = json_decode($record->getBody(), true);
        $action  = $message['action'] ?? '';

        if ($action === 'send_webhook') {
            // Just in case the even got disabled but we were still processing
            if (!webhook_event_enabled($message['event']['name'])) {
                return;
            }

            $this->sendWebhook($message['event']['name'], [
                'time_ms' => $message['time'],
                'events'  => [$message['event']],
            ]);
        }
    }

    private function sendWebhook(string $event, array $message): void
    {
        try {
            webhook_client()->request('POST', webhook_target(), [
                'body'    => $body = json_encode($message),
                'headers' => [
                    'Content-Type'       => 'application/json',
                    'X-Pusher-Key'       => app_key(),
                    'X-Pusher-Signature' => hash_hmac('sha256', $body, app_secret()),
                ],
            ]);

            log_message("Webhook delivered for event: {$event}");
        } catch (TransportExceptionInterface $e) {
            // We ignore failures, we simply log them but pretend like everything went OK to prevent lambda retries
            log_message("Webhook delivery failure: {$e->getMessage()} (event: {$event})");
        }
    }
}
