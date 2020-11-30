<?php

namespace Stayallive\ServerlessWebSockets\Http\Controllers\API\Pusher;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Stayallive\ServerlessWebSockets\Connections\Channels\PresenceChannel;

class FetchChannel extends Controller
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, string $channelName): ResponseInterface
    {
        $attributes  = [];
        $queryParams = $request->getQueryParams();

        if (isset($queryParams['info'])) {
            $attributes = explode(',', trim($queryParams['info']));
        }

        $channel = $this->connections->channel($channelName);

        $channelData = [
            'occupied' => $channel->hasConnections(),
        ];

        if (in_array('user_count', $attributes)) {
            if (!$channel instanceof PresenceChannel) {
                return $this->responseFactory->createResponse(400, 'Request must be limited to presence channels in order to fetch user_count.');
            }

            $channelData['user_count'] = $channel->userCount();
        }

        if (in_array('subscription_count', $attributes)) {
            $channelData['subscription_count'] = $channel->connectionCount();
        }

        return $this->jsonResponse($channelData);
    }
}
