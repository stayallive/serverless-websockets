<?php

namespace Stayallive\ServerlessWebSockets\Exceptions;

use Throwable;
use RuntimeException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;

class CouldNotSendSocketMessage extends RuntimeException
{
    public const REASON_UNKNOWN      = 'unknown';
    public const REASON_DISCONNECTED = 'disconnected';
    public const REASON_RATE_LIMITED = 'rate_limited';

    private string $reason;

    public function __construct($reason = self::REASON_UNKNOWN, Throwable $previous)
    {
        parent::__construct('Could not send message to socket', 0, $previous);

        $this->reason = $reason;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public static function fromException(ClientExceptionInterface $exception): self
    {
        $reasons = [
            410 => self::REASON_DISCONNECTED,
            429 => self::REASON_RATE_LIMITED,
        ];

        $statusCode = $exception->getResponse()->getStatusCode();

        return new self($reasons[$statusCode] ?? self::REASON_UNKNOWN, $exception);
    }
}
