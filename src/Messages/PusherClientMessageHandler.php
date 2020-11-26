<?php

namespace Stayallive\ServerlessWebSockets\Messages;

use Illuminate\Support\Str;
use Bref\Event\ApiGateway\WebsocketEvent;
use Stayallive\ServerlessWebSockets\Connections\ConnectionManager;

class PusherClientMessageHandler implements MessageHandler
{
    use BuildsPusherMessages;

    private WebsocketEvent $event;

    private array $payload;

    private ConnectionManager $channelManager;

    public function __construct(array $payload, WebsocketEvent $event, ConnectionManager $channelManager)
    {
        $this->payload        = $payload;
        $this->event          = $event;
        $this->channelManager = $channelManager;
    }

    public function respond(): Message
    {
        if (getenv('APP_CLIENT_EVENTS') !== 'true') {
            return $this->buildPusherErrorMessage('Client events are not allowed');
        }

        if (!Str::startsWith($this->payload['event'], 'client-')) {
            return $this->buildPusherErrorMessage('Client events must be prefixed by `client-`');
        }

        if (!$this->channelManager->isAuthenticatedChannel($this->payload['channel'])) {
            return $this->buildPusherErrorMessage('Client event are only allowed on authenticated channels', 4009);
        }

        $channel = $this->channelManager->findChannel($this->payload['channel']);

        if ($channel !== null) {
            $channel->broadcastToEveryoneExcept($this->payload['event'], $this->payload['data'] ?? [], $this->event->getConnectionId());
        }

        // Because of API Gateway limitations we are required to respond with something, so we do with a simple message
        return $this->buildPusherAcknowledgeMessage();
    }
}
