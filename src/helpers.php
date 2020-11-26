<?php

use Illuminate\Support\Str;
use Bref\Websocket\SimpleWebsocketClient;
use Symfony\Component\HttpClient\HttpClient;

function get_required_env_var(string $name)
{
    $value = getenv($name);

    if (empty($value)) {
        throw new RuntimeException("There is no {$name} environment variable defined.");
    }

    return $value;
}

function app_id(): string
{
    return get_required_env_var('APP_ID');
}

function app_key(): string
{
    return get_required_env_var('APP_KEY');
}

function app_stage(): string
{
    return get_required_env_var('APP_STAGE');
}

function app_secret(): string
{
    return get_required_env_var('APP_SECRET');
}

function app_region(): string
{
    return get_required_env_var('APP_REGION');
}

function app_ws_api_id(): string
{
    return get_required_env_var('APP_WS_API_ID');
}

function app_ws_api_endpoint(): string
{
    return sprintf('%s.execute-api.%s.amazonaws.com', app_ws_api_id(), app_region());
}

function wave_example_enabled(): bool
{
    return get_required_env_var('APP_WAVE_EXAMPLE_ENABLED') === 'true';
}

function client_events_enabled(): bool
{
    return get_required_env_var('APP_CLIENT_EVENTS') === 'true';
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
