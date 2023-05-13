<?php

use Psr\Http\Message\ServerRequestInterface;
use Slim\Factory\AppFactory;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../middleware/BeforeMiddleware.php';
require_once __DIR__ . '/../middleware/AfterMiddleware.php';

$app = AppFactory::create();

$app->addRoutingMiddleware();
$app->addBodyParsingMiddleware();
$app->setBasePath('/social-network-api');

$customErrorHandler = function (
    ServerRequestInterface $request,
    Throwable              $exception,
    bool                   $displayErrorDetails,
    bool                   $logErrors,
    bool                   $logErrorDetails,
    ?LoggerInterface       $logger = null
) use ($app) {

    $payload = array();
    $payload['status'] = $exception->getCode();
    $payload['message'] = $exception->getMessage();

    $response = $app->getResponseFactory()->createResponse();
    $response->getBody()->write(
        json_encode($payload)
    );

    return $response->withHeader('Content-Type', 'application/json')
        ->withStatus($exception->getCode() != 0 ? $exception->getCode() : 500);
};

$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$errorMiddleware->setDefaultErrorHandler($customErrorHandler);

$app->add(new BeforeMiddleware());

require_once __DIR__ . '/../app/user.php';
require_once __DIR__ . '/../app/utils.php';
require_once __DIR__ . '/../app/post.php';
$app->run();
?>