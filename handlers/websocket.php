<?php

use AsyncAws\DynamoDb\DynamoDbClient;
use Stayallive\ServerlessWebSockets\Connections\DynamoDB\ConnectionManager;

require __DIR__ . '/../vendor/autoload.php';

return new Stayallive\ServerlessWebSockets\Handlers\PusherProtocolHandler(
    new ConnectionManager(
        new DynamoDbClient
    )
);
