<?php

namespace Stayallive\ServerlessWebSockets\Messages;

use Bref\Event\Http\HttpResponse;

class PusherMessage implements Message
{
    private string  $event;
    private ?string $channel = null;
    private ?string $data    = null;

    public function __construct(string $event)
    {
        $this->event = $event;
    }

    public function toChannel(string $channel): self
    {
        $this->channel = $channel;

        return $this;
    }

    public function withData(string $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function toGatewayResponse(): HttpResponse
    {
        $message = [
            'event' => $this->event,
        ];

        if ($this->data !== null) {
            $message['data'] = $this->data;
        }

        if ($this->channel !== null) {
            $message['channel'] = $this->channel;
        }

        return new HttpResponse(json_encode($message));
    }
}
