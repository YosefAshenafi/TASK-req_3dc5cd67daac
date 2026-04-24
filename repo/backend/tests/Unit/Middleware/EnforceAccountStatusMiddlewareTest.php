<?php

use App\Http\Middleware\EnforceAccountStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

function makeAccountStatusRequest(?User $user = null): Request
{
    $request = Request::create('/api/search', 'GET');
    $request->setUserResolver(fn () => $user);

    return $request;
}

test('EnforceAccountStatus returns 401 for blacklisted user', function () {
    $middleware = new EnforceAccountStatus();
    $user = User::factory()->make([
        'blacklisted_at' => Carbon::now()->subMinute(),
    ]);

    $response = $middleware->handle(makeAccountStatusRequest($user), fn () => response('ok', 200));

    expect($response->getStatusCode())->toBe(401);
    expect($response->getContent())->toContain('account_blacklisted');
});

test('EnforceAccountStatus returns 423 for currently frozen user', function () {
    $middleware = new EnforceAccountStatus();
    $user = User::factory()->make([
        'frozen_until' => Carbon::now()->addHour(),
    ]);

    $response = $middleware->handle(makeAccountStatusRequest($user), fn () => response('ok', 200));

    expect($response->getStatusCode())->toBe(423);
    expect($response->getContent())->toContain('account_frozen');
    expect($response->getContent())->toContain('frozen_until');
});

test('EnforceAccountStatus allows active or thawed accounts', function () {
    $middleware = new EnforceAccountStatus();
    $user = User::factory()->make([
        'frozen_until' => Carbon::now()->subMinute(),
        'blacklisted_at' => null,
    ]);

    $response = $middleware->handle(makeAccountStatusRequest($user), fn () => response('ok', 200));

    expect($response->getStatusCode())->toBe(200);
});
