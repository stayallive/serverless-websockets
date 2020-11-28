<?php

use Stayallive\ServerlessWebSockets\Handlers\PusherProtocolHandler;

/** @var \DI\Container $container */
$container = require __DIR__ . '/../bootstrap/container.php';

return $container->get(
    PusherProtocolHandler::class
);
