<?php

namespace Stayallive\ServerlessWebSockets\Connections;

use Bref\Event\ApiGateway\WebsocketEvent;

interface ConnectionManager
{
    public function connect(WebsocketEvent $event): void;

    public function disconnect(WebsocketEvent $event): void;

    public function findChannel(string $channelName): ?Channel;

    public function findOrCreateChannel(string $channelName): Channel;
}
