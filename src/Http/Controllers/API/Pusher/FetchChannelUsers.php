<?php

namespace Stayallive\ServerlessWebSockets\Http\Controllers\API\Pusher;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Stayallive\ServerlessWebSockets\Connections\Channels\PresenceChannel;

class FetchChannelUsers extends Controller
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, string $channelName): ResponseInterface
    {
        $channel = $this->connections->channel($channelName);

        if (!$channel instanceof PresenceChannel) {
            return $this->responseFactory->createResponse(400, 'User can only be retrieved for presence channels.');
        }

        return $this->jsonResponse([
            'users' => array_map(static fn ($id) => compact('id'), $channel->userIds()),
        ]);
    }
}
