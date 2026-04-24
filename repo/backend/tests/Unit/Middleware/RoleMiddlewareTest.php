<?php

use App\Http\Middleware\RoleMiddleware;
use App\Models\User;
use Illuminate\Http\Request;

function makeRoleRequest(?User $user = null): Request
{
    $request = Request::create('/api/users', 'GET');
    $request->setUserResolver(fn () => $user);

    return $request;
}

test('RoleMiddleware returns 401 when request has no authenticated user', function () {
    $middleware = new RoleMiddleware();
    $request = makeRoleRequest(null);

    $response = $middleware->handle($request, fn () => response('ok', 200), 'admin');

    expect($response->getStatusCode())->toBe(401);
    expect($response->getContent())->toContain('Unauthenticated');
});

test('RoleMiddleware returns 403 when user role is not allowed', function () {
    $middleware = new RoleMiddleware();
    $request = makeRoleRequest(User::factory()->make(['role' => 'user']));

    $response = $middleware->handle($request, fn () => response('ok', 200), 'admin,technician');

    expect($response->getStatusCode())->toBe(403);
    expect($response->getContent())->toContain('Insufficient permissions');
});

test('RoleMiddleware allows request when role is included in allowed roles', function () {
    $middleware = new RoleMiddleware();
    $request = makeRoleRequest(User::factory()->make(['role' => 'technician']));

    $response = $middleware->handle($request, fn () => response('ok', 200), 'admin', 'technician');

    expect($response->getStatusCode())->toBe(200);
});
