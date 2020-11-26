<?php

namespace Stayallive\ServerlessWebSockets\Connections\DynamoDB\Channels;

use Stayallive\ServerlessWebSockets\Messages\Message;
use Stayallive\ServerlessWebSockets\Connections\Channels\PrivateChannel as PrivateChannelInterface;

class PrivateChannel extends AbstractChannel implements PrivateChannelInterface
{
    public function subscribe(string $connectionId, string $socketId, array $payload): Message
    {
        if (!$this->verifySignature($socketId, $payload)) {
            return $this->buildPusherErrorMessage('Invalid Signature', 4009);
        }

        return parent::subscribe($connectionId, $socketId, $payload);
    }
}
