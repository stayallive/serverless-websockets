<?php

namespace Stayallive\ServerlessWebSockets\Messages;

interface Message
{
    public function toMessageBody(): string;
}
