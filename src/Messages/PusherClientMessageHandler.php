<?php

namespace Stayallive\ServerlessWebSockets\Messages;

use Illuminate\Support\Str;
use Bref\Event\ApiGateway\WebsocketEvent;
use Stayallive\ServerlessWebSockets\Connections\ConnectionManager;
use Stayallive\ServerlessWebSockets\Connections\Channels\PresenceChannel;

class PusherClientMessageHandler implements MessageHandler
{
    use SendsPusherMessages;

    private WebsocketEvent $event;

    private array $payload;

    private ConnectionManager $channelManager;

    public function __construct(array $payload, WebsocketEvent $event, ConnectionManager $channelManager)
    {
        $this->payload        = $payload;
        $this->event          = $event;
        $this->channelManager = $channelManager;
    }

    public function handle(): void
    {
        if (!client_events_enabled()) {
            $this->respondToEvent(
                $this->event,
                $this->buildPusherErrorMessage('Client events are not allowed')
            );

            return;
        }

        if (!Str::startsWith($this->payload['event'], 'client-')) {
            $this->respondToEvent(
                $this->event,
                $this->buildPusherErrorMessage('Client events must be prefixed by `client-`')
            );

            return;
        }

        if (!$this->channelManager->isAuthenticatedChannel($this->payload['channel'])) {
            $this->respondToEvent(
                $this->event,
                $this->buildPusherErrorMessage('Publishing client events is only allowed on authenticated channels', 4009)
            );

            return;
        }

        $channel = $this->channelManager->findOrNewChannel($this->payload['channel']);

        if (!in_array($this->event->getConnectionId(), $channel->connectionIds())) {
            $this->respondToEvent(
                $this->event,
                $this->buildPusherErrorMessage('You must be subscribed to the channel before publishing events', 4009)
            );

            return;
        }

        $channel->broadcastToEveryoneExcept(
            $this->payload['event'],
            $this->payload['data'] ?? null,
            $this->event->getConnectionId()
        );

        if (webhook_event_enabled('client_event')) {
            queue_webhook('client_event', [
                'channel'   => $this->payload['channel'],
                'event'     => $this->payload['event'],
                'data'      => $this->payload['data'] ?? null,
                'socket_id' => $this->channelManager->findSocketIdForConnectionId($this->event->getConnectionId()),
                'user_id'   => $channel instanceof PresenceChannel ? $channel->findUserIdForConnectionId($this->event->getConnectionId()) : null,
            ]);
        }
    }
}
