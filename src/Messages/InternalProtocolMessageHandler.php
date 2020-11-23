<?php

namespace Stayallive\ServerlessWebSockets\Messages;

use Illuminate\Support\Str;
use Bref\Event\ApiGateway\WebsocketEvent;

class InternalProtocolMessageHandler implements MessageHandler
{
    use BuildsPusherMessages;

    private WebsocketEvent $event;

    private array $payload;

    public function __construct(array $payload, WebsocketEvent $event)
    {
        $this->payload = $payload;
        $this->event   = $event;
    }

    /**
     * @uses connect
     */
    public function respond(): array
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
    private function connect(): array
    {
        return $this->buildPusherEventMessage('pusher:connection_established', [
            'socket_id'        => $this->event->getConnectionId(),
            'activity_timeout' => 30,
        ]);
    }
}
