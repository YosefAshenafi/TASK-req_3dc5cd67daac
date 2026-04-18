<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

afterEach(function () {
    putenv('GATEWAY_BUFFER_PATH');
    unset($_ENV['GATEWAY_BUFFER_PATH'], $_SERVER['GATEWAY_BUFFER_PATH']);
});

test('gateway:dead-letter fails when buffer sqlite missing', function () {
    putenv('GATEWAY_BUFFER_PATH=/tmp/does-not-exist-' . uniqid() . '.sqlite');
    $_ENV['GATEWAY_BUFFER_PATH'] = getenv('GATEWAY_BUFFER_PATH');

    $code = Artisan::call('gateway:dead-letter');

    expect($code)->toBe(1);
});

test('gateway:dead-letter lists empty dead letter table', function () {
    $path = sys_get_temp_dir() . '/gw_dl_' . uniqid() . '.sqlite';
    File::delete($path);

    putenv("GATEWAY_BUFFER_PATH={$path}");
    $_ENV['GATEWAY_BUFFER_PATH'] = $path;

    $pdo = new PDO("sqlite:{$path}");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('
        CREATE TABLE dead_letter (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            idempotency_key TEXT NOT NULL,
            payload TEXT NOT NULL,
            http_status INTEGER,
            reason TEXT,
            dead_lettered_at INTEGER NOT NULL,
            created_at INTEGER NOT NULL
        )
    ');

    $code = Artisan::call('gateway:dead-letter', ['--list' => true]);

    expect($code)->toBe(0);
    expect(Artisan::output())->toContain('No dead-lettered');

    File::delete($path);
});

test('gateway:dead-letter shows usage hint when no option given', function () {
    $path = sys_get_temp_dir() . '/gw_dl_' . uniqid() . '.sqlite';
    File::delete($path);

    putenv("GATEWAY_BUFFER_PATH={$path}");
    $_ENV['GATEWAY_BUFFER_PATH'] = $path;

    $pdo = new PDO("sqlite:{$path}");
    $pdo->exec('
        CREATE TABLE dead_letter (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            idempotency_key TEXT NOT NULL,
            payload TEXT NOT NULL,
            http_status INTEGER,
            reason TEXT,
            dead_lettered_at INTEGER NOT NULL,
            created_at INTEGER NOT NULL
        )
    ');

    $code = Artisan::call('gateway:dead-letter');

    expect($code)->toBe(0);
    expect(Artisan::output())->toContain('--list');

    File::delete($path);
});

test('gateway:dead-letter lists rows when dead letter table has entries', function () {
    $path = sys_get_temp_dir() . '/gw_dl_' . uniqid() . '.sqlite';
    File::delete($path);

    putenv("GATEWAY_BUFFER_PATH={$path}");
    $_ENV['GATEWAY_BUFFER_PATH'] = $path;

    $pdo = new PDO("sqlite:{$path}");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('
        CREATE TABLE dead_letter (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            idempotency_key TEXT NOT NULL,
            payload TEXT NOT NULL,
            http_status INTEGER,
            reason TEXT,
            dead_lettered_at INTEGER NOT NULL,
            created_at INTEGER NOT NULL
        )
    ');

    $key = 'k-' . uniqid();
    $pdo->prepare(
        'INSERT INTO dead_letter (idempotency_key, payload, http_status, reason, dead_lettered_at, created_at) VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([$key, '{}', 400, 'schema_violation', time(), time()]);

    $code = Artisan::call('gateway:dead-letter', ['--list' => true]);

    expect($code)->toBe(0);

    File::delete($path);
});

test('gateway:dead-letter requeue returns failure when key not found', function () {
    $path = sys_get_temp_dir() . '/gw_dl_' . uniqid() . '.sqlite';
    File::delete($path);

    putenv("GATEWAY_BUFFER_PATH={$path}");
    $_ENV['GATEWAY_BUFFER_PATH'] = $path;

    $pdo = new PDO("sqlite:{$path}");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('
        CREATE TABLE dead_letter (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            idempotency_key TEXT NOT NULL,
            payload TEXT NOT NULL,
            http_status INTEGER,
            reason TEXT,
            dead_lettered_at INTEGER NOT NULL,
            created_at INTEGER NOT NULL
        )
    ');
    $pdo->exec('
        CREATE TABLE buffered_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            idempotency_key TEXT NOT NULL UNIQUE,
            payload TEXT NOT NULL,
            attempts INTEGER NOT NULL DEFAULT 0,
            retry_count INTEGER NOT NULL DEFAULT 0,
            next_retry_at INTEGER NOT NULL DEFAULT 0,
            last_error TEXT,
            last_attempted_at INTEGER,
            created_at INTEGER NOT NULL
        )
    ');

    $code = Artisan::call('gateway:dead-letter', ['--requeue' => 'nonexistent-key-' . uniqid()]);

    expect($code)->toBe(1);

    File::delete($path);
});

test('gateway:dead-letter requeues dead letter row', function () {
    $path = sys_get_temp_dir() . '/gw_dl_' . uniqid() . '.sqlite';
    File::delete($path);

    putenv("GATEWAY_BUFFER_PATH={$path}");
    $_ENV['GATEWAY_BUFFER_PATH'] = $path;

    $pdo = new PDO("sqlite:{$path}");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('
        CREATE TABLE dead_letter (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            idempotency_key TEXT NOT NULL,
            payload TEXT NOT NULL,
            http_status INTEGER,
            reason TEXT,
            dead_lettered_at INTEGER NOT NULL,
            created_at INTEGER NOT NULL
        )
    ');
    $pdo->exec('
        CREATE TABLE buffered_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            idempotency_key TEXT NOT NULL UNIQUE,
            payload TEXT NOT NULL,
            attempts INTEGER NOT NULL DEFAULT 0,
            retry_count INTEGER NOT NULL DEFAULT 0,
            next_retry_at INTEGER NOT NULL DEFAULT 0,
            last_error TEXT,
            last_attempted_at INTEGER,
            created_at INTEGER NOT NULL
        )
    ');

    $key = 'k-' . uniqid();
    $payload = json_encode(['device_id' => 'd1', 'idempotency_key' => $key]);
    $stmt = $pdo->prepare(
        'INSERT INTO dead_letter (idempotency_key, payload, http_status, reason, dead_lettered_at, created_at) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$key, $payload, 400, 'bad', time(), time()]);

    $code = Artisan::call('gateway:dead-letter', ['--requeue' => $key]);

    expect($code)->toBe(0);

    $count = (int) $pdo->query('SELECT COUNT(*) FROM dead_letter')->fetchColumn();
    expect($count)->toBe(0);

    File::delete($path);
});
