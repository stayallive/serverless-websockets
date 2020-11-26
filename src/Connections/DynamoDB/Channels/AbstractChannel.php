<?php

namespace Stayallive\ServerlessWebSockets\Connections\DynamoDB\Channels;

use AsyncAws\DynamoDb\DynamoDbClient;
use AsyncAws\DynamoDb\Input\DeleteItemInput;
use AsyncAws\DynamoDb\Input\UpdateItemInput;
use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use Stayallive\ServerlessWebSockets\Messages\BuildsPusherMessages;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Stayallive\ServerlessWebSockets\Connections\DynamoDB\ConnectionManager;
use Stayallive\ServerlessWebSockets\Connections\Channels\AbstractChannel as BaseChannel;

abstract class AbstractChannel extends BaseChannel
{
    use BuildsPusherMessages;

    protected DynamoDbClient $db;

    protected ?array $data;

    protected bool $updateConnectionPool;

    public function __construct(string $name, DynamoDbClient $db, ?array $data = null)
    {
        parent::__construct($name);

        $this->db                   = $db;
        $this->data                 = $data;
        $this->updateConnectionPool = true;
    }


    public function subscribe(string $connectionId, string $socketId, array $payload): array
    {
        $this->createEmptyChannelIfNeeded();

        $this->subscribeOnConnectionPool($connectionId);

        $this->addConnectionForConnectionId($connectionId, $socketId, $payload);

        return $this->buildPusherChannelMessage($this->name, 'pusher_internal:subscription_succeeded');
    }

    public function unsubscribe(string $connectionId): array
    {
        if ($this->exists()) {
            $this->unsubscribeOnConnectionPool($connectionId);

            $this->removeConnectionForConnectionId($connectionId);

            $this->cleanupChannelIfEmpty();
        }

        return $this->buildPusherAcknowledgeMessage();
    }


    public function connectionIds(): array
    {
        return array_keys($this->connections());
    }

    public function hasConnections(): bool
    {
        return !empty($this->connections());
    }

    public function connectionCount(): int
    {
        return count($this->connections());
    }


    public function broadcast(string $event, ?array $data = null): void
    {
        $this->broadcastToEveryoneExcept($event, $data);
    }

    public function broadcastToEveryoneExcept(string $event, ?array $data = null, ?string $exceptConnectionId = null): void
    {
        $message = [
            'event'   => $event,
            'channel' => $this->name,
        ];

        if (!empty($data)) {
            $message['data'] = $data;
        }

        $message = json_encode($message);

        foreach ($this->connectionIds() as $connectionId) {
            if ($exceptConnectionId !== null && $exceptConnectionId === $connectionId) {
                continue;
            }

            try {
                socket_client()->message($connectionId, $message);
            } catch (ClientExceptionInterface $e) {
                // Handle disconnected clients that were not cleaned up correctly
                if ($e->getResponse()->getStatusCode() === 410) {
                    $this->unsubscribe($connectionId);

                    continue;
                }
            }
        }
    }


    public function updateConnectionPool(bool $do = true): self
    {
        $this->updateConnectionPool = $do;

        return $this;
    }

    public function subscribeOnConnectionPool(string $connectionId): void
    {
        if (!$this->updateConnectionPool) {
            return;
        }

        $this->db->updateItem(new UpdateItemInput([
            'TableName'                 => ConnectionManager::CONNECTION_POOL_TABLE,
            'Key'                       => [
                'connection-id' => new AttributeValue(['S' => $connectionId]),
            ],
            'ReturnValues'              => 'NONE',
            'UpdateExpression'          => 'ADD #channels :channel',
            'ExpressionAttributeNames'  => [
                '#channels' => 'channels',
            ],
            'ExpressionAttributeValues' => [
                ':channel' => new AttributeValue(['SS' => [$this->name]]),
            ],
        ]))->resolve();
    }

