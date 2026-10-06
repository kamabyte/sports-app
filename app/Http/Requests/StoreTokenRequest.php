<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Вход ТВ-клиента: email и пароль той же учётной записи, что и в вебе.
 */
class StoreTokenRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ];
    }

    public function email(): string
    {
        return mb_strtolower($this->validated('email'));
    }

    public function password(): string
    {
        return $this->validated('password');
    }

    public function deviceName(): string
    {
        return $this->validated('device_name');
    }
}
