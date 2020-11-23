<?php

use Illuminate\Support\Str;
use Bref\Websocket\SimpleWebsocketClient;
use Symfony\Component\HttpClient\HttpClient;

function app_stage(): string
{
    return getenv('APP_STAGE');
}

function app_region(): string
{
    return getenv('APP_REGION');
}

function app_api_id(): string
{
    return getenv('APP_API_ID');
}

function app_api_endpoint(): string
{
    return sprintf('%s.execute-api.%s.amazonaws.com', app_api_id(), app_region());
}

function app_ws_api_id(): string
{
    return getenv('APP_WS_API_ID');
}

function app_ws_api_endpoint(): string
{
    return sprintf('%s.execute-api.%s.amazonaws.com', app_ws_api_id(), app_region());
}

function socket_client(): SimpleWebsocketClient
{
    static $client = null;

    if ($client === null) {
        $client = new SimpleWebsocketClient(
            app_ws_api_id(),
            app_region(),
            app_stage(),
            HttpClient::create()
        );
    }

    return $client;
}

function view(string $path): string
{
    if (!file_exists($path)) {
        throw new RuntimeException('File to render as view does not exist!');
    }

    if (Str::endsWith($path, '.html')) {
        return file_get_contents($path);
    }

    if (!Str::endsWith($path, '.php')) {
        throw new RuntimeException('File to render as view has an unsupported extension!');
    }

    ob_start();

    /** @noinspection PhpIncludeInspection */
    require $path;

    $content = ob_get_contents();

    ob_clean();

    return $content;
}
