<?php

use Slim\Factory\AppFactory;
use Slim\Exception\HttpNotFoundException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

const VIEW_PATH = __DIR__ . '/../resources/views';

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

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

$app->get('/', function (Request $request, Response $response) {
    $response->getBody()->write(view(VIEW_PATH . '/index.php'));

    return $response;
});

$app->run();
