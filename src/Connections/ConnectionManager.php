<?php

namespace Stayallive\ServerlessWebSockets\Connections;

use Illuminate\Support\Str;
use Bref\Event\ApiGateway\WebsocketEvent;
use Stayallive\ServerlessWebSockets\Entities\Connection;
use Stayallive\ServerlessWebSockets\Connections\Channels\AbstractChannel;

abstract class ConnectionManager
{
    abstract public function connect(WebsocketEvent $event): void;

    public function disconnect(WebsocketEvent $event): void
    {
        $this->disconnectConnectionId($event->getConnectionId());
    }

    abstract public function disconnectConnectionId(string $connectionId): void;


    abstract public function findConnection(string $connectionId): ?Connection;

    abstract public function findStaleConnectionIds(int $timeout = 86400): array;


    abstract public function channel(string $channelName): AbstractChannel;

    abstract public function channels(): array;


    public function isAuthenticatedChannel(string $channelName): bool
    {
        return Str::startsWith($channelName, ['private-', 'presence-']);
    }
}
