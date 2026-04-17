<?php

use App\Casts\EncryptedField;
use App\Models\User;

test('EncryptedField round-trips email value', function () {
    $cast  = new EncryptedField();
    $model = new User();

    $plaintext  = 'test@example.com';
    $ciphertext = $cast->set($model, 'email_enc', $plaintext, []);

    expect($ciphertext)->not->toEqual($plaintext);
    expect($ciphertext)->not->toBeNull();

    $decrypted = $cast->get($model, 'email_enc', $ciphertext, []);
    expect($decrypted)->toEqual($plaintext);
});

test('EncryptedField handles null values', function () {
    $cast  = new EncryptedField();
    $model = new User();

    expect($cast->set($model, 'email_enc', null, []))->toBeNull();
    expect($cast->get($model, 'email_enc', null, []))->toBeNull();
});

test('EncryptedField ciphertext does not contain plaintext', function () {
    $cast  = new EncryptedField();
    $model = new User();

    $plaintext  = 'secret@domain.com';
    $ciphertext = $cast->set($model, 'email_enc', $plaintext, []);

    expect($ciphertext)->not->toContain($plaintext);
    expect($ciphertext)->not->toContain('secret');
});

test('User model encrypts email_enc at rest', function () {
    $user = User::factory()->create(['email_enc' => 'admin@test.local']);

    $rawRecord = \Illuminate\Support\Facades\DB::table('users')
        ->where('id', $user->id)
        ->first();

    expect($rawRecord->email_enc)->not->toEqual('admin@test.local');
    expect($user->email_enc)->toEqual('admin@test.local');
});

test('UserFactory creates model with all relationships', function () {
    $user = User::factory()->create();

    expect($user->id)->not->toBeNull();
    expect($user->username)->not->toBeNull();
    expect($user->role)->toBeIn(['user', 'admin', 'technician']);
    expect($user->favorites)->not->toBeNull();
    expect($user->playlists)->not->toBeNull();
    expect($user->playHistory)->not->toBeNull();
});
