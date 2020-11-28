<?php

namespace Stayallive\ServerlessWebSockets\Messages;

use Bref\Event\ApiGateway\WebsocketEvent;

trait SendsMessages
{
    protected function respondToEvent(WebsocketEvent $event, Message $message): void
    {
        $this->sendMessageToConnection($event->getConnectionId(), $message);
    }

    /**
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     */
    protected function sendMessageToConnection(string $connectionId, Message $message): void
    {
        socket_client()->message($connectionId, $message->toMessageBody());
    }
}
