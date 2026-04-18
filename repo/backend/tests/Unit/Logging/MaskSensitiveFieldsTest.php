<?php

use App\Logging\MaskSensitiveFields;
use Monolog\Level;
use Monolog\LogRecord;

function recordWith(array $context): LogRecord
{
    return new LogRecord(
        new \DateTimeImmutable(),
        'test',
        Level::Info,
        'msg',
        $context,
    );
}

test('password is redacted', function () {
    $out = (new MaskSensitiveFields())(recordWith(['password' => 'hunter2']));
    expect($out->context['password'])->toBe('[REDACTED]');
});

test('idempotency_key is redacted', function () {
    // Device ingest logs include idempotency_key on error paths — it must not survive.
    $out = (new MaskSensitiveFields())(recordWith(['idempotency_key' => 'c1d2-abcd']));
    expect($out->context['idempotency_key'])->toBe('[REDACTED]');
});

test('session and session_id are redacted', function () {
    $out = (new MaskSensitiveFields())(recordWith([
        'session'    => 'abc123',
        'session_id' => 'sess_xyz',
    ]));
    expect($out->context['session'])->toBe('[REDACTED]');
    expect($out->context['session_id'])->toBe('[REDACTED]');
});

test('authorization header value is redacted', function () {
    $out = (new MaskSensitiveFields())(recordWith(['authorization' => 'Bearer abc.def.ghi']));
    expect($out->context['authorization'])->toBe('[REDACTED]');
});

test('x-gateway-token header variant is redacted', function () {
    $out = (new MaskSensitiveFields())(recordWith(['x-gateway-token' => 'shared-secret']));
    expect($out->context['x-gateway-token'])->toBe('[REDACTED]');
});

test('plate in payload is redacted', function () {
    $out = (new MaskSensitiveFields())(recordWith(['plate' => 'ABC-1234']));
    expect($out->context['plate'])->toBe('[REDACTED]');
});

test('nested sensitive keys are redacted', function () {
    $out = (new MaskSensitiveFields())(recordWith([
        'request' => [
            'headers' => ['authorization' => 'Bearer token'],
            'body'    => ['password' => 'abc', 'username' => 'alice'],
        ],
    ]));
    expect($out->context['request']['headers']['authorization'])->toBe('[REDACTED]');
    expect($out->context['request']['body']['password'])->toBe('[REDACTED]');
    expect($out->context['request']['body']['username'])->toBe('alice');
});

test('key containing sensitive substring is redacted even when not in exact list', function () {
    // 'csrf_token' is not in MASKED_KEYS but contains 'token' — hits the substring path
    $out = (new MaskSensitiveFields())(recordWith(['csrf_token' => 'abc123', 'refresh_token' => 'xyz']));
    expect($out->context['csrf_token'])->toBe('[REDACTED]');
    expect($out->context['refresh_token'])->toBe('[REDACTED]');
});

test('non-sensitive context is preserved', function () {
    $out = (new MaskSensitiveFields())(recordWith(['device_id' => 'gate-01', 'event_type' => 'gate.opened']));
    expect($out->context['device_id'])->toBe('gate-01');
    expect($out->context['event_type'])->toBe('gate.opened');
});
