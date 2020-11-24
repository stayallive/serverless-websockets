<?php

namespace Stayallive\ServerlessWebSockets\Http\Controllers;

use Pusher\Pusher;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class SocketAuth extends Controller
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $pusher = new Pusher(getenv('APP_KEY'), getenv('APP_SECRET'), getenv('APP_ID'));

        if (empty($_POST['channel_name']) || empty($_POST['socket_id'])) {
            return $this->response(400, 'Missing required parameters!');
        }

        if (!in_array($_POST['channel_name'], ['presence-test'])) {
            return $this->response(400, 'Sorry, this channel is not allowed!');
        }

        $auth = $pusher->socket_auth($_POST['channel_name'], $_POST['socket_id'], json_encode([
            'user_id' => $_COOKIE['user_id'] ?? '__anon',
        ]));

        return $this->jsonResponse(json_decode($auth, true));
    }
}
