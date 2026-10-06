<?php

namespace App\Http\Requests\Web;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class ScheduleRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'sport' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * Выбранный день в часовом поясе зрителя; по умолчанию — сегодня.
     */
    public function day(): CarbonImmutable
    {
        $date = $this->validated('date');
        $timezone = config('sports.timezone');

        if ($date === null) {
            return CarbonImmutable::now($timezone)->startOfDay();
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $date, $timezone);
    }

    public function sport(): ?string
    {
        return $this->validated('sport');
    }
}
