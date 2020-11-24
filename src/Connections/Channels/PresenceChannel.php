<?php

namespace Stayallive\ServerlessWebSockets\Connections\Channels;

interface PresenceChannel
{
    public function userCount(): int;
}
