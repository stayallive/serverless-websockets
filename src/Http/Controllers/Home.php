<?php

namespace Stayallive\ServerlessWebSockets\Http\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Home extends Controller
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write(view(VIEW_PATH . '/index.php'));

        return $response;
    }
}
