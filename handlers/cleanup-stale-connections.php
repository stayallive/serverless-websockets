<?php

use Stayallive\ServerlessWebSockets\Handlers\CleanupStaleConnectionHandler;

/** @var \DI\Container $container */
$container = require __DIR__ . '/../bootstrap/container.php';

return $container->get(
    CleanupStaleConnectionHandler::class
);
