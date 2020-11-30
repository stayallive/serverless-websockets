<?php

namespace Stayallive\ServerlessWebSockets\Handlers;

use DI\Container;
use Aws\CloudWatch\CloudWatchClient;
use Stayallive\ServerlessWebSockets\Connections\ConnectionManager;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;

class CLIHandler
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function __invoke(array $args = [])
    {
        $action = $args['action'] ?? '';

        switch ($action) {
            case 'write-cloudwatch-metrics':
                $this->executeWriteCloudWatchMetrics();
                break;
            case 'cleanup-stale-connections':
                $this->executeCleanupStaleConnections();
                break;
            case 'disconnect-all-connections':
                $this->executeDisconnectAllConnections();
                break;
            default:
                echo "CLI action {$action} not found, make sure you supply the correct action!";
                break;
        }
    }

    private function executeWriteCloudWatchMetrics(): void
    {
        $cloudWatchClient = new CloudWatchClient([
            'version' => 'latest',
            'region'  => app_region(),
        ]);

        $time = time();

        [
            $connectedClients,
            $averageConnectedTime,
        ] = $this->container->get(ConnectionManager::class)->retrieveConnectionStatistics();

        $dimensions = [
            [
                'Name'  => 'Stage',
                'Value' => app_stage(),
            ],
            [
                'Name'  => 'Region',
                'Value' => app_region(),
            ],
        ];

        $cloudWatchClient->putMetricData([
            'Namespace'  => 'ServerlessWebSockets',
            'MetricData' => [
                [
                    'MetricName' => 'ConnectedClients',
                    'Timestamp'  => $time,
                    'Dimensions' => $dimensions,
                    'Unit'       => 'Count',
                    'Value'      => (float)$connectedClients,
                ],
                [
                    'MetricName' => 'AverageConnectedTime',
                    'Timestamp'  => $time,
                    'Dimensions' => $dimensions,
                    'Unit'       => 'Seconds',
                    'Value'      => (float)$averageConnectedTime,
                ],
            ],
        ]);
    }

    private function executeCleanupStaleConnections(): void
    {
        $connectionManager = $this->container->get(ConnectionManager::class);

        $staleConnectionIds = $connectionManager->findStaleConnectionIds();

        echo 'Found ' . count($staleConnectionIds) . ' stale connections to force disconnect.' . PHP_EOL;

        foreach ($staleConnectionIds as $connectionId) {
            $connectionManager->disconnectConnectionId($connectionId);

            try {
                socket_client()->disconnect($connectionId);
            } catch (ClientExceptionInterface $e) {
                // It's possible the client was actually stale so we ignore any disconnect errors
            }
        }
    }

    private function executeDisconnectAllConnections(): void
    {
        $connectionManager = $this->container->get(ConnectionManager::class);

        $connectionIds = $connectionManager->findStaleConnectionIds(0);

        echo 'Found ' . count($connectionIds) . ' connections to force disconnect.' . PHP_EOL;

        foreach ($connectionIds as $connectionId) {
            $connectionManager->disconnectConnectionId($connectionId);

            try {
                socket_client()->disconnect($connectionId);
            } catch (ClientExceptionInterface $e) {
                // It's possible the client was actually stale so we ignore any disconnect errors
            }
        }
    }
}
