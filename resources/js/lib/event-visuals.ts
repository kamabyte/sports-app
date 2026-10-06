import type { CSSProperties } from 'react';
import type { SportEvent, SportEventDetail } from '@/types';

type AnyEvent = SportEvent | SportEventDetail;

/**
 * Фон карточки вместо постера: цвета соперников по углам на почти чёрном,
 * у событий без соперников — цвет вида спорта.
 */
export function eventBackdrop(event: AnyEvent, strength = 'cc'): CSSProperties {
    const home = event.home?.color ?? event.sport.color;
    const away = event.away?.color ?? event.sport.color;

    return {
        backgroundColor: '#0b0b0f',
        backgroundImage: [
            `radial-gradient(110% 130% at 0% 0%, ${home}${strength} 0%, transparent 58%)`,
            `radial-gradient(110% 130% at 100% 100%, ${away}${strength} 0%, transparent 58%)`,
            'linear-gradient(135deg, #1c1c22, #09090b)',
        ].join(', '),
    };
}

export function hasTeams(event: AnyEvent): event is AnyEvent & {
    home: NonNullable<AnyEvent['home']>;
    away: NonNullable<AnyEvent['away']>;
} {
    return event.home !== null && event.away !== null;
}
