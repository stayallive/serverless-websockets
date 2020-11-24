<?php

namespace Stayallive\ServerlessWebSockets\Messages;

use Illuminate\Support\Str;
use Bref\Event\ApiGateway\WebsocketEvent;
use Stayallive\ServerlessWebSockets\Connections\ConnectionManager;

class PusherProtocolMessageHandler implements MessageHandler
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

    /**
     * @uses ping, connect, subscribe, unsubscribe
     */
    public function respond(): array
    {
        $eventName = Str::camel(Str::after($this->payload['event'], ':'));

        if (method_exists($this, $eventName)) {
            return $this->{$eventName}();
        }

        return $this->buildPusherErrorMessage('Pusher protocol message handler not implemented.');
    }

    /**
     * @see https://pusher.com/docs/channels/library_auth_reference/pusher-websockets-protocol#ping-and-pong-messages
     */
    private function ping(): array
    {
        return $this->buildPusherEventMessage('pusher:pong');
    }

    /**
     * @see https://pusher.com/docs/channels/library_auth_reference/pusher-websockets-protocol#-pusher-subscribe-client-pusher-channels-
     */
    protected function subscribe(): array
    {
        $socketId = $this->channelManager->findSocketIdForConnection($this->event->getConnectionId());

        return $this->channelManager->findOrCreateChannel($this->payload['data']['channel'])
                                    ->subscribe($this->event->getConnectionId(), $socketId, $this->payload['data'] ?? []);
    }

    /**
     * @see https://pusher.com/docs/channels/library_auth_reference/pusher-websockets-protocol#-pusher-unsubscribe-client-pusher-channels-
     */
    protected function unsubscribe(): array
    {
        return $this->channelManager->findOrCreateChannel($this->payload['data']['channel'])
                                    ->unsubscribe($this->event->getConnectionId());
    }
}
