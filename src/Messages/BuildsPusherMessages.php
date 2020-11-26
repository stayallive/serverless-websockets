<?php

namespace Stayallive\ServerlessWebSockets\Messages;

trait BuildsPusherMessages
{
    private function buildPusherMessage(string $event, ?array $data = null, ?string $channel = null): array
    {
        $message = compact('event');

        if ($data !== null) {
            $message['data'] = json_encode($data);
        }

        if ($channel !== null) {
            $message['channel'] = $channel;
        }

        return $message;
    }

    /**
     * @see https://pusher.com/docs/channels/library_auth_reference/pusher-websockets-protocol#-pusher-error-pusher-channels-client-
     *
     * @param string $message A textual description of the error
     * @param int    $code    A code that identifies the error that has occurred
     *
     * @return array
     */
    protected function buildPusherErrorMessage(string $message, int $code = 4005): array
    {
        return $this->buildPusherMessage('pusher:error', compact('message', 'code'));
    }

    protected function buildPusherEventMessage(string $event, ?array $data = null): array
    {
        return $this->buildPusherMessage($event, $data);
    }

    protected function buildPusherChannelMessage(string $channel, string $event, ?array $data = null): array
    {
        return $this->buildPusherMessage($event, $data, $channel);
    }

    protected function buildPusherAcknowledgeMessage(): array
    {
        return $this->buildPusherMessage('internal:ack');
    }
}
