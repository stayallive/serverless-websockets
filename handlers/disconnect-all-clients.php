<?php

use Stayallive\ServerlessWebSockets\Handlers\DisconnectAllClientsHandler;

/** @var \DI\Container $container */
$container = require __DIR__ . '/../bootstrap/container.php';

return $container->get(
    DisconnectAllClientsHandler::class
);
