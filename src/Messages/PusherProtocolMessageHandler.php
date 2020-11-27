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
    public function respond(): Message
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
    private function ping(): Message
    {
        return $this->buildPusherEventMessage('pusher:pong');
    }

    /**
     * @see https://pusher.com/docs/channels/library_auth_reference/pusher-websockets-protocol#-pusher-subscribe-client-pusher-channels-
     */
    protected function subscribe(): Message
    {
        $socketId = $this->channelManager->findSocketIdForConnectionId($this->event->getConnectionId());

        return $this->channelManager->findOrNewChannel($this->payload['data']['channel'])
                                    ->subscribe($this->event->getConnectionId(), $socketId, $this->payload['data'] ?? []);
    }

    /**
     * @see https://pusher.com/docs/channels/library_auth_reference/pusher-websockets-protocol#-pusher-unsubscribe-client-pusher-channels-
     */
    protected function unsubscribe(): Message
    {
        $this->channelManager->findOrNewChannel($this->payload['data']['channel'])
                             ->unsubscribe($this->event->getConnectionId());

        // Because of API Gateway limitations we are required to respond with something, so we do with a simple message
        return $this->buildPusherAcknowledgeMessage();
    }
}
