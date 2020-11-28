<?php

namespace Stayallive\ServerlessWebSockets\Messages;

interface MessageHandler
{
    public function handle(): void;
}
