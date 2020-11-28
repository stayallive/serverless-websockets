<?php

use Stayallive\ServerlessWebSockets\Handlers\WebhookHandler;

/** @var \DI\Container $container */
$container = require __DIR__ . '/../bootstrap/container.php';

return $container->get(
    WebhookHandler::class
);
