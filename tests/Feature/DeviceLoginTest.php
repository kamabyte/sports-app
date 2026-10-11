<?php

use App\Models\DeviceCode;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Вход ТВ-клиента по коду (RFC 8628, docs/api.md): код на экране,
 * подтверждение на сайте после обычного входа, токен — устройству один раз.
 */

/**
 * @return array{device_code: string, user_code: string}
 */
function requestDeviceCode(string $name = 'Apple TV в гостиной'): array
{
    return test()->postJson('/api/v1/device/codes', ['device_name' => $name])
        ->assertCreated()
        ->assertJsonPath('expires_in', 600)
        ->assertJsonPath('interval', 5)
        ->json();
}

it('signs a TV in after the code is approved on the site', function (): void {
    $code = requestDeviceCode();
    expect($code['user_code'])->toMatch('/^[BCDFGHJKLMNPQRSTVWXZ]{4}-[BCDFGHJKLMNPQRSTVWXZ]{4}$/');
    $plain = str_replace('-', '', $code['user_code']);
    expect(DeviceCode::query()->sole()->device_code)->not->toBe($code['device_code']);

    $this->postJson('/api/v1/device/token', ['device_code' => $code['device_code']])
        ->assertStatus(400)
        ->assertJsonPath('error', 'authorization_pending');

    $user = User::factory()->create(['email' => 'fan@example.com']);
    $this->actingAs($user);
    // Регистр и дефис не важны; на странице — имя устройства для сверки.
    $this->get('/device?code='.strtolower($plain))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/device')
            ->where('code', $code['user_code'])
            ->where('device.name', 'Apple TV в гостиной')
            ->where('invalid', false));
    $this->post('/device', ['user_code' => $code['user_code'], 'approve' => true])
        ->assertRedirect('/device')
        ->assertSessionHas('status', 'approved');

    $this->travel(6)->seconds();
    $token = $this->postJson('/api/v1/device/token', ['device_code' => $code['device_code']])
        ->assertCreated()
        ->assertJsonPath('user.email', 'fan@example.com')
        ->json('token');

    expect($user->tokens()->sole()->name)->toBe('Apple TV в гостиной');
    app('auth')->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('user.email', 'fan@example.com');

    // Токен — один раз.
    $this->postJson('/api/v1/device/token', ['device_code' => $code['device_code']])
        ->assertStatus(400)
        ->assertJsonPath('error', 'invalid_grant');
    expect(DeviceCode::query()->count())->toBe(0);
});

it('asks a device that polls too often to slow down', function (): void {
    $code = requestDeviceCode();

    $this->postJson('/api/v1/device/token', ['device_code' => $code['device_code']])->assertJsonPath('error', 'authorization_pending');
    $this->postJson('/api/v1/device/token', ['device_code' => $code['device_code']])->assertJsonPath('error', 'slow_down');
    $this->travel(5)->seconds();
    $this->postJson('/api/v1/device/token', ['device_code' => $code['device_code']])->assertJsonPath('error', 'authorization_pending');
});

it('tells the device when the code is denied or expired', function (): void {
    $denied = requestDeviceCode();
    $expired = requestDeviceCode('Android TV');
    $this->actingAs(User::factory()->create());

    $this->post('/device', ['user_code' => $denied['user_code'], 'approve' => false])->assertSessionHas('status', 'denied');
    $this->postJson('/api/v1/device/token', ['device_code' => $denied['device_code']])->assertJsonPath('error', 'access_denied');
    $this->postJson('/api/v1/device/token', ['device_code' => $denied['device_code']])->assertJsonPath('error', 'invalid_grant');

    $this->travel(601)->seconds();
    $this->postJson('/api/v1/device/token', ['device_code' => $expired['device_code']])->assertJsonPath('error', 'expired_token');
    $this->get('/device?code='.$expired['user_code'])
        ->assertInertia(fn (Assert $page) => $page->where('device', null)->where('invalid', true));
    $this->post('/device', ['user_code' => $expired['user_code'], 'approve' => true])->assertSessionHasErrors('user_code');

    expect(User::query()->first()->tokens()->count())->toBe(0);
});

it('cannot approve a code twice or without signing in', function (): void {
    $code = requestDeviceCode();

    $this->get('/device?code='.$code['user_code'])->assertRedirect('/login');
    $this->post('/device', ['user_code' => $code['user_code'], 'approve' => true])->assertRedirect('/login');

    $this->actingAs(User::factory()->create());
    $this->post('/device', ['user_code' => $code['user_code'], 'approve' => true])->assertSessionHas('status', 'approved');
    $this->post('/device', ['user_code' => $code['user_code'], 'approve' => false])->assertSessionHasErrors('user_code');

    $this->postJson('/api/v1/device/codes', [])->assertUnprocessable()->assertJsonValidationErrors('device_name');
    $this->postJson('/api/v1/device/token', ['device_code' => 'nope'])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
});
