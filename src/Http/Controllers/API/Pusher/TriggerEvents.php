<?php

namespace Stayallive\ServerlessWebSockets\Http\Controllers\API\Pusher;

use RuntimeException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class TriggerEvents extends TriggerEvent
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $trigger = $this->getJsonBody($request);

        foreach ($trigger['batch'] ?? [] as $event) {
            try {
                $this->trigger($event);
            } catch (RuntimeException $e) {
                return $this->response(400, $e->getMessage());
            }
        }

        return $this->jsonResponse();
    }
}
