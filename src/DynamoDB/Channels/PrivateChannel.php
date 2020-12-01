<?php

namespace Stayallive\ServerlessWebSockets\DynamoDB\Channels;

use Stayallive\ServerlessWebSockets\Entities\Connection;
use Stayallive\ServerlessWebSockets\Exceptions\CouldNotSendSocketMessage;
use Stayallive\ServerlessWebSockets\Connections\Channels\PrivateChannel as PrivateChannelInterface;

class PrivateChannel extends AbstractChannel implements PrivateChannelInterface
{
    public function subscribe(Connection $connection, array $payload): void
    {
        if (!$this->verifySignature($connection->getSocketId(), $payload)) {
            try {
                $connection->sendMessage(
                    $this->buildPusherErrorMessage('Invalid Signature', 4009)
                );
            } catch (CouldNotSendSocketMessage $e) {
                // We have not done anything yet so we can safely ignore this
            }

            return;
        }

        parent::subscribe($connection, $payload);
    }
}
