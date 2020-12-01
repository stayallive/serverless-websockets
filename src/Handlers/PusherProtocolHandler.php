<?php

namespace Stayallive\ServerlessWebSockets\Handlers;

use Bref\Context\Context;
use Psr\Log\LoggerInterface;
use Bref\Event\Http\HttpResponse;
use Bref\Event\ApiGateway\WebsocketEvent;
use Bref\Event\ApiGateway\WebsocketHandler;
use Stayallive\ServerlessWebSockets\Connections\ConnectionManager;
use Stayallive\ServerlessWebSockets\Messages\MessageHandlerFactory;

class PusherProtocolHandler extends WebsocketHandler
{
    private ConnectionManager $channelManager;

    private LoggerInterface $logger;

    public function __construct(ConnectionManager $channelManager, LoggerInterface $logger)
    {
        $this->channelManager = $channelManager;
        $this->logger         = $logger;
    }

    public function handleWebsocket(WebsocketEvent $event, Context $context): HttpResponse
    {
        $this->logger->info("Received WebSocket event for action:{$event->getEventType()}");

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
        $payload = json_decode($event->getBody(), true);

        if (empty($payload['event'])) {
            $this->logger->warning('Missing event type from message payload');

            return new HttpResponse('missing message event');
        }

        $this->logger->info("Handling WebSocket message for event:{$payload['event']}");

        MessageHandlerFactory::fromSocketEvent($event, $payload, $this->channelManager)->handle();

        return new HttpResponse('ok');
    }

    private function onConnect(WebsocketEvent $event, Context $context): HttpResponse
    {
        $this->channelManager->connect($event);

        return new HttpResponse('ok');
    }

    private function onDisconnect(WebsocketEvent $event, Context $context): HttpResponse
    {
        $this->channelManager->disconnect($event);

        return new HttpResponse('ok');
    }
}
