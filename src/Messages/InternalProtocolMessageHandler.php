<?php

namespace Stayallive\ServerlessWebSockets\Messages;

use Illuminate\Support\Str;
use Bref\Event\ApiGateway\WebsocketEvent;
use Stayallive\ServerlessWebSockets\Connections\ConnectionManager;

class InternalProtocolMessageHandler implements MessageHandler
{
    use SendsPusherMessages;

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
     * @uses connect
     */
    public function handle(): void
    {
        $eventName = Str::camel(Str::after($this->payload['event'], ':'));

        if (method_exists($this, $eventName)) {
            $this->{$eventName}();

            return;
        }

        $this->respondToEvent(
            $this->event,
            $this->buildPusherErrorMessage('Internal protocol message handler not implemented.')
        );
    }

    /**
     * This is as custom method that works around a API gateway limitation.
     *
     * You cannot respond to the connect event so we need the client to "request" a connect.
     */
    private function connect(): void
    {
        $connection = $this->connectionManager->findConnection($this->event->getConnectionId());

        if ($connection === null) {
            $this->respondToEvent(
                $this->event,
                $this->buildPusherErrorMessage('Socket not registered.', 4200)
            );

            return;
        }

        $this->respondToEvent(
            $this->event,
            $this->buildPusherEventMessage('pusher:connection_established', [
                'socket_id'        => $connection->getSocketId(),
                'activity_timeout' => 30,
            ])
        );
    }
}
