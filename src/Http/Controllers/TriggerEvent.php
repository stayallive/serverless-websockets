<?php

namespace Stayallive\ServerlessWebSockets\Http\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Stayallive\ServerlessWebSockets\Connections\ConnectionManager;

class TriggerEvent extends Controller
{
    private ConnectionManager $connections;

    public function __construct(ResponseFactoryInterface $responseFactory, ConnectionManager $connections)
    {
        parent::__construct($responseFactory);

        $this->connections = $connections;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $trigger = $this->getJsonBody($request);

        if (empty($trigger['channel']) && empty($trigger['channels'])) {
            $this->response(400, 'Missing channels to broadcast event to.');
        }

        if (empty($trigger['channels'])) {
            $trigger['channels'] = [$trigger['channel']];
        }

        if (empty($trigger['name'])) {
            $this->response(400, 'Missing event name to broadcast.');
        }

        foreach ($trigger['channels'] as $channelName) {
            $channel = $this->connections->findChannel($channelName);

            if ($channel !== null) {
                $channel->broadcastToEveryoneExcept(
                    $trigger['name'],
                    $trigger['data'] ?? null,
                    $trigger['socket_id'] ?? null
                );
            }
        }

        return $this->jsonResponse();
    }
}
