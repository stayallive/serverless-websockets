<?php

namespace Stayallive\ServerlessWebSockets\Connections;

use Bref\Event\ApiGateway\WebsocketEvent;
use Stayallive\ServerlessWebSockets\Connections\Channels\AbstractChannel;

interface ConnectionManager
{
    public function connect(WebsocketEvent $event): void;

    public function disconnect(WebsocketEvent $event): void;


    public function findSocketIdForConnection(string $connectionId): ?string;


    public function channels(): array;

    public function findChannel(string $channelName): ?AbstractChannel;

    public function findOrCreateChannel(string $channelName): AbstractChannel;
}
