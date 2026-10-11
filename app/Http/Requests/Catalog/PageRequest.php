<?php

namespace App\Http\Requests\Catalog;

use App\Sports\Catalog\Paging;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Длинные списки API порциями: limit растёт на шаг, more — есть ли ещё.
 */
class PageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'limit' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function limit(int $step = Paging::STEP): int
    {
        return Paging::limit($this, $step);
    }
}
