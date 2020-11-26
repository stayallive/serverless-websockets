<?php

namespace Stayallive\ServerlessWebSockets\Http\Controllers\API\Pusher;

use Psr\Http\Message\ResponseFactoryInterface;
use Stayallive\ServerlessWebSockets\Connections\ConnectionManager;
use Stayallive\ServerlessWebSockets\Http\Controllers\Controller as BaseController;

abstract class Controller extends BaseController
{
    protected ConnectionManager $connections;

    public function __construct(ResponseFactoryInterface $responseFactory, ConnectionManager $connections)
    {
        parent::__construct($responseFactory);

        $this->connections = $connections;
    }
}
