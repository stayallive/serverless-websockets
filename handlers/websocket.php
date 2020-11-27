<?php

use AsyncAws\DynamoDb\DynamoDbClient;
use Stayallive\ServerlessWebSockets\Handlers\PusherProtocolHandler;
use Stayallive\ServerlessWebSockets\Connections\DynamoDB\ConnectionManager;

require __DIR__ . '/../vendor/autoload.php';

return new PusherProtocolHandler(
    new ConnectionManager(
        new DynamoDbClient
    )
);
