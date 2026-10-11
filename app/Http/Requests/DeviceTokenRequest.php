<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Опрос устройства: секрет, полученный вместе с кодом.
 */
class DeviceTokenRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'device_code' => ['required', 'string', 'max:255'],
        ];
    }

    public function deviceCode(): string
    {
        return (string) $this->validated('device_code');
    }
}
