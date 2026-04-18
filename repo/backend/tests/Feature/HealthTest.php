<?php

test('health endpoint returns ok', function () {
    $this->getJson('/api/health')
        ->assertStatus(200)
        ->assertJsonPath('status', 'ok');
});