    public function unsubscribeOnConnectionPool(string $connectionId): void
    {
        if (!$this->updateConnectionPool) {
            return;
        }

        $this->db->updateItem(new UpdateItemInput([
            'TableName'                 => ConnectionManager::CONNECTION_POOL_TABLE,
            'Key'                       => [
                'connection-id' => new AttributeValue(['S' => $connectionId]),
            ],
            'ReturnValues'              => 'NONE',
            'UpdateExpression'          => 'DELETE #channels :channel',
            'ExpressionAttributeNames'  => [
                '#channels' => 'channels',
            ],
            'ExpressionAttributeValues' => [
                ':channel' => new AttributeValue(['SS' => [$this->name]]),
            ],
        ]))->resolve();
    }


    protected function exists(): bool
    {
        return $this->data !== null;
    }

    protected function connections(): array
    {
        if (!$this->exists()) {
            return [];
        }

        $connections = $this->data['connections'] ?? [];

        if ($connections instanceof AttributeValue) {
            $connections = $connections->getM();
        }

        return $connections;
    }

    protected function cleanupChannelIfEmpty(): void
    {
        if (!$this->exists() || $this->hasConnections()) {
            return;
        }

        $request = $this->db->deleteItem(new DeleteItemInput([
            'TableName'                 => ConnectionManager::CHANNELS_TABLE,
            'Key'                       => [
                'channel-id' => new AttributeValue(['S' => $this->name]),
            ],
            'ConditionExpression'       => 'size(#connections) <= :empty',
            'ExpressionAttributeNames'  => [
                '#connections' => 'connections',
            ],
            'ExpressionAttributeValues' => [
                ':empty' => new AttributeValue(['N' => '0']),
            ],
        ]));

        try {
            $request->resolve();
        } catch (HttpException $e) {
            // This is probably that the condition failed, safe to ignore
            throw $e;
        }

        $this->data = null;
    }

    protected function createEmptyChannelIfNeeded(): void
    {
        if ($this->exists()) {
            return;
        }

        $result = $this->db->updateItem(new UpdateItemInput([
            'TableName'                 => ConnectionManager::CHANNELS_TABLE,
            'Key'                       => [
                'channel-id' => new AttributeValue(['S' => $this->name]),
            ],
            'ReturnValues'              => 'ALL_NEW',
            'UpdateExpression'          => 'SET #connections = if_not_exists(#connections, :connections), #users = if_not_exists(#users, :users), #type = if_not_exists(#type, :type)',
            'ExpressionAttributeNames'  => [
                '#type'        => 'type',
                '#users'       => 'users',
                '#connections' => 'connections',
            ],
            'ExpressionAttributeValues' => [
                ':type'        => new AttributeValue(['S' => class_basename($this)]),
                ':users'       => new AttributeValue(['M' => []]),
                ':connections' => new AttributeValue(['M' => []]),
            ],
        ]));

        $this->data = $result->getAttributes();
    }


    protected function addConnectionForConnectionId(string $connectionId, string $socketId, array $payload): void
    {
        $result = $this->db->updateItem(new UpdateItemInput([
            'TableName'                 => ConnectionManager::CHANNELS_TABLE,
            'Key'                       => [
                'channel-id' => new AttributeValue(['S' => $this->name]),
            ],
            'ReturnValues'              => 'ALL_NEW',
            'UpdateExpression'          => 'SET #connections.#connection = :user',
            'ExpressionAttributeNames'  => [
                '#connection'  => $connectionId,
                '#connections' => 'connections',
            ],
            'ExpressionAttributeValues' => [
                ':user' => new AttributeValue([
                    'M' => [
                        'socket-id' => new AttributeValue(['S' => $socketId]),
                    ],
                ]),
            ],
        ]));

        $this->data = array_merge($this->data, $result->getAttributes());
    }

    protected function removeConnectionForConnectionId(string $connectionId): void
    {
        $result = $this->db->updateItem(new UpdateItemInput([
            'TableName'                => ConnectionManager::CHANNELS_TABLE,
            'Key'                      => [
                'channel-id' => new AttributeValue(['S' => $this->name]),
            ],
            'ReturnValues'             => 'ALL_NEW',
            'UpdateExpression'         => 'REMOVE #connections.#socket',
            'ExpressionAttributeNames' => [
                '#socket'      => $connectionId,
                '#connections' => 'connections',
            ],
        ]));

        $this->data = array_merge($this->data, $result->getAttributes());
    }
}
