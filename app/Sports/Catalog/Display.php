<?php

namespace App\Sports\Catalog;

use App\Sports\Models\Athlete;
use App\Sports\Models\CatalogModel;
use App\Sports\Models\Category;
use App\Sports\Models\Competition;
use App\Sports\Models\Country;
use App\Sports\Models\Team;
use Closure;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Как сущность каталога показывается пользователю: название на языке
 * интерфейса (если источник или ручная правка его дали — иначе оригинальное),
 * адрес страницы, подписи статусов и признаков команд.
 */
final class Display
{
    /** Разделы вида спорта, которые не могут быть ключом раздела навигации. */
    public const array RESERVED_KEYS = ['competitions', 'national-teams', 'players', 'teams'];

    private const array STATUS = [
        'scheduled' => 'Не начался',
        'live' => 'Идёт',
        'finished' => 'Завершён',
        'postponed' => 'Перенесён',
        'cancelled' => 'Отменён',
        'abandoned' => 'Прерван',
        'interrupted' => 'Остановлен',
        'unknown' => 'Статус неизвестен',
    ];

    private const array STATUS_DETAIL = [
        'after_extra_time' => 'Доп. время',
        'after_penalties' => 'Пенальти',
        'after_overtime' => 'Овертайм',
        'retired' => 'Отказ',
        'walkover' => 'Неявка',
        'awarded' => 'Техн. результат',
    ];

    private const array AGE = ['u23' => 'U23', 'u21' => 'U21', 'u20' => 'U20', 'u19' => 'U19', 'u18' => 'U18', 'u17' => 'U17'];

    public static function locale(): string
    {
        return (string) config('sports.catalog.locale', 'ru');
    }

    /**
     * Ограничение для with(['names' => …]): только официальное название на языке интерфейса.
     *
     * @return Closure(Relation<*, *, *>): void
     */
    public static function names(): Closure
    {
        return function (Relation $query): void {
            $query->where('locale', self::locale())->where('kind', 'official');
        };
    }

    /** Название на языке интерфейса — если оно известно, иначе как у источника. */
    public static function name(?CatalogModel $model): ?string
    {
        if ($model === null) {
            return null;
        }

        $original = (string) $model->getAttribute('name');
        if (! $model->relationLoaded('names')) {
            return $original;
        }

        $local = $model->getRelation('names')->firstWhere('locale', self::locale());

        return $local?->getAttribute('name') ?? $original;
    }

    /** Оригинальное название, если оно отличается от показанного (для подписи «Arsenal»). */
    public static function originalName(CatalogModel $model): ?string
    {
        $shown = self::name($model);
        $original = (string) $model->getAttribute('name');

        return $shown !== $original ? $original : null;
    }

    public static function status(string $status): string
    {
        return self::STATUS[$status] ?? self::STATUS['unknown'];
    }

    public static function statusDetail(?string $detail): ?string
    {
        return $detail === null ? null : (self::STATUS_DETAIL[$detail] ?? null);
    }

    /**
     * Признаки команды для бейджей: клуб/сборная, пол, возраст, олимпийская.
     *
     * @return list<string>
     */
    public static function teamBadges(Team $team): array
    {
        return array_values(array_filter([
            match ($team->kind) {
                'national' => 'Сборная', 'club' => 'Клуб', default => null
            },
            match ($team->gender) {
                'women' => 'Женщины', 'men' => $team->kind === 'national' ? 'Мужчины' : null, 'mixed' => 'Смешанная', default => null
            },
            self::AGE[$team->age_group] ?? null,
            $team->squad === 'olympic' ? 'Олимпийская' : null,
            $team->squad === 'reserve' ? 'Резерв' : null,
        ]));
    }

    /**
     * Признаки турнира: кто участвует и в каком разряде.
     *
     * @return list<string>
     */
    public static function competitionBadges(Competition $competition): array
    {
        return array_values(array_filter([
            $competition->participant_kind === 'national' ? 'Сборные' : null,
            match ($competition->gender) {
                'women' => 'Женщины', 'mixed' => 'Смешанный', default => null
            },
            self::AGE[$competition->age_group] ?? null,
            match ($competition->entry_format) {
                'singles' => 'Одиночный разряд', 'doubles' => 'Парный разряд', 'mixed_doubles' => 'Микст', default => null
            },
        ]));
    }

    public static function sportUrl(string $sport): string
    {
        return "/sport/{$sport}";
    }

    public static function categoryUrl(string $sport, Category $category): string
    {
        return "/sport/{$sport}/{$category->key}";
    }

    public static function competitionUrl(string $sport, Competition $competition): string
    {
        return "/sport/{$sport}/{$competition->primaryCategory->key}/{$competition->slug}";
    }

    public static function teamUrl(Team $team): string
    {
        return "/team/{$team->id}/{$team->slug}";
    }

    public static function athleteUrl(Athlete $athlete): string
    {
        return "/player/{$athlete->id}/{$athlete->slug}";
    }

    public static function eventUrl(int $id): string
    {
        return "/event/{$id}";
    }

    public static function flag(?Country $country): ?string
    {
        return $country?->relationLoaded('flag') ? $country->flag?->url : null;
    }
}
