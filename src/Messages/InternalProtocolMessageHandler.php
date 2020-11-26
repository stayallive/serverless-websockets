<?php

namespace Stayallive\ServerlessWebSockets\Messages;

use Illuminate\Support\Str;
use Bref\Event\ApiGateway\WebsocketEvent;
use Stayallive\ServerlessWebSockets\Connections\ConnectionManager;

class InternalProtocolMessageHandler implements MessageHandler
{
    use BuildsPusherMessages;

    private WebsocketEvent $event;

    private array $payload;

    private ConnectionManager $connectionManager;

    public function __construct(array $payload, WebsocketEvent $event, ConnectionManager $connectionManager)
    {
        $this->payload           = $payload;
        $this->event             = $event;
        $this->connectionManager = $connectionManager;
    }

    /**
     * @return \Stayallive\ServerlessWebSockets\Messages\Message
     *
     * @uses connect
     */
    public function respond(): Message
    {
        $eventName = Str::camel(Str::after($this->payload['event'], ':'));

        if (method_exists($this, $eventName)) {
            return $this->{$eventName}();
        }

        return $this->buildPusherErrorMessage('Internal protocol message handler not implemented.');
    }

    /**
     * This is as custom method that works around a API gateway limitation.
     *
     * You cannot respond to the connect event so we need the client to "request" a connect.
     */
    private function connect(): Message
    {
        $socketId = $this->connectionManager->findSocketIdForConnection($this->event->getConnectionId());

        if ($socketId === null) {
            return $this->buildPusherErrorMessage('Socket not registered.', 4200);
        }

        return $this->buildPusherEventMessage('pusher:connection_established', [
            'socket_id'        => $socketId,
            'activity_timeout' => 30,
        ]);
    }
}
