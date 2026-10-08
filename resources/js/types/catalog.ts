/** Спортивный каталог: типы пропсов страниц (app/Http/Resources/Catalog). */

export type Family =
    | 'team'
    | 'racket'
    | 'duel'
    | 'bout'
    | 'cricket'
    | 'race'
    | 'golf';

export type EventStatus =
    | 'scheduled'
    | 'live'
    | 'finished'
    | 'postponed'
    | 'cancelled'
    | 'abandoned'
    | 'interrupted'
    | 'unknown';

export interface CatalogSportNav {
    key: string;
    name: string;
    family: Family;
    url: string;
    /** Есть проверенные данные; без них вид спорта помечен «нет данных». */
    has_data: boolean;
}

export interface CatalogShared {
    sports: CatalogSportNav[];
    updated_at: string | null;
    stale: boolean;
}

export interface SportRef {
    key: string;
    name: string;
    family: Family;
    url: string;
    has_data?: boolean;
    wave?: string;
}

export interface Crumb {
    label: string;
    url: string | null;
}

export interface CompetitionItem {
    id: number;
    name: string;
    original_name: string | null;
    url: string;
    logo: string | null;
    sport: { key: string; name: string; family: Family };
    category: {
        key: string;
        name: string;
        kind: string;
        url: string;
        flag: string | null;
    };
    badges: string[];
}

export interface Member {
    id: number;
    name: string;
    url: string;
    flag: string | null;
}

/** Команда, спортсмен, пара или эстафета. */
export interface Participant {
    kind: 'team' | 'individual' | 'pair' | 'relay';
    id: number | null;
    name: string;
    url: string | null;
    logo: string | null;
    flag: string | null;
    /** Сборная: эмблема — флаг страны. */
    national: boolean;
    code: string | null;
    members: Member[];
}

export interface Side {
    side: 1 | 2;
    participant: Participant | null;
    /** «Уточняется» или имя из дневного списка — если профиля участника нет. */
    label: string | null;
    /** Имя из дневного списка — профиля участника пока нет. */
    provisional: boolean;
}

export interface EventItem {
    id: number;
    url: string;
    kind: string;
    family: Family;
    name: string | null;
    round: string | null;
    parent: { id: number; name: string | null; url: string } | null;
    status: EventStatus;
    status_label: string;
    time_precision: 'exact' | 'date' | 'unknown';
    starts_at: string | null;
    date: string | null;
    competition: CompetitionItem;
    sides: [Side, Side] | null;
    entrants: number | null;
}

export interface EventDetail extends EventItem {
    venue: string | null;
    ends_at: string | null;
    start_list: { participant: Participant; affiliation: string | null }[];
    children: EventItem[];
    siblings: {
        id: number;
        name: string | null;
        status: EventStatus;
        url: string;
        current: boolean;
    }[];
}

export interface EventGroup {
    key: string;
    competition: CompetitionItem;
    edition: string | null;
    parent: { name: string | null; url: string } | null;
    events: EventItem[];
}

export interface PanelCategory {
    key: string;
    kind: string;
    name: string;
    url: string;
    flag: string | null;
    competitions_count: number;
    children?: PanelCategory[];
}

export interface SportPanelData {
    popular: CompetitionItem[];
    categories: PanelCategory[];
    has_national_teams: boolean;
    has_players: boolean;
}

export interface TeamItem {
    id: number;
    name: string;
    original_name: string | null;
    url: string;
    logo: string | null;
    national: boolean;
    code: string | null;
    badges: string[];
    sport: { key: string; name: string } | null;
    country: { name: string; flag: string | null; code: string } | null;
}

export interface AthleteItem {
    id: number;
    name: string;
    original_name: string | null;
    url: string;
    portrait: string | null;
    country: { name: string; flag: string | null; code: string } | null;
    sports: { key: string; name: string }[] | null;
}
