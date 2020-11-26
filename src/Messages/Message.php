<?php

namespace Stayallive\ServerlessWebSockets\Messages;

use Bref\Event\Http\HttpResponse;

interface Message
{
    public function toGatewayResponse(): HttpResponse;
}
