<?php

use App\Http\Middleware\GatewayTokenMiddleware;
use Illuminate\Http\Request;

function makeGatewayRequest(?string $token = null): Request
{
    $server = [];
    if ($token !== null) {
        $server['HTTP_X_GATEWAY_TOKEN'] = $token;
    }

    return Request::create('/api/gateway/events', 'POST', [], [], [], $server);
}

test('GatewayTokenMiddleware rejects requests without token', function () {
    config(['smartpark.gateway.token' => 'secret-token']);
    $middleware = new GatewayTokenMiddleware();

    $response = $middleware->handle(makeGatewayRequest(null), fn () => response('ok', 201));

    expect($response->getStatusCode())->toBe(401);
});

test('GatewayTokenMiddleware rejects requests with wrong token', function () {
    config(['smartpark.gateway.token' => 'secret-token']);
    $middleware = new GatewayTokenMiddleware();

    $response = $middleware->handle(makeGatewayRequest('wrong-token'), fn () => response('ok', 201));

    expect($response->getStatusCode())->toBe(401);
});

test('GatewayTokenMiddleware allows requests with matching token', function () {
    config(['smartpark.gateway.token' => 'secret-token']);
    $middleware = new GatewayTokenMiddleware();

    $response = $middleware->handle(makeGatewayRequest('secret-token'), fn () => response('ok', 201));

    expect($response->getStatusCode())->toBe(201);
});
