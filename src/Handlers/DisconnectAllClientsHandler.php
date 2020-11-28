<?php

namespace Stayallive\ServerlessWebSockets\Handlers;

use Stayallive\ServerlessWebSockets\Connections\ConnectionManager;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;

class DisconnectAllClientsHandler
{
    private ConnectionManager $connectionManager;

    public function __construct(ConnectionManager $connectionManager)
    {
        $this->connectionManager = $connectionManager;
    }

    public function __invoke(): void
    {
        $staleConnectionIds = $this->connectionManager->findStaleConnectionIds(0);

        echo 'Found ' . count($staleConnectionIds) . ' connections to force disconnect.' . PHP_EOL;

        foreach ($staleConnectionIds as $connectionId) {
            $this->connectionManager->disconnectConnectionId($connectionId);

            try {
                socket_client()->disconnect($connectionId);
            } catch (ClientExceptionInterface $e) {
                // It's possible the client was actually stale so we ignore any disconnect errors
            }
        }
    }
}
