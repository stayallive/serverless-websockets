<?php

namespace Stayallive\ServerlessWebSockets\DynamoDB\Entities;

use RuntimeException;
use Stayallive\ServerlessWebSockets\Entities\User as UserEntity;

class User extends UserEntity
{
    public static function fromDynamoDBRecord(array $record): User
    {
        if (empty($record) || $record['type']->getS() !== 'User') {
            throw new RuntimeException('Missing data to reconstruct user from DynamoDB record.');
        }

        return new self(
            $record['user-id']->getS(),
            json_decode($record['user-info']->getS(), true),
        );
    }
}
