<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Запрос кода входа: имя устройства — оно же имя будущего токена.
 */
class StoreDeviceCodeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'device_name' => ['required', 'string', 'max:255'],
        ];
    }

    public function deviceName(): string
    {
        return (string) $this->validated('device_name');
    }
}
