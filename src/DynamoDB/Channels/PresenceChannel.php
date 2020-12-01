<?php

namespace Stayallive\ServerlessWebSockets\DynamoDB\Channels;

use AsyncAws\DynamoDb\Input\PutItemInput;
use AsyncAws\DynamoDb\Input\DeleteItemInput;
use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use Stayallive\ServerlessWebSockets\Entities\User;
use Stayallive\ServerlessWebSockets\Entities\Connection;
use Stayallive\ServerlessWebSockets\DynamoDB\Entities\User as DynamoDBUser;
use Stayallive\ServerlessWebSockets\Connections\Channels\PresenceChannel as PresenceChannelInterface;

class PresenceChannel extends PrivateChannel implements PresenceChannelInterface
{
    /** @var array<string, \Stayallive\ServerlessWebSockets\Entities\User> */
    private array $users = [];


    public function userIds(): array
    {
        return array_keys($this->users);
    }

    public function userCount(): int
    {
        return count($this->users);
    }

    public function findUserIdForConnectionId(string $connectionId): ?string
    {
        $connection = $this->connections[$connectionId] ?? null;

        if ($connection === null) {
            return null;
        }

        return $connection->getUserId();
    }


    protected function addConnection(Connection $connection, array $payload): void
    {
        $user = $this->getUserInfoFromPayload($payload);

        $connection->setUserId($user->getId());

        $alreadyConnected = $this->userHasOpenConnections($user->getId(), $connection->getConnectionId());

        parent::addConnection($connection, $payload);

        if (!$alreadyConnected) {
            $this->db->putItem(new PutItemInput([
                'TableName' => app_db_table(),
                'Item'      => [
                    'PK'        => new AttributeValue(['S' => "CHANNEL#{$this->name}"]),
                    'SK'        => new AttributeValue(['S' => "USER#{$user->getId()}"]),
                    'type'      => new AttributeValue(['S' => 'User']),
                    'user-id'   => new AttributeValue(['S' => $user->getId()]),
                    'user-info' => new AttributeValue(['S' => json_encode($user->getInfo())]),
                ],
            ]));

            $this->users[$user->getId()] = $user;

            $this->broadcastToEveryoneExcept(
                'pusher_internal:member_added',
                ['user_id' => $user->getId(), 'user_info' => $user->getInfo()],
                $connection->getConnectionId()
            );

            queue_webhook('member_added', ['channel' => $this->name, 'user_id' => $user->getId()]);
        }
    }

    protected function hydrateFromDynamoDBRecords(array $records): void
    {
        parent::hydrateFromDynamoDBRecords($records);

        $this->users = collect($records)
            ->filter(fn (array $record) => $record['type']->getS() === 'User')
            ->mapWithKeys(function (array $record) {
                $user = DynamoDBUser::fromDynamoDBRecord($record);

                return [$user->getId() => $user];
            })->all();
    }

    protected function removeConnectionForConnectionId(string $connectionId): void
    {
        $userId = $this->findUserIdForConnectionId($connectionId);

        if ($userId !== null && !$this->userHasOpenConnections($userId, $connectionId)) {
            $this->db->deleteItem(new DeleteItemInput([
                'TableName' => app_db_table(),
                'Key'       => [
                    'PK' => new AttributeValue(['S' => "CHANNEL#{$this->name}"]),
                    'SK' => new AttributeValue(['S' => "USER#{$userId}"]),
                ],
            ]));

            unset($this->users[$userId]);

            $this->broadcastToEveryoneExcept(
                'pusher_internal:member_removed',
                ['user_id' => $userId],
                $connectionId
            );

            queue_webhook('member_removed', ['channel' => $this->name, 'user_id' => $userId]);
        }

        parent::removeConnectionForConnectionId($connectionId);
    }

    protected function respondWithSubscriptionSucceeded(Connection $connection): void
    {
        $connection->sendMessage(
            $this->buildPusherChannelMessage(
                $this->name,
                'pusher_internal:subscription_succeeded',
                $this->getChannelData()
            )
        );
    }


    protected function getChannelData(): array
    {
        return [
            'presence' => [
                'ids'   => array_keys($this->users),
                'hash'  => array_map(static fn (User $user) => $user->getInfo(), $this->users),
                'count' => count($this->users),
            ],
        ];
    }

    protected function userHasOpenConnections(string $userId, ?string $exceptForConnectionId = null): bool
    {
        foreach ($this->connections as $connectionId => $userData) {
            if ($exceptForConnectionId !== null && $connectionId === $exceptForConnectionId) {
                continue;
            }

            if ($userData->getUserId() === $userId) {
                return true;
            }
        }

        return false;
    }

    protected function getUserInfoFromPayload(array $payload): User
    {
        $channelData = json_decode($payload['channel_data'], true);

        return new User(
            (string)$channelData['user_id'],
            $channelData['user_info'] ?? []
        );
    }
}
