<?php

namespace Stayallive\ServerlessWebSockets\Handlers;

use Bref\Context\Context;
use Bref\Event\Http\HttpResponse;
use Bref\Event\ApiGateway\WebsocketEvent;
use Bref\Event\ApiGateway\WebsocketHandler;
use Stayallive\ServerlessWebSockets\Connections\ConnectionManager;
use Stayallive\ServerlessWebSockets\Messages\MessageHandlerFactory;

class PusherProtocolHandler extends WebsocketHandler
{
    private ConnectionManager $channelManager;

    public function __construct(ConnectionManager $channelManager)
    {
        $this->channelManager = $channelManager;
    }

    public function handleWebsocket(WebsocketEvent $event, Context $context): HttpResponse
    {
        switch ($event->getEventType()) {
            case 'CONNECT':
                return $this->onConnect($event, $context);
            case 'DISCONNECT':
                return $this->onDisconnect($event, $context);
            default:
                return $this->onMessage($event, $context);
        }
    }

    private function onMessage(WebsocketEvent $event, Context $context): HttpResponse
    {
        $response = MessageHandlerFactory::fromSocketEvent($event, $this->channelManager)->respond();

        return new HttpResponse(json_encode($response));
    }

    private function onConnect(WebsocketEvent $event, Context $context): HttpResponse
    {
        $this->channelManager->connect($event);

        return new HttpResponse('connect');
    }

    private function onDisconnect(WebsocketEvent $event, Context $context): HttpResponse
    {
        $this->channelManager->disconnect($event);

        return new HttpResponse('disconnect');
    }
}
