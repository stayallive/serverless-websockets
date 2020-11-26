<?php

use Slim\App;
use AsyncAws\DynamoDb\DynamoDbClient;
use Psr\Container\ContainerInterface;
use Slim\Routing\RouteCollectorProxy;
use Slim\Exception\HttpNotFoundException;
use Psr\Http\Message\ResponseFactoryInterface;
use Stayallive\ServerlessWebSockets\Http\Middleware;
use Stayallive\ServerlessWebSockets\Http\Controllers;
use Psr\Http\Message\ServerRequestInterface as Request;
use Stayallive\ServerlessWebSockets\Connections\ConnectionManager;
use Stayallive\ServerlessWebSockets\Connections\DynamoDB\ConnectionManager as DynamoDBConnectionManager;

const VIEW_PATH = __DIR__ . '/../resources/views';

require __DIR__ . '/../vendor/autoload.php';

$app = DI\Bridge\Slim\Bridge::create();

/** @var \DI\Container $container */
$container = $app->getContainer();

$container->set(ResponseFactoryInterface::class, function (ContainerInterface $container) {
    return $container->get(App::class)->getResponseFactory();
});
$container->set(ConnectionManager::class, function () {
    return new DynamoDBConnectionManager(new DynamoDbClient);
});

$app->addRoutingMiddleware();

$errorHandler = $app->addErrorMiddleware(false, true, true);

$errorHandler->setErrorHandler(
    HttpNotFoundException::class,
    function (
        Request $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails,
        ?LoggerInterface $logger = null
    ) use ($app) {
        $response = $app->getResponseFactory()->createResponse(404, 'Not Found');

        $response->getBody()->write(view(VIEW_PATH . '/errors/404.html'));

        return $response;
    }
);

if (wave_example_enabled()) {
    $app->group('/wave', function (RouteCollectorProxy $group) {
        $group->get('', Controllers\Wave\View::class);
        $group->post('/pusher/auth', Controllers\Wave\SocketAuth::class);
    });
}

$app->group('/apps/{appId}', function (RouteCollectorProxy $group) {
    $group->post('/events', Controllers\API\Pusher\TriggerEvent::class);
    $group->post('/batch_events', Controllers\API\Pusher\TriggerEvents::class);

    $group->get('/channels', Controllers\API\Pusher\FetchChannels::class);
    $group->get('/channels/{channelName}', Controllers\API\Pusher\FetchChannel::class);
    $group->get('/channels/{channelName}/users', Controllers\API\Pusher\FetchChannelUsers::class);
})
    ->add($container->get(Middleware\EnsureValidAppKey::class))
    ->add($container->get(Middleware\EnsureValidSignature::class));

$app->run();
