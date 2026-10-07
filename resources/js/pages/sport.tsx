import { CompetitionMark } from '@/components/competition-badge';
import { Head, Link } from '@inertiajs/react';
import { CalendarX2 } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { EventCard } from '@/components/event-card';
import { EventRow } from '@/components/event-row';
import { groupBy } from '@/components/competition-group';
import { useNow } from '@/hooks/use-now';
import { useTimezone } from '@/hooks/use-timezone';
import { dateKey, formatDay, liveState } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Competition, Sport, SportEvent } from '@/types';

interface Props {
    sport: Sport;
    competitions: Competition[];
    competition: string | null;
    events: SportEvent[];
}

export default function SportPage({
    sport,
    competitions,
    competition,
    events,
}: Props) {
    const now = useNow();
    const timezone = useTimezone();
    const live = events.filter(
        (event) => liveState(event, now).status === 'live',
    );
    const upcoming = events.filter(
        (event) => liveState(event, now).status === 'upcoming',
    );
    const days = groupBy(upcoming, (event) =>
        dateKey(event.starts_at, timezone),
    );

    const chip = (key: string | null, label: string, item?: Competition) => (
        <Link
            key={key ?? 'all'}
            href={
                key
                    ? `/sports/${sport.key}?competition=${key}`
                    : `/sports/${sport.key}`
            }
            preserveScroll
            className={cn(
                'inline-flex h-9 shrink-0 items-center gap-1.5 rounded-full border px-4 text-sm font-medium transition-colors',
                competition === key
                    ? 'border-transparent bg-foreground text-background'
                    : 'border-border bg-card/60 text-muted-foreground hover:bg-accent hover:text-foreground',
            )}
        >
            {item && <CompetitionMark competition={item} />}
            {label}
        </Link>
    );

    return (
        <>
            <Head title={sport.name} />

            <div
                className="relative -mx-4 -mt-4 mb-8 overflow-hidden px-4 pt-10 pb-8 md:-mx-6 md:-mt-6 md:px-6 lg:-mx-8 lg:px-8"
                style={{
                    backgroundImage: `radial-gradient(80% 140% at 0% 0%, ${sport.color}40, transparent 70%)`,
                }}
            >
                <div className="flex items-center gap-4">
                    <span
                        className="flex size-16 items-center justify-center rounded-2xl text-4xl shadow-lg"
                        style={{ backgroundColor: `${sport.color}33` }}
                        aria-hidden
                    >
                        {sport.emoji}
                    </span>
                    <div>
                        <h1 className="font-display text-3xl font-bold md:text-4xl">
                            {sport.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {live.length > 0
                                ? `${live.length} идут сейчас · `
                                : ''}
                            {upcoming.length} впереди на неделе
                        </p>
                    </div>
                </div>

                <div className="-mx-4 mt-6 scrollbar-none flex gap-2 overflow-x-auto px-4 md:-mx-6 md:px-6 lg:-mx-8 lg:px-8">
                    {chip(null, 'Все турниры')}
                    {competitions.map((item) =>
                        chip(item.key, item.short_name, item),
                    )}
                </div>
            </div>

            <div className="flex flex-col gap-10">
                {live.length > 0 && (
                    <section>
                        <h2 className="mb-3 flex items-center gap-2 font-display text-xl font-semibold">
                            <span className="size-2 animate-live-pulse rounded-full bg-live" />
                            Матчи сейчас
                        </h2>
                        <div className="grid gap-x-4 gap-y-6 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                            {live.map((event) => (
                                <EventCard key={event.id} event={event} />
                            ))}
                        </div>
                    </section>
                )}

                {days.map((group) => {
                    const day = dateKey(group[0].starts_at, timezone);

                    return (
                        <section key={day}>
                            <h2 className="mb-2 font-display text-xl font-semibold first-letter:uppercase">
                                {formatDay(day, timezone)}
                            </h2>
                            <div className="flex flex-col rounded-3xl border border-border/60 bg-card/50 p-1.5">
                                {group.map((event) => (
                                    <EventRow
                                        key={event.id}
                                        event={event}
                                        showCompetition={competition === null}
                                    />
                                ))}
                            </div>
                        </section>
                    );
                })}

                {events.length === 0 && (
                    <EmptyState icon={CalendarX2} title="Ближайших событий нет">
                        Матчи появятся здесь после обновления спортивного
                        каталога.
                    </EmptyState>
                )}
            </div>
        </>
    );
}
