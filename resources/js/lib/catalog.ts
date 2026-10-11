import { usePage } from '@inertiajs/react';
import type { CatalogShared, EventItem } from '@/types/catalog';

const LOCALE = 'ru-RU';

/** Значок вида спорта в меню — эмодзи узнаются быстрее подписи. */
export const SPORT_EMOJI: Record<string, string> = {
    football: '⚽',
    tennis: '🎾',
    basketball: '🏀',
    hockey: '🏒',
    volleyball: '🏐',
    handball: '🤾',
    baseball: '⚾',
    'american-football': '🏈',
    'rugby-union': '🏉',
    'rugby-league': '🏉',
    cricket: '🏏',
    'table-tennis': '🏓',
    badminton: '🏸',
    snooker: '🎱',
    darts: '🎯',
    boxing: '🥊',
    mma: '🥋',
    esports: '🎮',
    motorsport: '🏎️',
    golf: '⛳',
    cycling: '🚴',
    'winter-sports': '⛷️',
};

export function useCatalog(): CatalogShared {
    return (
        usePage().props.catalog ?? {
            sports: [],
            updated_at: null,
            stale: false,
        }
    );
}

/** Адрес с параметрами; пустые и значения по умолчанию не пишутся. */
export function withQuery(
    path: string,
    params: Record<string, string | number | null | undefined>,
): string {
    const query = new URLSearchParams();
    for (const [key, value] of Object.entries(params)) {
        if (value !== null && value !== undefined && value !== '') {
            query.set(key, String(value));
        }
    }
    const string = query.toString();

    return string ? `${path}?${string}` : path;
}

/** «2026-10-07» в часовом поясе зрителя. */
export function todayKey(timeZone: string): string {
    return new Intl.DateTimeFormat('en-CA', { timeZone }).format(new Date());
}

export function shiftDate(date: string, days: number): string {
    const value = new Date(`${date}T12:00:00Z`);
    value.setUTCDate(value.getUTCDate() + days);

    return value.toISOString().slice(0, 10);
}

export function formatTime(iso: string, timeZone: string): string {
    return new Intl.DateTimeFormat(LOCALE, {
        hour: '2-digit',
        minute: '2-digit',
        timeZone,
    }).format(new Date(iso));
}

/** «Сегодня», «Завтра», «Вчера» или «чт, 9 октября». */
export function formatDay(date: string, timeZone: string): string {
    const today = todayKey(timeZone);
    if (date === today) return 'Сегодня';
    if (date === shiftDate(today, 1)) return 'Завтра';
    if (date === shiftDate(today, -1)) return 'Вчера';

    return new Intl.DateTimeFormat(LOCALE, {
        weekday: 'short',
        day: 'numeric',
        month: 'long',
        timeZone: 'UTC',
    }).format(new Date(`${date}T12:00:00Z`));
}

/** «среда, 7 октября 2026». */
export function formatLongDate(date: string): string {
    return new Intl.DateTimeFormat(LOCALE, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${date}T12:00:00Z`));
}

/** Короткая подпись для полосы дней: «Сегодня» / «пт» и «9 окт.». */
export function dayChip(
    date: string,
    timeZone: string,
): { top: string; bottom: string } {
    const day = new Date(`${date}T12:00:00Z`);
    const relative = formatDay(date, timeZone);
    const bottom = new Intl.DateTimeFormat(LOCALE, {
        day: 'numeric',
        month: 'short',
        timeZone: 'UTC',
    }).format(day);

    if (['Сегодня', 'Завтра', 'Вчера'].includes(relative)) {
        return { top: relative, bottom };
    }

    return {
        top: new Intl.DateTimeFormat(LOCALE, {
            weekday: 'short',
            timeZone: 'UTC',
        }).format(day),
        bottom,
    };
}

/** Часовой пояс зрителя словами: «МСК (UTC+3)» для Москвы, иначе «GMT+2». */
export function zoneLabel(timeZone: string): string {
    const parts = new Intl.DateTimeFormat(LOCALE, {
        timeZone,
        timeZoneName: 'short',
    }).formatToParts(new Date());

    return (
        parts.find((part) => part.type === 'timeZoneName')?.value ?? timeZone
    );
}

/** Время события в строке: «19:30», а без точного времени — не полночь. */
export function eventTime(event: EventItem, timeZone: string): string | null {
    if (event.time_precision !== 'exact' || !event.starts_at) {
        return null;
    }

    return formatTime(event.starts_at, timeZone);
}

/** День события в часовом поясе зрителя. */
export function eventDay(event: EventItem, timeZone: string): string | null {
    if (event.time_precision === 'exact' && event.starts_at) {
        return new Intl.DateTimeFormat('en-CA', { timeZone }).format(
            new Date(event.starts_at),
        );
    }

    return event.date;
}

/** Название события: «Хозяева — Гости» или имя гонки/сессии, иначе турнир. */
export function eventTitle(event: EventItem): string {
    return event.sides
        ? event.sides
              .map(
                  (side) =>
                      side.participant?.name ?? side.label ?? 'Уточняется',
              )
              .join(' — ')
        : (event.name ?? event.competition.name);
}

export function isLive(event: { status: string }): boolean {
    return event.status === 'live' || event.status === 'interrupted';
}

export function pluralize(
    count: number,
    one: string,
    few: string,
    many: string,
): string {
    const mod10 = count % 10;
    const mod100 = count % 100;
    const word =
        mod10 === 1 && mod100 !== 11
            ? one
            : mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)
              ? few
              : many;

    return `${count} ${word}`;
}

/** «12 мин назад», «3 ч назад», «7 окт., 14:20». */
export function formatAgo(iso: string, timeZone: string): string {
    const minutes = Math.round((Date.now() - new Date(iso).getTime()) / 60_000);
    if (minutes < 1) return 'только что';
    if (minutes < 60) return `${minutes} мин назад`;
    if (minutes < 24 * 60) return `${Math.round(minutes / 60)} ч назад`;

    return new Intl.DateTimeFormat(LOCALE, {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
        timeZone,
    }).format(new Date(iso));
}
