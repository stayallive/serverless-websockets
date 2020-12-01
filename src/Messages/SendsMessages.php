<?php

namespace Stayallive\ServerlessWebSockets\Messages;

use Bref\Event\ApiGateway\WebsocketEvent;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Stayallive\ServerlessWebSockets\Exceptions\CouldNotSendSocketMessage;

trait SendsMessages
{
    protected function respondToEvent(WebsocketEvent $event, Message $message): void
    {
        try {
            $this->sendMessageToConnection($event->getConnectionId(), $message);
        } catch (CouldNotSendSocketMessage $e) {
            // We ignore any failed sending attempts and let the client timeout
        }
    }

    protected function sendMessageToConnection(string $connectionId, Message $message): void
    {
        try {
            socket_client()->message($connectionId, $message->toMessageBody());
        } catch (ClientExceptionInterface $e) {
            throw CouldNotSendSocketMessage::fromException($e);
        }
    }
}
