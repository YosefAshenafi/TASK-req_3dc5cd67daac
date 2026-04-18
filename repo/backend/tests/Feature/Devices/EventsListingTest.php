<?php

use App\Models\Device;
use App\Models\DeviceEvent;
use App\Models\User;

test('device events endpoint filters by status', function () {
    $tech  = User::factory()->technician()->create();
    $token = $tech->createToken('test')->plainTextToken;

    $device = Device::create([
        'id' => 'dev-filter-1',
        'kind' => 'camera',
        'label' => 'Test',
        'last_sequence_no' => 0,
        'last_seen_at' => now(),
    ]);

    DeviceEvent::create([
        'device_id' => $device->id,
        'event_type' => 'gate.opened',
        'sequence_no' => 1,
        'idempotency_key' => 'key-1',
        'occurred_at' => now(),
        'received_at' => now(),
        'is_out_of_order' => false,
        'status' => 'accepted',
    ]);

    DeviceEvent::create([
        'device_id' => $device->id,
        'event_type' => 'gate.opened',
        'sequence_no' => 0,
        'idempotency_key' => 'key-2',
        'occurred_at' => now(),
        'received_at' => now(),
        'is_out_of_order' => true,
        'status' => 'out_of_order',
    ]);

    // No filter → both
    $all = $this->withToken($token)->getJson("/api/devices/{$device->id}/events");
    $all->assertStatus(200);
    expect(count($all->json('items')))->toEqual(2);

    // Filter to accepted only
    $accepted = $this->withToken($token)->getJson("/api/devices/{$device->id}/events?status=accepted");
    $accepted->assertStatus(200);
    $statuses = collect($accepted->json('items'))->pluck('status')->unique()->toArray();
    expect($statuses)->toEqual(['accepted']);

    // Filter to out_of_order
    $ooo = $this->withToken($token)->getJson("/api/devices/{$device->id}/events?status=out_of_order");
    $oooStatuses = collect($ooo->json('items'))->pluck('status')->unique()->toArray();
    expect($oooStatuses)->toEqual(['out_of_order']);
});

test('device events cursor pagination advances through events without repeats', function () {
    $tech  = User::factory()->technician()->create();
    $token = $tech->createToken('test')->plainTextToken;

    $device = Device::create([
        'id' => 'dev-cursor-1',
        'kind' => 'camera',
        'label' => 'Test',
        'last_sequence_no' => 0,
        'last_seen_at' => now(),
    ]);

    for ($i = 0; $i < 6; $i++) {
        DeviceEvent::create([
            'device_id' => $device->id,
            'event_type' => 'tick',
            'sequence_no' => $i,
            'idempotency_key' => "key-{$i}",
            'occurred_at' => now()->subSeconds(10 - $i),
            'received_at' => now(),
            'is_out_of_order' => false,
            'status' => 'accepted',
        ]);
    }

    $page1 = $this->withToken($token)->getJson("/api/devices/{$device->id}/events?limit=3");
    $page1->assertStatus(200);
    $page1Ids = collect($page1->json('items'))->pluck('id')->toArray();
    expect(count($page1Ids))->toEqual(3);

    $cursor = $page1->json('next_cursor');
    expect($cursor)->not->toBeNull();

    $page2 = $this->withToken($token)->getJson("/api/devices/{$device->id}/events?limit=3&cursor={$cursor}");
    $page2Ids = collect($page2->json('items'))->pluck('id')->toArray();

    expect(array_intersect($page1Ids, $page2Ids))->toBeEmpty();
});
