<?php

namespace Stayallive\ServerlessWebSockets\Http\Controllers;

use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Stayallive\ServerlessWebSockets\Connections\Channel;
use Stayallive\ServerlessWebSockets\Connections\ConnectionManager;

class FetchChannels extends Controller
{
    private ConnectionManager $connections;

    public function __construct(ResponseFactoryInterface $responseFactory, ConnectionManager $connections)
    {
        parent::__construct($responseFactory);

        $this->connections = $connections;
    }

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
            $channels = $channels->filter(fn (Channel $channel, string $channelName) => Str::startsWith($channelName, $queryParams['filter_by_prefix']));
        }

        return $this->jsonResponse([
            'channels' => (object)$channels->map(function (Channel $channel) use ($attributes) {
                $info = [];

                if (in_array('user_count', $attributes)) {
                    $info['user_count'] = $channel->connectionCount();
                }

                return (object)$info;
            })->toArray(),
        ]);
    }
}
