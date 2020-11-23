<?php

namespace Stayallive\ServerlessWebSockets\Messages;

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

    public function respond(): array
    {
        return $this->buildPusherErrorMessage('Pusher client message handler not implemented.');
    }
}
