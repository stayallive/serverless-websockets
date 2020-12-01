<?php

use Psr\Log\LogLevel;
use DI\ContainerBuilder;
use Psr\Log\LoggerInterface;
use Bref\Logger\StderrLogger;
use AsyncAws\DynamoDb\DynamoDbClient;
use Stayallive\ServerlessWebSockets\Connections\ConnectionManager;

require_once __DIR__ . '/../vendor/autoload.php';

define('BASE_PATH', dirname(__DIR__) . '/');
define('VIEW_PATH', dirname(__DIR__) . '/resources/views');

$builder = new ContainerBuilder;

$builder->addDefinitions([
    LoggerInterface::class => static function (): LoggerInterface {
        return new StderrLogger(getenv('APP_LOG_LEVEL') ?: LogLevel::INFO);
    },

    ConnectionManager::class => static function (DynamoDbClient $db): ConnectionManager {
        return new Stayallive\ServerlessWebSockets\DynamoDB\ConnectionManager($db);
    },
]);

return $builder->build();
