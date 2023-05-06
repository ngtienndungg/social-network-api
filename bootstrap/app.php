<?php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;
use Slim\Factory\AppFactory;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../middleware/BeforeMiddleware.php';
require_once __DIR__ . '/../middleware/AfterMiddleware.php';

$app = AppFactory::create();

$app->addRoutingMiddleware();
$app->setBasePath('/social-network-api');


$errorMiddleware = $app->addErrorMiddleware(true, true, true);

$app->get('/hello/{name}', function ($request, $response, $args) {
    $name = $args['name'];
    $response->getBody()->write("Hello, $name");
    return $response;
});

$app->add(new BeforeMiddleware());
$app->add(new AfterMiddleware());

$app->run();