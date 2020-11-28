<?php

namespace Stayallive\ServerlessWebSockets\Messages;

trait SendsPusherMessages
{
    use SendsMessages;

    private function buildPusherMessage(string $event, ?array $data = null, ?string $channel = null): PusherMessage
    {
        $message = new PusherMessage($event);

        if ($data !== null) {
            $message->withData(json_encode($data));
        }

        if ($channel !== null) {
            $message->toChannel($channel);
        }

        return $message;
    }

    /**
     * @see https://pusher.com/docs/channels/library_auth_reference/pusher-websockets-protocol#-pusher-error-pusher-channels-client-
     *
     * @param string $message
     * @param int    $code
     *
     * @return \Stayallive\ServerlessWebSockets\Messages\PusherMessage
     */
    protected function buildPusherErrorMessage(string $message, int $code = 4005): PusherMessage
    {
        return $this->buildPusherMessage('pusher:error', compact('message', 'code'));
    }

    protected function buildPusherEventMessage(string $event, ?array $data = null): PusherMessage
    {
        return $this->buildPusherMessage($event, $data);
    }

    protected function buildPusherChannelMessage(string $channel, string $event, ?array $data = null): PusherMessage
    {
        return $this->buildPusherMessage($event, $data, $channel);
    }
}
