<?php

namespace Stayallive\ServerlessWebSockets\Connections\DynamoDB;

use Illuminate\Support\Str;
use AsyncAws\DynamoDb\DynamoDbClient;
use AsyncAws\DynamoDb\Input\ScanInput;
use AsyncAws\DynamoDb\Input\GetItemInput;
use AsyncAws\DynamoDb\Input\PutItemInput;
use Bref\Event\ApiGateway\WebsocketEvent;
use AsyncAws\DynamoDb\Input\DeleteItemInput;
use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use Stayallive\ServerlessWebSockets\Connections\Channels\AbstractChannel;
use Stayallive\ServerlessWebSockets\Connections\DynamoDB\Channels\PublicChannel;
use Stayallive\ServerlessWebSockets\Connections\DynamoDB\Channels\PrivateChannel;
use Stayallive\ServerlessWebSockets\Connections\DynamoDB\Channels\PresenceChannel;
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
        $this->db->putItem(new PutItemInput([
            'TableName' => app_db_connection_pool_table(),
            'Item'      => [
                'connection-id' => new AttributeValue(['S' => $event->getConnectionId()]),
                'connect-time'  => new AttributeValue(['N' => (string)time()]),
                'socket-id'     => new AttributeValue(['S' => $this->generateSocketId()]),
            ],
        ]));
    }

    public function disconnect(WebsocketEvent $event): void
    {
        $this->removeFromAllChannels($event->getConnectionId());

        $this->db->deleteItem(new DeleteItemInput([
            'TableName' => app_db_connection_pool_table(),
            'Key'       => [
                'connection-id' => new AttributeValue(['S' => $event->getConnectionId()]),
            ],
        ]));
    }


    public function findSocketIdForConnectionId(string $connectionId): ?string
    {
        $request = $this->db->getItem(new GetItemInput([
            'TableName'      => app_db_connection_pool_table(),
            'Key'            => [
                'connection-id' => new AttributeValue(['S' => $connectionId]),
            ],
            'ConsistentRead' => true,
        ]));

        try {
            $request->resolve();
        } catch (HttpException $e) {
            return null;
        }

        $result = $request->getItem();

        if (empty($result)) {
            return null;
        }

        return $result['socket-id']->getS();
    }


    public function channels(): array
    {
        $request = $this->db->scan(new ScanInput([
            'TableName'      => app_db_channels_table(),
            'ConsistentRead' => true,
        ]));

        try {
            $request->resolve();
        } catch (HttpException $e) {
            return [];
        }

        $channels = [];

        foreach ($request->getItems() as $channelData) {
            $channelName  = $channelData['channel-id']->getS();
            $channelClass = $this->determineChannelClass($channelName);

            $channels[$channelName] = new $channelClass($channelName, $this->db, $channelData);
        }

        return $channels;
    }

    public function findChannel(string $channelName): ?AbstractChannel
    {
        $request = $this->db->getItem(new GetItemInput([
            'TableName'      => app_db_channels_table(),
            'Key'            => [
                'channel-id' => new AttributeValue(['S' => $channelName]),
            ],
            'ConsistentRead' => true,
        ]));

        try {
            $request->resolve();
        } catch (HttpException $e) {
            return null;
        }

        $result = $request->getItem();

        if (empty($result)) {
            return null;
        }

        $channelClass = $this->determineChannelClass($channelName);

        return new $channelClass($channelName, $this->db, $result);
    }

    public function findOrNewChannel(string $channelName): AbstractChannel
    {
        $channel = $this->findChannel($channelName);

        if ($channel === null) {
            $channelClass = $this->determineChannelClass($channelName);

            $channel = new $channelClass($channelName, $this->db);
        }

        return $channel;
    }


    protected function generateSocketId(): string
    {
        return sprintf('%d.%d', random_int(1, 1000000000), random_int(1, 1000000000));
    }

    protected function removeFromAllChannels(string $connectionId): void
    {
        $request = $this->db->getItem(new GetItemInput([
            'TableName'      => app_db_connection_pool_table(),
            'Key'            => [
                'connection-id' => new AttributeValue(['S' => $connectionId]),
            ],
            'ConsistentRead' => true,
        ]));

        try {
            $request->resolve();
        } catch (HttpException $e) {
            return;
        }

        /** @noinspection ProperNullCoalescingOperatorUsageInspection */
        $channels = $request->getItem()['channels'] ?? [];

        if ($channels instanceof AttributeValue) {
            $channels = $channels->getSS();
        }

        foreach ($channels as $channel) {
            $channel = $this->findChannel($channel);

            if ($channel !== null) {
                if ($channel instanceof AbstractChannel) {
                    $channel->updateConnectionPool(false);
                }

                $channel->unsubscribe($connectionId);
            }
        }
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
