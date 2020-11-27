<?php

namespace Stayallive\ServerlessWebSockets\Connections\Channels;

interface PresenceChannel
{
    public function userIds(): array;

    public function userCount(): int;

    public function findUserIdForConnectionId(string $connectionId): ?string;
}
