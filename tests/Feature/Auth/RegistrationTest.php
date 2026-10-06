<?php

use App\Models\User;

it('has no public registration', function (): void {
    // Учётные записи заводятся командой php artisan sports:user.
    $this->get('/register')->assertNotFound();
    $this->post('/register', ['email' => 'x@example.com'])->assertNotFound();
});

it('creates a verified account with the sports:user command', function (): void {
    $this->artisan('sports:user', ['email' => 'Fan@Example.com', '--password' => 'secret123'])
        ->assertSuccessful();

    $user = User::query()->where('email', 'fan@example.com')->sole();
    expect($user->name)->toBe('fan')
        ->and($user->email_verified_at)->not->toBeNull();

    $this->post('/login', ['email' => 'fan@example.com', 'password' => 'secret123']);
    $this->assertAuthenticatedAs($user);
});

it('resets the password of an existing account', function (): void {
    $user = User::factory()->create(['email' => 'fan@example.com', 'name' => 'Fan']);

    $this->artisan('sports:user', ['email' => 'fan@example.com', '--password' => 'new-secret'])
        ->assertSuccessful();

    expect(Hash::check('new-secret', $user->fresh()->password))->toBeTrue()
        ->and($user->fresh()->name)->toBe('Fan');
});
