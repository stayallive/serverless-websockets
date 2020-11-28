<?php

use DI\ContainerBuilder;
use AsyncAws\DynamoDb\DynamoDbClient;
use Stayallive\ServerlessWebSockets\Connections\ConnectionManager;

require_once __DIR__ . '/../vendor/autoload.php';

define('BASE_PATH', dirname(__DIR__) . '/');
define('VIEW_PATH', dirname(__DIR__) . '/resources/views');

$builder = new ContainerBuilder;

$builder->addDefinitions([
    ConnectionManager::class => static function (DynamoDbClient $db): ConnectionManager {
        return new Stayallive\ServerlessWebSockets\Connections\DynamoDB\ConnectionManager($db);
    },
]);

return $builder->build();
