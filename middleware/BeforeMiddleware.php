<?php

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class BeforeMiddleware
{
    public function __invoke($request, $handler): Response
    {
        if (!IS_APP_LIVE) {
            sleep(1);
        }
        $response = $handler->handle($request);
        return $response;
    }
}