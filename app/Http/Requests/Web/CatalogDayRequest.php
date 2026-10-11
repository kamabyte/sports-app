<?php

namespace App\Http\Requests\Web;

use App\Sports\Catalog\Display;
use App\Sports\Models\Category;
use App\Sports\Models\Competition;
use App\Sports\Models\Sport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Фильтры расписания из адреса: день, вид спорта, раздел, турнир, «только
 * с трансляцией». Прошедших дней нет — расписание начинается с сегодняшнего.
 * Всё в URL: обновление страницы, «назад» и ссылка сохраняют выбор.
 */
class CatalogDayRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'sport' => ['nullable', 'string', 'max:32'],
            'category' => ['nullable', 'string', 'max:64'],
            'competition' => ['nullable', 'integer'],
            'tv' => ['nullable', 'boolean'],
        ];
    }

    /** Выбранный день в часовом поясе зрителя: сегодня или позже. */
    public function day(): CarbonImmutable
    {
        $timezone = config('sports.timezone');
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $date = $this->validated('date');
        $day = $date === null ? $today : CarbonImmutable::createFromFormat('!Y-m-d', $date, $timezone);

        return $day->lt($today) ? $today : $day;
    }

    public function sportKey(): ?string
    {
        return $this->validated('sport');
    }

    public function categoryKey(): ?string
    {
        return $this->validated('category');
    }

    public function competitionId(): ?int
    {
        $id = $this->validated('competition');

        return $id === null ? null : (int) $id;
    }

    /** Выбранный вид спорта; неизвестный ключ — без фильтра. */
    public function sport(): ?Sport
    {
        return $this->sportKey() !== null ? Sport::query()->where('key', $this->sportKey())->first() : null;
    }

    /** Раздел выбранного вида спорта: раздел другого вида спорта не применяется. */
    public function category(?Sport $sport): ?Category
    {
        return $sport !== null && $this->categoryKey() !== null
            ? Category::query()->with(['names' => Display::names()])->where('sport_id', $sport->id)->where('key', $this->categoryKey())->first()
            : null;
    }

    /** Турнир выбранного вида спорта: турнир другого вида спорта не применяется. */
    public function competition(?Sport $sport): ?Competition
    {
        return $sport !== null && $this->competitionId() !== null
            ? Competition::query()->with(['names' => Display::names()])->where('sport_id', $sport->id)->find($this->competitionId())
            : null;
    }

    /** Только события с трансляцией. */
    public function onTv(): bool
    {
        return $this->boolean('tv');
    }
}
