<?php

namespace Stayallive\ServerlessWebSockets\Http\Controllers;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;

abstract class Controller
{
    protected ResponseFactoryInterface $responseFactory;

    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        $this->responseFactory = $responseFactory;
    }

    abstract public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface;

    protected function response(int $status = 204, string $message = ''): ResponseInterface
    {
        return $this->responseFactory->createResponse($status, $message);
    }

    protected function getJsonBody(RequestInterface $request): array
    {
        $body = json_decode($request->getBody()->getContents(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        return $body;
    }

    protected function jsonResponse(array $data = [], int $status = 200): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($status);

        $response->getBody()->write(json_encode($data));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
