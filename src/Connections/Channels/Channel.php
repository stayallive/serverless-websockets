<?php

namespace Stayallive\ServerlessWebSockets\Connections\Channels;

interface Channel
{
    public function subscribe(string $connectionId, string $socketId, array $payload): array;

    public function unsubscribe(string $connectionId): array;


    public function hasConnections(): bool;

    public function connectionCount(): int;

    public function connectionIds(): array;


    public function broadcast(string $event, ?array $data = null): void;

    public function broadcastToEveryoneExcept(string $event, ?array $data = null, ?string $exceptConnectionId = null): void;
}
