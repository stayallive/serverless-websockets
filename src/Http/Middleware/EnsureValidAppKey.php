<?php

namespace Stayallive\ServerlessWebSockets\Http\Middleware;

use Slim\Routing\Route;
use Slim\Routing\RouteContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class EnsureValidAppKey extends Middleware
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $route = RouteContext::fromRequest($request)->getRoute();

        if ($route instanceof Route && $route->getArgument('appId') === app_id()) {
            return $handler->handle($request);
        }

        return $this->responseFactory->createResponse(401, 'Unknown app ID provided.');
    }
}
