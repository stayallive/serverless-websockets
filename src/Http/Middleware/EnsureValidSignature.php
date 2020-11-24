<?php

namespace Stayallive\ServerlessWebSockets\Http\Middleware;

use Pusher\Pusher;
use Illuminate\Support\Arr;
use Slim\Routing\RouteContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class EnsureValidSignature extends Middleware
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $body   = $request->getBody()->getContents();
        $params = Arr::except($queryParams = $request->getQueryParams(), ['auth_signature', 'body_md5', 'appId', 'appKey', 'channelName']);

        if (!empty($body)) {
            $params['body_md5'] = md5($body);
        }

        ksort($params);

        $route = RouteContext::fromRequest($request);

        $signature = "{$request->getMethod()}\n{$route->getRoutingResults()->getUri()}\n" . Pusher::array_implode('=', '&', $params);

        $authSignature = hash_hmac('sha256', $signature, getenv('APP_SECRET'));

        if ($authSignature !== ($queryParams['auth_signature'] ?? null)) {
            return $this->responseFactory->createResponse(401, 'Invalid auth signature provided.');
        }

        return $handler->handle($request);
    }
}
