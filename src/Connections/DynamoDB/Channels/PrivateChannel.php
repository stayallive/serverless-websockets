<?php

namespace Stayallive\ServerlessWebSockets\Connections\DynamoDB\Channels;

use Stayallive\ServerlessWebSockets\Connections\Channels\PrivateChannel as PrivateChannelInterface;

class PrivateChannel extends AbstractChannel implements PrivateChannelInterface
{
    public function subscribe(string $connectionId, string $socketId, array $payload): void
    {
        if (!$this->verifySignature($socketId, $payload)) {
            $this->sendMessageToConnection(
                $connectionId,
                $this->buildPusherErrorMessage('Invalid Signature', 4009)
            );
        }

        parent::subscribe($connectionId, $socketId, $payload);
    }
}
