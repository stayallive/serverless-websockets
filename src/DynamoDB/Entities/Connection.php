<?php

namespace Stayallive\ServerlessWebSockets\DynamoDB\Entities;

use RuntimeException;
use Stayallive\ServerlessWebSockets\Entities\Connection as ConnectionEntity;

class Connection extends ConnectionEntity
{
    public static function fromDynamoDBRecord(array $record): Connection
    {
        if (empty($record) || $record['type']->getS() !== 'Connection') {
            throw new RuntimeException('Missing data to reconstruct connection from DynamoDB record.');
        }

        return new self(
            $record['connection-id']->getS(),
            $record['socket-id']->getS(),
            (int)$record['connect-time']->getN(),
            isset($record['user-id']) ? $record['user-id']->getS() : null
        );
    }
}
