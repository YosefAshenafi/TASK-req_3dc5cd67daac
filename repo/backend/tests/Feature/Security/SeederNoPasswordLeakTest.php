<?php

use App\Models\FeatureFlag;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

test('seeder does not write plaintext bootstrap passwords to the log stream', function () {
    $secretsDir = storage_path('app/bootstrap-secrets');
    if (is_dir($secretsDir)) {
        File::deleteDirectory($secretsDir);
    }

    $originalEnv = app()->environment();
    $this->app->detectEnvironment(fn () => 'production');
    config(['app.env' => 'production']);
    putenv('SEED_DEFAULT_ACCOUNTS=true');
    putenv('ADMIN_BOOTSTRAP_PASSWORD');
    putenv('USER_BOOTSTRAP_PASSWORD');
    putenv('TECH_BOOTSTRAP_PASSWORD');

    // Ensure a clean users table so the seeder actually enters the generated-password
    // branch (it skips accounts that already exist).
    User::query()->delete();
    FeatureFlag::query()->delete();

    $capturedLines = [];
    Log::listen(function ($message) use (&$capturedLines) {
        $capturedLines[] = (string) ($message->message ?? '');
    });

    try {
        (new DatabaseSeeder())->run();
    } finally {
        $this->app->detectEnvironment(fn () => $originalEnv);
        config(['app.env' => $originalEnv]);
        putenv('SEED_DEFAULT_ACCOUNTS');
    }

    // 1. Three user rows were created.
    expect(User::count())->toBe(3);

    // 2. Each account's stored hash does not match the obvious "password" literal.
    foreach (['admin', 'user1', 'tech1'] as $username) {
        $user = User::where('username', $username)->firstOrFail();
        expect(Hash::check('password', $user->password))->toBeFalse();
    }

    // 3. Each account received an out-of-band secret file (mode 0600 on supported filesystems).
    foreach (['admin', 'user1', 'tech1'] as $username) {
        $file = storage_path("app/bootstrap-secrets/{$username}.txt");
        expect(file_exists($file))->toBeTrue();
        $written = trim((string) @file_get_contents($file));
        expect($written)->not->toBe('');
        expect(Hash::check($written, User::where('username', $username)->firstOrFail()->password))->toBeTrue();
    }

    // 4. The generated password string never appears in any captured log line.
    foreach ($capturedLines as $line) {
        expect($line)->not->toMatch('/bootstrap password for [\'"].+[\'"]: .+/i');
        foreach (['admin', 'user1', 'tech1'] as $username) {
            $secret = trim((string) @file_get_contents(storage_path("app/bootstrap-secrets/{$username}.txt")));
            if ($secret !== '') {
                expect(str_contains($line, $secret))->toBeFalse();
            }
        }
    }

    // Cleanup bootstrap-secrets so subsequent test runs do not see stale files.
    if (is_dir($secretsDir)) {
        File::deleteDirectory($secretsDir);
    }
});
