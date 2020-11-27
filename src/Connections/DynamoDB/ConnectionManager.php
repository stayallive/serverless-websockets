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
    public const CHANNELS_TABLE        = 'serverless-websockets-channels';
    public const CONNECTION_POOL_TABLE = 'serverless-websockets-connection-pool';

    private DynamoDbClient $db;

    public function __construct(DynamoDbClient $db)
    {
        $this->db = $db;
    }


    public function connect(WebsocketEvent $event): void
    {
        $this->db->putItem(new PutItemInput([
            'TableName' => self::CONNECTION_POOL_TABLE,
            'Item'      => [
                'connection-id' => new AttributeValue(['S' => $event->getConnectionId()]),
                'socket-id'     => new AttributeValue(['S' => $this->generateSocketId()]),
                'api-id'        => new AttributeValue(['S' => $event->getApiId()]),
                'region'        => new AttributeValue(['S' => $event->getRegion()]),
                'stage'         => new AttributeValue(['S' => $event->getStage()]),
            ],
        ]));
    }

    public function disconnect(WebsocketEvent $event): void
    {
        $this->removeFromAllChannels($event->getConnectionId());

        $this->db->deleteItem(new DeleteItemInput([
            'TableName' => self::CONNECTION_POOL_TABLE,
            'Key'       => [
                'connection-id' => new AttributeValue(['S' => $event->getConnectionId()]),
            ],
        ]));
    }


    public function findSocketIdForConnectionId(string $connectionId): ?string
    {
        $request = $this->db->getItem(new GetItemInput([
            'TableName'      => self::CONNECTION_POOL_TABLE,
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
            'TableName'      => self::CHANNELS_TABLE,
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
            'TableName'      => self::CHANNELS_TABLE,
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
            'TableName'      => self::CONNECTION_POOL_TABLE,
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
