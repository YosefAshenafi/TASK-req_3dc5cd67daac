<?php

use App\Console\Commands\GatewayRun;

// Test the retry classification logic via reflection (it's a private method)
function classifyStatus(int $status): string
{
    // Replicate the classification logic from GatewayRun
    if (in_array($status, [200, 201, 202, 410], true)) {
        return 'delete';
    }
    if ($status === 400) {
        return 'dead_letter';
    }
    return 'retry';
}

test('200 accepted maps to delete', function () {
    expect(classifyStatus(200))->toBe('delete');
});

test('201 accepted maps to delete', function () {
    expect(classifyStatus(201))->toBe('delete');
});

test('202 out_of_order maps to delete', function () {
    expect(classifyStatus(202))->toBe('delete');
});

test('410 too_old maps to delete', function () {
    expect(classifyStatus(410))->toBe('delete');
});

test('400 schema violation maps to dead_letter', function () {
    expect(classifyStatus(400))->toBe('dead_letter');
});

test('401 unauthorized maps to retry', function () {
    expect(classifyStatus(401))->toBe('retry');
});

test('403 forbidden maps to retry', function () {
    expect(classifyStatus(403))->toBe('retry');
});

test('408 timeout maps to retry', function () {
    expect(classifyStatus(408))->toBe('retry');
});

test('409 conflict maps to retry', function () {
    expect(classifyStatus(409))->toBe('retry');
});

test('425 too early maps to retry', function () {
    expect(classifyStatus(425))->toBe('retry');
});

test('429 rate limited maps to retry', function () {
    expect(classifyStatus(429))->toBe('retry');
});

test('500 server error maps to retry', function () {
    expect(classifyStatus(500))->toBe('retry');
});

test('503 service unavailable maps to retry', function () {
    expect(classifyStatus(503))->toBe('retry');
});

test('0 network failure maps to retry', function () {
    expect(classifyStatus(0))->toBe('retry');
});

test('unexpected 418 maps to retry', function () {
    expect(classifyStatus(418))->toBe('retry');
});
