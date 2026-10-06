import { Link } from '@inertiajs/react';
import { ChevronRight, Tv } from 'lucide-react';
import { LiveDot } from '@/components/live-badge';
import { TeamCrest } from '@/components/team-crest';
import { useNow } from '@/hooks/use-now';
import { useTimezone } from '@/hooks/use-timezone';
import { hasTeams } from '@/lib/event-visuals';
import {
    channelsSummary,
    dateKey,
    formatDay,
    formatTime,
    liveState,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import type { SportEvent } from '@/types';

/** Строка телегида: время, соперники, турнир, каналы. */
export function EventRow({
    event,
    showDate = false,
    showCompetition = true,
}: {
    event: SportEvent;
    showDate?: boolean;
    showCompetition?: boolean;
}) {
    const now = useNow();
    const timezone = useTimezone();
    const { status, progress } = liveState(event, now);
    const live = status === 'live';
    const channels = channelsSummary(event.channels);

    return (
        <Link
            href={`/events/${event.id}`}
            prefetch="hover"
            className={cn(
                'group relative flex items-center gap-3 overflow-hidden rounded-2xl px-3 py-3 transition-colors hover:bg-accent/60 sm:gap-4 sm:px-4',
                status === 'finished' && 'opacity-60 hover:opacity-100',
            )}
        >
            <div className="w-14 shrink-0 text-right sm:w-16">
                {live ? (
                    <span className="inline-flex items-center gap-1.5 text-xs font-bold text-live uppercase">
                        <LiveDot />
                        {event.live_label ?? 'Live'}
                    </span>
                ) : (
                    <>
                        <div className="text-[15px] font-semibold tabular-nums">
                            {formatTime(event.starts_at, timezone)}
                        </div>
                        {showDate && (
                            <div className="text-[11px] text-muted-foreground">
                                {formatDay(
                                    dateKey(event.starts_at, timezone),
                                    timezone,
                                )}
                            </div>
                        )}
                    </>
                )}
            </div>

            {hasTeams(event) ? (
                <div className="flex shrink-0 -space-x-2.5">
                    <TeamCrest
                        team={event.home}
                        className="size-9 text-[10px]"
                    />
                    <TeamCrest
                        team={event.away}
                        className="size-9 text-[10px]"
                    />
                </div>
            ) : (
                <span
                    className="flex size-9 shrink-0 items-center justify-center rounded-full text-lg"
                    style={{ backgroundColor: `${event.sport.color}33` }}
                    aria-hidden
                >
                    {event.sport.emoji}
                </span>
            )}

            <div className="min-w-0 flex-1">
                <div className="truncate text-[15px] font-semibold">
                    {event.title}
                </div>
                <div className="flex min-w-0 items-center gap-1.5 truncate text-[13px] text-muted-foreground">
                    <span className="truncate">
                        {[
                            showCompetition
                                ? `${event.sport.emoji} ${event.competition.name}`
                                : null,
                            event.round,
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                    </span>
                    {channels && (
                        <span className="hidden shrink-0 items-center gap-1 md:inline-flex">
                            · <Tv className="size-3.5" /> {channels}
                        </span>
                    )}
                </div>
            </div>

            {event.score && status !== 'upcoming' && (
                <div className="shrink-0 font-display text-lg font-bold tabular-nums">
                    {event.score.home}:{event.score.away}
                </div>
            )}

            <ChevronRight className="size-4 shrink-0 text-muted-foreground transition group-hover:translate-x-0.5" />

            {live && (
                <div className="absolute inset-x-4 bottom-0 h-0.5 overflow-hidden rounded-full bg-live/15">
                    <div
                        className="h-full bg-live"
                        style={{ width: `${progress * 100}%` }}
                    />
                </div>
            )}
        </Link>
    );
}
