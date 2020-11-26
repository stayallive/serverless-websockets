<?php

namespace Stayallive\ServerlessWebSockets\Connections\DynamoDB\Channels;

use AsyncAws\DynamoDb\Input\UpdateItemInput;
use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use Stayallive\ServerlessWebSockets\Messages\Message;
use Stayallive\ServerlessWebSockets\Connections\DynamoDB\ConnectionManager;
use Stayallive\ServerlessWebSockets\Connections\Channels\PresenceChannel as PresenceChannelInterface;

class PresenceChannel extends PrivateChannel implements PresenceChannelInterface
{
    public function subscribe(string $connectionId, string $socketId, array $payload): Message
    {
        parent::subscribe($connectionId, $socketId, $payload);

        return $this->buildPusherChannelMessage($this->name, 'pusher_internal:subscription_succeeded', $this->getChannelData());
    }


    public function userIds(): array
    {
        if (!$this->exists()) {
            return [];
        }

        return array_keys($this->data['users']->getM());
    }

    public function userCount(): int
    {
        if (!$this->exists()) {
            return 0;
        }

        return count($this->data['users']->getM());
    }


    protected function addConnectionForConnectionId(string $connectionId, string $socketId, array $payload): void
    {
        [$userId, $userInfo] = $this->getUserInfoFromPayload($payload);

        $alreadyConnected = $this->userHasOpenConnections($userId, $connectionId);

        $result = $this->db->updateItem(new UpdateItemInput([
            'TableName'                 => ConnectionManager::CHANNELS_TABLE,
            'Key'                       => [
                'channel-id' => new AttributeValue(['S' => $this->name]),
            ],
            'ReturnValues'              => 'ALL_NEW',
            'UpdateExpression'          => 'SET #connections.#connection = :user, #users.#user = :info',
            'ExpressionAttributeNames'  => [
                '#user'        => $userId,
                '#users'       => 'users',
                '#connection'  => $connectionId,
                '#connections' => 'connections',
            ],
            'ExpressionAttributeValues' => [
                ':user' => new AttributeValue([
                    'M' => [
                        'user-id'   => new AttributeValue(['S' => $userId]),
                        'socket-id' => new AttributeValue(['S' => $socketId]),
                    ],
                ]),
                ':info' => new AttributeValue(['S' => json_encode($userInfo)]),
            ],
        ]));

        $this->data = array_merge($this->data, $result->getAttributes());

        if (!$alreadyConnected) {
            $this->broadcastToEveryoneExcept(
                'pusher_internal:member_added',
                ['user_id' => $userId, 'user_info' => $userInfo],
                $connectionId
            );
        }
    }

    protected function removeConnectionForConnectionId(string $connectionId): void
    {
        $userId = $this->userIdForConnectionId($connectionId);

        if ($userId !== null && !$this->userHasOpenConnections($userId, $connectionId)) {
            $this->broadcastToEveryoneExcept(
                'pusher_internal:member_removed',
                ['user_id' => $userId],
                $connectionId
            );

            $result = $this->db->updateItem(new UpdateItemInput([
                'TableName'                => ConnectionManager::CHANNELS_TABLE,
                'Key'                      => [
                    'channel-id' => new AttributeValue(['S' => $this->name]),
                ],
                'ReturnValues'             => 'ALL_NEW',
                'UpdateExpression'         => 'REMOVE #connections.#socket, #users.#user',
                'ExpressionAttributeNames' => [
                    '#user'        => $userId,
                    '#users'       => 'users',
                    '#socket'      => $connectionId,
                    '#connections' => 'connections',
                ],
            ]));

            $this->data = array_merge($this->data, $result->getAttributes());

            return;
        }

        parent::removeConnectionForConnectionId($connectionId);
    }


    protected function getChannelData(): array
    {
        return [
            'presence' => [
                'ids'   => array_keys($this->data['users']->getM()),
                'hash'  => array_map(static fn (AttributeValue $userInfo) => json_decode($userInfo->getS(), true), $this->data['users']->getM()),
                'count' => count($this->data['users']->getM()),
            ],
        ];
    }

    protected function userIdForConnectionId($connectionId): ?string
    {
        /** @var \AsyncAws\DynamoDb\ValueObject\AttributeValue $userData */
        $userData = $this->data['connections']->getM()[$connectionId] ?? null;

        if ($userData === null) {
            return null;
        }

        return $userData->getM()['user-id']->getS();
    }

    protected function userHasOpenConnections(string $userId, ?string $exceptForConnectionId = null): bool
    {
        /** @var \AsyncAws\DynamoDb\ValueObject\AttributeValue $userData */
        foreach ($this->data['connections']->getM() as $connectionId => $userData) {
            if ($exceptForConnectionId !== null && $connectionId === $exceptForConnectionId) {
                continue;
            }

            if ($userData->getM()['user-id']->getS() === $userId) {
                return true;
            }
        }

        return false;
    }

    protected function getUserInfoFromPayload(array $payload): array
    {
        if (empty($payload['channel_data'])) {
            return [];
        }

        $channelData = json_decode($payload['channel_data'], true);

        return [
            (string)$channelData['user_id'],
            $channelData['user_info'] ?? [],
        ];
    }
}
