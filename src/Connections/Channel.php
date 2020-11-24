<?php

namespace Stayallive\ServerlessWebSockets\Connections;

abstract class Channel
{
    protected string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }


    abstract public function subscribe(string $connectionId, array $payload): array;

    abstract public function unsubscribe(string $connectionId): array;


    abstract public function hasConnections(): bool;

    abstract public function connectionCount(): int;

    abstract public function connectedSocketIds(): array;


    abstract public function broadcast(string $event, ?array $data = null): void;

    abstract public function broadcastToEveryoneExcept(string $event, ?array $data = null, ?string $exceptSocketId = null): void;
}
