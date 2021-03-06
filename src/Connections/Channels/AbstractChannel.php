<?php

namespace Stayallive\ServerlessWebSockets\Connections\Channels;

use Illuminate\Support\Str;
use Stayallive\ServerlessWebSockets\Entities\Connection;

abstract class AbstractChannel implements Channel
{
    protected string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }


    abstract public function subscribe(Connection $connection, array $payload): void;

    abstract public function unsubscribe(string $connectionId): void;


    abstract public function socketIds(): array;

    abstract public function connectionIds(): array;

    abstract public function hasConnections(): bool;

    abstract public function connectionCount(): int;


    abstract public function broadcast(string $event, $data = null): void;

    abstract public function broadcastToEveryoneExcept(string $event, $data = null, ?string $exceptConnectionId = null): void;


    protected function verifySignature(string $socketId, array $payload): bool
    {
        $signature = "{$socketId}:{$this->name}";

        if (isset($payload['channel_data'])) {
            $signature .= ":{$payload['channel_data']}";
        }

        return Str::after($payload['auth'] ?? '', ':') === hash_hmac('sha256', $signature, app_secret());
    }
}
