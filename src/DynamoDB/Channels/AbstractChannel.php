<?php

namespace Stayallive\ServerlessWebSockets\DynamoDB\Channels;

use AsyncAws\DynamoDb\DynamoDbClient;
use AsyncAws\DynamoDb\Input\PutItemInput;
use AsyncAws\DynamoDb\Input\DeleteItemInput;
use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use Stayallive\ServerlessWebSockets\Entities\Connection;
use Stayallive\ServerlessWebSockets\Messages\PusherMessage;
use Stayallive\ServerlessWebSockets\Messages\SendsPusherMessages;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Stayallive\ServerlessWebSockets\DynamoDB\Entities\Connection as DynamoDBConnection;
use Stayallive\ServerlessWebSockets\Connections\Channels\AbstractChannel as BaseChannel;

abstract class AbstractChannel extends BaseChannel
{
    use SendsPusherMessages;

    protected DynamoDbClient $db;

    /** @var array<string, \Stayallive\ServerlessWebSockets\Entities\Connection> */
    protected array $connections;

    public function __construct(string $name, DynamoDbClient $db, array $data = [])
    {
        parent::__construct($name);

        $this->db = $db;

        $this->hydrateFromDynamoDBRecords($data);
    }


    public function subscribe(Connection $connection, array $payload): void
    {
        $hadConnectionBeforeSubscription = $this->hasConnections();

        $this->addConnection($connection, $payload);

        $this->respondWithSubscriptionSucceeded($connection);

        if (!$hadConnectionBeforeSubscription) {
            queue_webhook('channel_occupied', ['channel' => $this->name]);
        }
    }

    public function unsubscribe(string $connectionId): void
    {
        $this->removeConnectionForConnectionId($connectionId);
    }


    public function socketIds(): array
    {
        return array_map(static fn (Connection $connection) => $connection->getSocketId(), $this->connections);
    }

    public function connectionIds(): array
    {
        return array_keys($this->connections);
    }

    public function hasConnections(): bool
    {
        return !empty($this->connections);
    }

    public function connectionCount(): int
    {
        return count($this->connections);
    }


    public function broadcast(string $event, $data = null): void
    {
        $this->broadcastToEveryoneExcept($event, $data);
    }

    public function broadcastToEveryoneExcept(string $event, $data = null, ?string $exceptConnectionId = null): void
    {
        $message = new PusherMessage($event);

        $message->toChannel($this->name);

        if ($data !== null) {
            if (is_array($data)) {
                $data = json_encode($data);
            }

            $message->withData($data);
        }

        foreach ($this->connectionIds() as $connectionId) {
            if ($exceptConnectionId !== null && $exceptConnectionId === $connectionId) {
                continue;
            }

            try {
                $this->sendMessageToConnection($connectionId, $message);
            } catch (ClientExceptionInterface $e) {
                // Handle disconnected clients that were not cleaned up correctly
                if ($e->getResponse()->getStatusCode() === 410) {
                    echo "Found a stale connection in channel:{$this->name} connection:{$connectionId}, unsubscribing." . PHP_EOL;

                    $this->unsubscribe($connectionId);

                    continue;
                }
            }
        }
    }


    protected function addConnection(Connection $connection, array $payload): void
    {
        $this->db->putItem(new PutItemInput([
            'TableName' => app_db_table(),
            'Item'      => [
                'PK'            => new AttributeValue(['S' => "CHANNEL#{$this->name}"]),
                'SK'            => new AttributeValue(['S' => "CONNECTION#{$connection->getConnectionId()}"]),
                'GSI1PK'        => new AttributeValue(['S' => "CONNECTION#{$connection->getConnectionId()}"]),
                'GSI1SK'        => new AttributeValue(['S' => "CHANNEL#{$this->name}"]),
                'type'          => new AttributeValue(['S' => 'Connection']),
                'channel'       => new AttributeValue(['S' => $this->name]),
                'user-id'       => new AttributeValue(['S' => $connection->getUserId() ?? '']),
                'socket-id'     => new AttributeValue(['S' => $connection->getSocketId()]),
                'connect-time'  => new AttributeValue(['N' => (string)$connection->getConnectTime()]),
                'connection-id' => new AttributeValue(['S' => $connection->getConnectionId()]),
            ],
        ]));

        $this->connections[$connection->getConnectionId()] = $connection;
    }

    protected function hydrateFromDynamoDBRecords(array $records): void
    {
        $this->connections = collect($records)
            ->filter(fn (array $record) => $record['type']->getS() === 'Connection')
            ->mapWithKeys(function (array $record) {
                $connection = DynamoDBConnection::fromDynamoDBRecord($record);

                return [$connection->getConnectionId() => $connection];
            })->all();
    }

    protected function removeConnectionForConnectionId(string $connectionId): void
    {
        if (!isset($this->connections[$connectionId])) {
            return;
        }

        $this->db->deleteItem(new DeleteItemInput([
            'TableName' => app_db_table(),
            'Key'       => [
                'PK' => new AttributeValue(['S' => "CHANNEL#{$this->name}"]),
                'SK' => new AttributeValue(['S' => "CONNECTION#{$connectionId}"]),
            ],
        ]));

        unset($this->connections[$connectionId]);

        if (!$this->hasConnections()) {
            queue_webhook('channel_vacated', ['channel' => $this->name]);
        }
    }

    protected function respondWithSubscriptionSucceeded(Connection $connection): void
    {
        $connection->sendMessage(
            $this->buildPusherChannelMessage($this->name, 'pusher_internal:subscription_succeeded')
        );
    }
}
