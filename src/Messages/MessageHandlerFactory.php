<?php

namespace Stayallive\ServerlessWebSockets\Messages;

use Illuminate\Support\Str;
use Bref\Event\ApiGateway\WebsocketEvent;
use Stayallive\ServerlessWebSockets\Connections\ConnectionManager;

class MessageHandlerFactory
{
    public static function fromSocketEvent(WebsocketEvent $event, array $payload, ConnectionManager $channelManager): MessageHandler
    {
        if (Str::startsWith($payload['event'], 'internal:')) {
            return new InternalProtocolMessageHandler($payload, $event, $channelManager);
        }

        return Str::startsWith($payload['event'], 'pusher:')
            ? new PusherProtocolMessageHandler($payload, $event, $channelManager)
            : new PusherClientMessageHandler($payload, $event, $channelManager);
    }
}
