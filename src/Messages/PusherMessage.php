<?php

namespace Stayallive\ServerlessWebSockets\Messages;

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

    public function toMessageBody(): string
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

        return json_encode($message);
    }
}
