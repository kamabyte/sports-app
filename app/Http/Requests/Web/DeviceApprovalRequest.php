<?php

namespace App\Http\Requests\Web;

use App\Models\DeviceCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Решение по коду входа ТВ: подключить или отклонить. Код должен ждать
 * решения и не истечь.
 */
class DeviceApprovalRequest extends FormRequest
{
    private ?DeviceCode $deviceCode = null;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_code' => ['required', 'string', 'max:16'],
            'approve' => ['required', 'boolean'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isEmpty() && $this->deviceCode() === null) {
                $validator->errors()->add('user_code', 'Код не найден или истёк. Запросите новый на телевизоре.');
            }
        }];
    }

    public function deviceCode(): ?DeviceCode
    {
        return $this->deviceCode ??= DeviceCode::pending((string) $this->input('user_code'));
    }

    public function approves(): bool
    {
        return $this->boolean('approve');
    }
}
