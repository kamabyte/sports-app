import { CompetitionMark } from '@/components/competition-badge';
import { Link } from '@inertiajs/react';
import { EventStatusPill } from '@/components/event-status';
import { TeamCrest } from '@/components/team-crest';
import { useNow } from '@/hooks/use-now';
import { useTimezone } from '@/hooks/use-timezone';
import { eventBackdrop, hasTeams } from '@/lib/event-visuals';
import { formatTime, liveState } from '@/lib/format';
import type { SportEvent } from '@/types';

/** Карточка события на полке — как постер в Apple TV, только из цветов команд. */
export function EventCard({ event }: { event: SportEvent }) {
    const now = useNow();
    const timezone = useTimezone();
    const { status, progress } = liveState(event, now);

    return (
        <Link
            href={`/events/${event.id}`}
            className="group block rounded-2xl focus-visible:outline-none"
            prefetch
        >
            <div
                className="relative aspect-[16/10] overflow-hidden rounded-2xl shadow-lg ring-1 shadow-black/20 ring-white/10 transition duration-300 group-hover:-translate-y-0.5 group-hover:shadow-xl group-hover:ring-white/25 group-focus-visible:ring-2 group-focus-visible:ring-ring"
                style={eventBackdrop(event)}
            >
                <div className="absolute inset-x-3 top-3 flex items-center justify-between gap-2">
                    <span className="inline-flex h-6 min-w-0 items-center gap-1.5 rounded-full bg-black/35 px-2.5 text-[11px] font-semibold text-white/90 backdrop-blur-md">
                        <CompetitionMark competition={event.competition} />
                        <span className="truncate">
                            {event.competition.short_name}
                        </span>
                    </span>
                    <EventStatusPill event={event} className="shrink-0" />
                </div>

                <div className="absolute inset-0 flex items-center justify-center gap-4 px-6 pt-4 text-white">
                    {hasTeams(event) ? (
                        <>
                            <TeamCrest
                                team={event.home}
                                className="size-12 text-xs sm:size-14 sm:text-sm"
                            />
                            <span className="min-w-16 text-center font-display text-2xl font-bold tabular-nums">
                                {event.score && status !== 'upcoming' ? (
                                    `${event.score.home}:${event.score.away}`
                                ) : (
                                    <span className="text-base font-semibold text-white/70">
                                        {formatTime(event.starts_at, timezone)}
                                    </span>
                                )}
                            </span>
                            <TeamCrest
                                team={event.away}
                                className="size-12 text-xs sm:size-14 sm:text-sm"
                            />
                        </>
                    ) : (
                        <span className="text-5xl drop-shadow-lg" aria-hidden>
                            {event.sport.emoji}
                        </span>
                    )}
                </div>

                {status === 'live' && (
                    <div className="absolute inset-x-0 bottom-0 h-1 bg-white/15">
                        <div
                            className="h-full bg-live"
                            style={{ width: `${progress * 100}%` }}
                        />
                    </div>
                )}
            </div>

            <div className="mt-2.5 px-0.5">
                <div className="truncate text-[15px] leading-snug font-semibold">
                    {event.title}
                </div>
                <div className="mt-0.5 truncate text-[13px] text-muted-foreground">
                    {event.round ?? event.competition.name}
                </div>
            </div>
        </Link>
    );
}
