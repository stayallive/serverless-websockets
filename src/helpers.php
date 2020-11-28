<?php

use AsyncAws\Sqs\SqsClient;
use Illuminate\Support\Str;
use Bref\Websocket\SimpleWebsocketClient;
use AsyncAws\Sqs\Input\SendMessageRequest;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;

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

function app_sqs_url(): string
{
    return get_required_env_var('APP_SQS_URL');
}

function app_ws_api_id(): string
{
    return get_required_env_var('APP_WS_API_ID');
}

function app_ws_api_endpoint(): string
{
    return sprintf('%s.execute-api.%s.amazonaws.com', app_ws_api_id(), app_region());
}

function app_db_channels_table(): string
{
    return get_required_env_var('APP_DB_CHANNELS_TABLE');
}

function app_db_connections_table(): string
{
    return get_required_env_var('APP_DB_CONNECTIONS_TABLE');
}


function wave_example_enabled(): bool
{
    return get_required_env_var('APP_WAVE_EXAMPLE_ENABLED') === 'true';
}

function client_events_enabled(): bool
{
    return get_required_env_var('APP_CLIENT_EVENTS') === 'true';
}


function queue_webhook(string $event, array $data = []): void
{
    if (!webhook_event_enabled($event)) {
        return;
    }

    sqs_client()->sendMessage(new SendMessageRequest([
        'QueueUrl'    => app_sqs_url(),
        'MessageBody' => json_encode([
            'action' => 'send_webhook',
            'time'   => time(),
            'event'  => array_merge([
                'name' => $event,
            ], $data),
        ]),
    ]));
}

function webhook_target(): string
{
    return getenv('APP_WEBHOOK_TARGET') ?: '';
}

function webhook_event_enabled(string $event): bool
{
    if (!webhook_events_enabled()) {
        return false;
    }

    $enabled = explode(',', getenv('APP_WEBHOOK_EVENTS') ?: '');

    return in_array($event, $enabled);
}

function webhook_events_enabled(): bool
{
    return !empty(webhook_target());
}


function sqs_client(): SqsClient
{
    return new SqsClient;
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

function webhook_client(): HttpClientInterface
{
    // This configuration will retry 3 times with the following delays:
    // #0: 0ms
    // #1: 250ms
    // #2: 2.5s
    // #3: 25s
    return new RetryableHttpClient(
        HttpClient::create([
            'timeout'       => 15,
            'max_redirects' => 3,
            'headers'       => [
                'User-Agent' => 'ServerlessWebsockets-WebhookSlinger',
            ],
        ]),
        new GenericRetryStrategy(
            GenericRetryStrategy::DEFAULT_RETRY_STATUS_CODES,
            250,
            10.0
        ),
        3
    );
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
