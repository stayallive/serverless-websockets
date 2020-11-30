<?php

namespace Stayallive\ServerlessWebSockets\Connections\Channels;

use Stayallive\ServerlessWebSockets\Entities\Connection;

interface Channel
{
    public function subscribe(Connection $connection, array $payload): void;

    public function unsubscribe(string $connectionId): void;


    public function connectionIds(): array;

    public function hasConnections(): bool;

    public function connectionCount(): int;


    public function broadcast(string $event, $data = null): void;

    public function broadcastToEveryoneExcept(string $event, $data = null, ?string $exceptConnectionId = null): void;
}
