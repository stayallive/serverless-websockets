<?php

namespace Stayallive\ServerlessWebSockets\Connections\Channels;

use Stayallive\ServerlessWebSockets\Messages\Message;

interface Channel
{
    public function subscribe(string $connectionId, string $socketId, array $payload): Message;

    public function unsubscribe(string $connectionId): void;


    public function hasConnections(): bool;

    public function connectionCount(): int;

    public function connectionIds(): array;


    public function broadcast(string $event, $data = null): void;

    public function broadcastToEveryoneExcept(string $event, $data = null, ?string $exceptConnectionId = null): void;
}
