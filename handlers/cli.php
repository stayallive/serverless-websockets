<?php

use Stayallive\ServerlessWebSockets\Handlers\CLIHandler;

/** @var \DI\Container $container */
$container = require __DIR__ . '/../bootstrap/container.php';

return $container->get(
    CLIHandler::class,
);
