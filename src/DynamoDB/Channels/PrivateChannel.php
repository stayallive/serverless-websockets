<?php

namespace Stayallive\ServerlessWebSockets\DynamoDB\Channels;

use Stayallive\ServerlessWebSockets\Entities\Connection;
use Stayallive\ServerlessWebSockets\Connections\Channels\PrivateChannel as PrivateChannelInterface;

class PrivateChannel extends AbstractChannel implements PrivateChannelInterface
{
    public function subscribe(Connection $connection, array $payload): void
    {
        if (!$this->verifySignature($connection->getSocketId(), $payload)) {
            $connection->sendMessage(
                $this->buildPusherErrorMessage('Invalid Signature', 4009)
            );
        }

        parent::subscribe($connection, $payload);
    }
}
