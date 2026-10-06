<?php

namespace App\Http\Requests;

use App\Guide\Data\EventStatus;
use App\Guide\EventQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET /api/events: sport, competition, status (через запятую), date (день
 * в часовом поясе sports.timezone), q — поиск по командам и турнирам.
 */
class EventIndexRequest extends FormRequest
{
    private const int MAX_RESULTS = 200;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sport' => ['nullable', 'string', 'max:32'],
            'competition' => ['nullable', 'string', 'max:32'],
            'status' => ['nullable', 'array'],
            'status.*' => [Rule::enum(EventStatus::class)],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('status'))) {
            $this->merge(['status' => explode(',', $this->input('status'))]);
        }
    }

    public function toQuery(): EventQuery
    {
        $day = $this->day();

        return new EventQuery(
            sport: $this->validated('sport'),
            competition: $this->validated('competition'),
            statuses: $this->statuses(),
            from: $day?->startOfDay(),
            to: $day?->endOfDay(),
            search: $this->validated('q'),
            limit: self::MAX_RESULTS,
        );
    }

    /**
     * @return list<EventStatus>
     */
    private function statuses(): array
    {
        return array_values(array_map(EventStatus::from(...), $this->validated('status') ?? []));
    }

    private function day(): ?CarbonImmutable
    {
        $date = $this->validated('date');

        return $date ? CarbonImmutable::createFromFormat('!Y-m-d', $date, config('sports.timezone')) : null;
    }
}
