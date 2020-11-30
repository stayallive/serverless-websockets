<?php

namespace Stayallive\ServerlessWebSockets\DynamoDB;

use Illuminate\Support\Str;
use AsyncAws\DynamoDb\DynamoDbClient;
use AsyncAws\DynamoDb\Input\ScanInput;
use Illuminate\Support\LazyCollection;
use AsyncAws\DynamoDb\Input\QueryInput;
use AsyncAws\DynamoDb\Input\GetItemInput;
use AsyncAws\DynamoDb\Input\PutItemInput;
use Bref\Event\ApiGateway\WebsocketEvent;
use AsyncAws\DynamoDb\Input\DeleteItemInput;
use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use Stayallive\ServerlessWebSockets\DynamoDB\Entities\Connection;
use Stayallive\ServerlessWebSockets\DynamoDB\Channels\PublicChannel;
use Stayallive\ServerlessWebSockets\DynamoDB\Channels\PrivateChannel;
use Stayallive\ServerlessWebSockets\DynamoDB\Channels\PresenceChannel;
use Stayallive\ServerlessWebSockets\Connections\Channels\AbstractChannel;
use Stayallive\ServerlessWebSockets\Connections\ConnectionManager as BaseConnectionManager;

class ConnectionManager extends BaseConnectionManager
{
    private DynamoDbClient $db;

    public function __construct(DynamoDbClient $db)
    {
        $this->db = $db;
    }


    public function connect(WebsocketEvent $event): void
    {
        $connection = Connection::createForConnection($event->getConnectionId());

        $this->db->putItem(new PutItemInput([
            'TableName' => app_db_table(),
            'Item'      => [
                'PK'            => new AttributeValue(['S' => 'CONNECTIONS']),
                'SK'            => new AttributeValue(['S' => "CONNECTION#{$connection->getConnectionId()}"]),
                'type'          => new AttributeValue(['S' => 'Connection']),
                'socket-id'     => new AttributeValue(['S' => $connection->getSocketId()]),
                'connect-time'  => new AttributeValue(['N' => (string)$connection->getConnectTime()]),
                'connection-id' => new AttributeValue(['S' => $connection->getConnectionId()]),
            ],
        ]));
    }

    public function disconnectConnectionId(string $connectionId): void
    {
        $this->removeFromAllChannels($connectionId);

        $this->db->deleteItem(new DeleteItemInput([
            'TableName' => app_db_table(),
            'Key'       => [
                'PK' => new AttributeValue(['S' => 'CONNECTIONS']),
                'SK' => new AttributeValue(['S' => "CONNECTION#{$connectionId}"]),
            ],
        ]));
    }


    public function findConnection(string $connectionId): ?Connection
    {
        $connectionData = $this->db->getItem(new GetItemInput([
            'TableName'      => app_db_table(),
            'Key'            => [
                'PK' => new AttributeValue(['S' => 'CONNECTIONS']),
                'SK' => new AttributeValue(['S' => "CONNECTION#{$connectionId}"]),
            ],
            'ConsistentRead' => true,
        ]))->getItem();

        if (empty($connectionData)) {
            return null;
        }

        return Connection::fromDynamoDBRecord($connectionData);
    }

    public function findStaleConnectionIds(int $timeout = 86400): array
    {
        $request = $this->db->query(new QueryInput([
            'TableName'                 => app_db_table(),
            'FilterExpression'          => '#time <= :timeout',
            'KeyConditionExpression'    => 'PK = :pk',
            'ExpressionAttributeNames'  => [
                '#time' => 'connect-time',
            ],
            'ExpressionAttributeValues' => [
                ':pk'      => new AttributeValue(['S' => 'CONNECTIONS']),
                ':timeout' => new AttributeValue(['N' => (string)(time() - $timeout)]),
            ],
        ]));

        return array_map(static function (array $item): string {
            return $item['connection-id']->getS();
        }, iterator_to_array($request->getItems()));
    }


    public function channel(string $channelName): AbstractChannel
    {
        $request = $this->db->query(new QueryInput([
            'TableName'                 => app_db_table(),
            'KeyConditionExpression'    => 'PK = :pk',
            'ExpressionAttributeValues' => [
                ':pk' => new AttributeValue(['S' => "CHANNEL#{$channelName}"]),
            ],
        ]));

        $channelClass = $this->determineChannelClass($channelName);

        return new $channelClass($channelName, $this->db, iterator_to_array($request->getItems()));
    }

    public function channels(): array
    {
        $request = $this->db->scan(new ScanInput([
            'TableName'                 => app_db_table(),
            'FilterExpression'          => 'begins_with(PK, :pk)',
            'ExpressionAttributeValues' => [
                ':pk' => new AttributeValue(['S' => 'CHANNEL#']),
            ],
        ]));

        $channels = [];

        foreach ($request->getItems() as $channelRecord) {
            $channelName = Str::after($channelRecord['PK']->getS(), 'CHANNEL#');

            if (!isset($channels[$channelName])) {
                $channels[$channelName] = [];
            }

            $channels[$channelName][$channelRecord];
        }

        return collect($channels)->map(function (array $channelRecords, string $channelName) {
            $channelClass = $this->determineChannelClass($channelName);

            return new $channelClass($channelName, $this->db, $channelRecords);
        })->values()->all();
    }


    protected function removeFromAllChannels(string $connectionId): void
    {
        $request = $this->db->query(new QueryInput([
            'TableName'                 => app_db_table(),
            'IndexName'                 => 'GSI1',
            'KeyConditionExpression'    => 'GSI1PK = :pk',
            'ExpressionAttributeValues' => [
                ':pk' => new AttributeValue(['S' => "CONNECTION#{$connectionId}"]),
            ],
        ]));

        LazyCollection::make($request->getIterator())->each(function (array $item) use ($connectionId) {
            $this->channel($item['channel']->getS())->unsubscribe($connectionId);
        });
    }

    protected function determineChannelClass(string $channelName): string
    {
        if (Str::startsWith($channelName, 'private-')) {
            return PrivateChannel::class;
        }

        if (Str::startsWith($channelName, 'presence-')) {
            return PresenceChannel::class;
        }

        return PublicChannel::class;
    }
}
