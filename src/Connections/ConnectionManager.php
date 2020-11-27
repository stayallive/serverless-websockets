<?php

namespace Stayallive\ServerlessWebSockets\Connections;

use Illuminate\Support\Str;
use Bref\Event\ApiGateway\WebsocketEvent;
use Stayallive\ServerlessWebSockets\Connections\Channels\AbstractChannel;

abstract class ConnectionManager
{
    abstract public function connect(WebsocketEvent $event): void;

    abstract public function disconnect(WebsocketEvent $event): void;


    abstract public function findSocketIdForConnectionId(string $connectionId): ?string;


    abstract public function channels(): array;

    abstract public function findChannel(string $channelName): ?AbstractChannel;

    abstract public function findOrNewChannel(string $channelName): AbstractChannel;


    public function isAuthenticatedChannel(string $channelName): bool
    {
        return Str::startsWith($channelName, ['private-', 'presence-']);
    }
}
