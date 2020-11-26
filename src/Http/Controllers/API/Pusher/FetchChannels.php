<?php

namespace Stayallive\ServerlessWebSockets\Http\Controllers\API\Pusher;

use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Stayallive\ServerlessWebSockets\Connections\Channels\AbstractChannel;
use Stayallive\ServerlessWebSockets\Connections\Channels\PresenceChannel;

class FetchChannels extends Controller
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $attributes  = [];
        $queryParams = $request->getQueryParams();

        if (isset($queryParams['info'])) {
            $attributes = explode(',', trim($queryParams['info']));

            if (in_array('user_count', $attributes) && !Str::startsWith($queryParams['filter_by_prefix'] ?? '', 'presence-')) {
                return $this->responseFactory->createResponse(400, 'Request must be limited to presence channels in order to fetch user_count.');
            }
        }

        $channels = collect($this->connections->channels());

        if (!empty($queryParams['filter_by_prefix'])) {
            $channels = $channels->filter(fn (AbstractChannel $channel, string $channelName) => Str::startsWith($channelName, $queryParams['filter_by_prefix']));
        }

        return $this->jsonResponse([
            'channels' => (object)$channels->map(function (AbstractChannel $channel) use ($attributes) {
                $info = [];

                if ($channel instanceof PresenceChannel && in_array('user_count', $attributes)) {
                    $info['user_count'] = $channel->userCount();
                }

                if (in_array('subscription_count', $attributes)) {
                    $info['subscription_count'] = $channel->connectionCount();
                }

                return (object)$info;
            })->toArray(),
        ]);
    }
}
