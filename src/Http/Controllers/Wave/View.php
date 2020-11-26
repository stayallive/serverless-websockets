<?php

namespace Stayallive\ServerlessWebSockets\Http\Controllers\Wave;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Stayallive\ServerlessWebSockets\Http\Controllers\Controller;

class View extends Controller
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write(view(VIEW_PATH . '/wave/index.php'));

        return $response;
    }
}
