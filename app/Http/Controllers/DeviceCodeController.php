<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeviceTokenRequest;
use App\Http\Requests\StoreDeviceCodeRequest;
use App\Models\DeviceCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Вход ТВ-клиента по коду (RFC 8628): устройство получает код, человек
 * подтверждает его на /device (там обычный вход с 2FA), устройство
 * опрашивает /device/token и получает токен Sanctum.
 */
class DeviceCodeController extends Controller
{
    public function store(StoreDeviceCodeRequest $request): JsonResponse
    {
        [$code, $secret] = DeviceCode::issue($request->deviceName());
        $url = route('device.show');

        return response()->json([
            'device_code' => $secret,
            'user_code' => $code->displayCode(),
            'verification_url' => $url,
            'verification_url_complete' => $url.'?code='.$code->user_code,
            'expires_in' => DeviceCode::TTL_SECONDS,
            'interval' => DeviceCode::INTERVAL_SECONDS,
        ], Response::HTTP_CREATED);
    }

    public function token(DeviceTokenRequest $request): JsonResponse
    {
        $code = DeviceCode::forDevice($request->deviceCode());

        return match (true) {
            $code === null => $this->error('invalid_grant', 'Код не найден: запросите новый.'),
            $code->expired() => $this->error('expired_token', 'Код истёк: запросите новый.'),
            $code->denied_at !== null => $this->deny($code),
            $code->approved_at === null => $code->pollTooSoon()
                ? $this->error('slow_down', 'Слишком частые запросы: увеличьте интервал на 5 секунд.')
                : $this->error('authorization_pending', 'Ждём подтверждения на сайте.'),
            default => $this->issueToken($code),
        };
    }

    /** Токен выдаётся один раз: запись удаляется, повторный опрос — invalid_grant. */
    private function issueToken(DeviceCode $code): JsonResponse
    {
        if (DeviceCode::query()->whereKey($code->id)->delete() !== 1 || $code->user === null) {
            return $this->error('invalid_grant', 'Код уже использован: запросите новый.');
        }

        return response()->json([
            'token' => $code->user->createToken($code->device_name)->plainTextToken,
            'user' => ['id' => $code->user->id, 'name' => $code->user->name, 'email' => $code->user->email],
        ], Response::HTTP_CREATED);
    }

    private function deny(DeviceCode $code): JsonResponse
    {
        $code->delete();

        return $this->error('access_denied', 'Вход отклонён на сайте.');
    }

    private function error(string $error, string $message): JsonResponse
    {
        return response()->json(['error' => $error, 'message' => $message], Response::HTTP_BAD_REQUEST);
    }
}
