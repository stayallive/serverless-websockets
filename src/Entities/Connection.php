<?php

namespace Stayallive\ServerlessWebSockets\Entities;

use Stayallive\ServerlessWebSockets\Messages\Message;
use Stayallive\ServerlessWebSockets\Messages\SendsMessages;

class Connection extends Entity
{
    use SendsMessages;

    protected string  $connectionId;
    protected string  $socketId;
    protected int     $connectTime;
    protected ?string $userId;

    public function __construct(string $connectionId, string $socketId, int $connectTime, string $userId = null)
    {
        $this->connectionId = $connectionId;
        $this->socketId     = $socketId;
        $this->connectTime  = $connectTime;
        $this->userId       = empty($userId) ? null : $userId;
    }

    public function getConnectionId(): string
    {
        return $this->connectionId;
    }

    public function getSocketId(): string
    {
        return $this->socketId;
    }

    public function getConnectTime(): int
    {
        return $this->connectTime;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        $this->userId = empty($userId) ? null : $userId;
    }

    public function sendMessage(Message $message): void
    {
        $this->sendMessageToConnection($this->getConnectionId(), $message);
    }

    public static function createForConnection(string $connectionId, string $userId = null): Connection
    {
        return new self($connectionId, self::generateSocketId(), time(), $userId);
    }

    protected static function generateSocketId(): string
    {
        return sprintf('%d.%d', random_int(1, 1000000000), random_int(1, 1000000000));
    }
}
