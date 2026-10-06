export type EventStatus = 'upcoming' | 'live' | 'finished';

export interface Sport {
    key: string;
    name: string;
    emoji: string;
    /** Цвет вида спорта — фон карточек событий без команд. */
    color: string;
}

export interface Competition {
    key: string;
    sport: string;
    name: string;
    short_name: string;
    /** ISO-код страны или EU — для флага; null — неизвестна. */
    country: string | null;
    /** Эмблема турнира из каталога (сохранена у нас). */
    logo: string | null;
}

/** Команда или спортсмен. */
export interface Team {
    name: string;
    short_name: string;
    color: string;
    /** Эмблема команды (сохранена у нас). */
    logo: string | null;
    /** Сборная: logo — флаг, рисуется на весь круг. */
    flag: boolean;
}

/**
 * Показ события на канале и запись телегида, по которой его нашли:
 * прямой эфир, одновременный показ или повтор (со своим временем).
 */
export interface Broadcast {
    id: number;
    channel: string;
    channel_number: number;
    country: string | null;
    language: string | null;
    quality: 'HD' | 'FHD' | '4K' | null;
    provider: string;
    epg_title: string;
    logo: string | null;
    starts_at: string;
    ends_at: string;
    /** Помечен в телегиде как прямой эфир. */
    live: boolean;
    replay: boolean;
}

interface EventBase {
    id: string;
    title: string;
    sport: Sport;
    competition: Competition;
    home: Team | null;
    away: Team | null;
    round: string | null;
    venue: string | null;
    starts_at: string;
    ends_at: string;
    status: EventStatus;
    /** «67′», «2-й период» — только в эфире. */
    live_label: string | null;
    progress: number;
    score: { home: number; away: number } | null;
}

/** Событие в списках: вместо трансляций — названия каналов. */
export interface SportEvent extends EventBase {
    channels: string[];
}

export interface SportEventDetail extends EventBase {
    broadcasts: Broadcast[];
}

export interface SportShelf {
    sport: Sport;
    events: SportEvent[];
}

/** Канал источника (Dispatcharr). */
export interface ChannelSummary {
    id: number;
    number: number;
    name: string;
    logo: string | null;
    category: string | null;
    /** Сейчас идёт матч из расписания (боковая панель). */
    on_air?: boolean;
    /** Что из расписания идёт сейчас (раздел «Каналы»). */
    now?: { event_id: number; title: string; replay: boolean } | null;
}
