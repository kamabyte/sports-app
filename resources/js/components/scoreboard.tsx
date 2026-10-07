import { TeamCrest } from '@/components/team-crest';
import { useNow } from '@/hooks/use-now';
import { useTimezone } from '@/hooks/use-timezone';
import { hasTeams } from '@/lib/event-visuals';
import {
    dateKey,
    formatCountdown,
    formatDay,
    formatTime,
    liveState,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import type { SportEvent, SportEventDetail } from '@/types';

/** Соперники и между ними — счёт или время начала. */
export function Scoreboard({
    event,
    size = 'lg',
}: {
    event: SportEvent | SportEventDetail;
    size?: 'md' | 'lg';
}) {
    const now = useNow();
    const timezone = useTimezone();
    const { status } = liveState(event, now);
    const large = size === 'lg';

    if (!hasTeams(event)) {
        return (
            <div className="flex flex-col items-center gap-2 text-center text-white">
                <span className={large ? 'text-7xl' : 'text-5xl'} aria-hidden>
                    {event.sport.emoji}
                </span>
                <span className="text-sm font-medium text-white/75">
                    {status === 'upcoming'
                        ? formatCountdown(event.starts_at, now)
                        : (event.live_label ??
                          formatTime(event.starts_at, timezone))}
                </span>
            </div>
        );
    }

    const team = (side: 'home' | 'away') => {
        const value = event[side];

        return (
            <div
                className={cn(
                    'flex flex-1 flex-col items-center gap-3 text-center',
                    large ? 'max-w-56' : 'max-w-40',
                )}
            >
                <TeamCrest
                    team={value}
                    className={
                        large
                            ? 'size-20 text-lg sm:size-24 sm:text-xl'
                            : 'size-14 text-sm'
                    }
                />
                <span
                    className={cn(
                        'font-display leading-tight font-semibold text-balance',
                        large ? 'text-lg sm:text-xl' : 'text-base',
                    )}
                >
                    {value.name}
                </span>
            </div>
        );
    };

    return (
        <div className="flex w-full items-start justify-center gap-4 text-white sm:gap-8">
            {team('home')}
            <div
                className={cn(
                    'flex shrink-0 flex-col items-center gap-1.5',
                    large ? 'pt-5 sm:pt-7' : 'pt-3',
                )}
            >
                {event.score && status !== 'upcoming' ? (
                    <span
                        className={cn(
                            'font-display font-bold tabular-nums',
                            large ? 'text-5xl sm:text-6xl' : 'text-4xl',
                        )}
                    >
                        {event.score.home}
                        <span className="px-1 text-white/40">:</span>
                        {event.score.away}
                    </span>
                ) : (
                    <span
                        className={cn(
                            'font-display font-bold tabular-nums',
                            large ? 'text-4xl sm:text-5xl' : 'text-3xl',
                        )}
                    >
                        {formatTime(event.starts_at, timezone)}
                    </span>
                )}
                <span className="text-xs font-medium tracking-wide text-white/70 uppercase">
                    {status === 'live' && (event.live_label ?? 'Идёт матч')}
                    {status === 'finished' && 'Завершён'}
                    {status === 'upcoming' &&
                        `${formatDay(dateKey(event.starts_at, timezone), timezone)} · ${formatCountdown(event.starts_at, now)}`}
                </span>
            </div>
            {team('away')}
        </div>
    );
}
