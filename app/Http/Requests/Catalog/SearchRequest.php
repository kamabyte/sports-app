<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Строка поиска API: по названиям каталога или по именам спортсменов вида спорта.
 */
class SearchRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function searchQuery(): string
    {
        return trim((string) $this->validated('q'));
    }
}
