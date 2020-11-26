<?php

namespace Stayallive\ServerlessWebSockets\Http\Controllers\API\Pusher;

use RuntimeException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class TriggerEvent extends Controller
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $trigger = $this->getJsonBody($request);

        try {
            $this->trigger($trigger);
        } catch (RuntimeException $e) {
            return $this->response(400, $e->getMessage());
        }

        return $this->jsonResponse();
    }

    protected function trigger(array $event): void
    {
        if (empty($event['channel']) && empty($event['channels'])) {
            throw new RuntimeException('Missing channel(s) to broadcast event to.');
        }

        if (empty($event['channels'])) {
            $event['channels'] = [$event['channel']];
        }

        if (empty($event['name'])) {
            throw new RuntimeException('Missing event name to broadcast.');
        }

        foreach ($event['channels'] as $channelName) {
            $channel = $this->connections->findChannel($channelName);

            if ($channel !== null) {
                $channel->broadcastToEveryoneExcept(
                    $event['name'],
                    $event['data'] ?? null,
                    $event['socket_id'] ?? null
                );
            }
        }
    }
}
