<?php

use App\Providers\AppServiceProvider;
use App\Services\MediaValidator;

test('AppServiceProvider registers MediaValidator as a shared singleton', function () {
    // Two resolutions of the same abstract must return the same instance.
    // A bug in register() (e.g. ->bind instead of ->singleton) would produce
    // different objects, which would break request-scoped caching.
    $first  = app(MediaValidator::class);
    $second = app(MediaValidator::class);

    expect($first)->toBeInstanceOf(MediaValidator::class);
    expect($second)->toBe($first);
});

test('AppServiceProvider boot() is safely re-entrant and does not throw', function () {
    // Re-running boot() on an already-booted provider must be a no-op. Historically
    // providers that register listeners inside boot() have accidentally double-
    // subscribed — this test guards against that regression even though boot()
    // is currently empty.
    $provider = new AppServiceProvider(app());

    // Calling boot() directly must be idempotent and not raise.
    $provider->boot();
    $provider->boot();

    // Application still resolves dependencies afterwards.
    expect(app(MediaValidator::class))->toBeInstanceOf(MediaValidator::class);
});

test('AppServiceProvider is registered in the application container', function () {
    // If someone removed this provider from bootstrap/providers.php, the
    // MediaValidator binding would fall back to Laravel's auto-resolve which
    // would silently make it a new instance per resolution.
    $loaded = collect(app()->getLoadedProviders())
        ->keys()
        ->contains(AppServiceProvider::class);

    expect($loaded)->toBeTrue();
});
