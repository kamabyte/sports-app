import type { EventStatus, SportEvent, SportEventDetail } from '@/types';

const LOCALE = 'ru-RU';

export function formatTime(iso: string, timeZone: string): string {
    return new Intl.DateTimeFormat(LOCALE, {
        hour: '2-digit',
        minute: '2-digit',
        timeZone,
    }).format(new Date(iso));
}

/** Дата в часовом поясе расписания, «2026-10-06». */
export function dateKey(iso: string | Date, timeZone: string): string {
    return new Intl.DateTimeFormat('en-CA', { timeZone }).format(
        typeof iso === 'string' ? new Date(iso) : iso,
    );
}

/** «Сегодня», «Завтра», «Вчера» или «чт, 9 октября». */
export function formatDay(
    date: string,
    timeZone: string,
    now = new Date(),
): string {
    const today = dateKey(now, timeZone);
    const shift = (days: number) =>
        dateKey(new Date(now.getTime() + days * 86_400_000), timeZone);

    if (date === today) return 'Сегодня';
    if (date === shift(1)) return 'Завтра';
    if (date === shift(-1)) return 'Вчера';

    // Полдень UTC — чтобы дата не съехала на соседнюю ни в каком поясе.
    return new Intl.DateTimeFormat(LOCALE, {
        weekday: 'short',
        day: 'numeric',
        month: 'long',
        timeZone: 'UTC',
    }).format(new Date(`${date}T12:00:00Z`));
}

/** Короткая подпись дня для вкладок: «Сегодня» или «чт 9». */
export function formatDayTab(
    date: string,
    timeZone: string,
    now = new Date(),
): { label: string; sub: string } {
    const label = formatDay(date, timeZone, now);
    const day = new Date(`${date}T12:00:00Z`);
    const sub = new Intl.DateTimeFormat(LOCALE, {
        day: 'numeric',
        month: 'short',
        timeZone: 'UTC',
    }).format(day);

    if (['Сегодня', 'Завтра', 'Вчера'].includes(label)) return { label, sub };

    return {
        label: new Intl.DateTimeFormat(LOCALE, {
            weekday: 'long',
            timeZone: 'UTC',
        }).format(day),
        sub,
    };
}

/** «через 25 мин», «через 3 ч 10 мин». */
export function formatCountdown(iso: string, now: number): string {
    const minutes = Math.max(
        0,
        Math.round((new Date(iso).getTime() - now) / 60_000),
    );
    if (minutes < 1) return 'вот-вот';
    if (minutes < 60) return `через ${minutes} мин`;
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;
    if (hours < 24)
        return rest ? `через ${hours} ч ${rest} мин` : `через ${hours} ч`;
    return `через ${Math.round(hours / 24)} дн`;
}

/**
 * Статус и прогресс по часам браузера: страница может висеть открытой
 * долго, а матч — начаться или закончиться.
 */
export function liveState(
    event: Pick<SportEvent | SportEventDetail, 'starts_at' | 'ends_at'>,
    now: number,
): { status: EventStatus; progress: number } {
    const start = new Date(event.starts_at).getTime();
    const end = new Date(event.ends_at).getTime();

    if (now < start) return { status: 'upcoming', progress: 0 };
    if (now >= end) return { status: 'finished', progress: 1 };
    return { status: 'live', progress: (now - start) / (end - start) };
}

/** Флаг страны из ISO-кода (EU тоже есть). */
export function flag(country: string | null): string {
    if (!country) return '';

    return country
        .toUpperCase()
        .replace(/./g, (char) =>
            String.fromCodePoint(127397 + char.charCodeAt(0)),
        );
}

const LANGUAGES: Record<string, string> = {
    ru: 'Русский',
    en: 'Английский',
    ar: 'Арабский',
    de: 'Немецкий',
    es: 'Испанский',
    it: 'Итальянский',
    fr: 'Французский',
};

export function languageName(code: string): string {
    return LANGUAGES[code] ?? code.toUpperCase();
}

/** «Матч Премьер», «Матч Премьер +2». */
export function channelsSummary(channels: string[]): string | null {
    if (channels.length === 0) return null;
    return channels.length > 1
        ? `${channels[0]} +${channels.length - 1}`
        : channels[0];
}
