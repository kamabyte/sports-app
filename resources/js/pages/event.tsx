import { CompetitionMark } from '@/components/competition-badge';
import { Head, Link } from '@inertiajs/react';
import { ChevronRight, MapPin } from 'lucide-react';
import { EventCard } from '@/components/event-card';
import { EventStatusPill } from '@/components/event-status';
import { Scoreboard } from '@/components/scoreboard';
import { Shelf } from '@/components/shelf';
import { useNow } from '@/hooks/use-now';
import { useTimezone } from '@/hooks/use-timezone';
import { eventBackdrop } from '@/lib/event-visuals';
import { liveState } from '@/lib/format';
import type { SportEvent, SportEventDetail } from '@/types';

interface Props {
    event: SportEventDetail;
    related: SportEvent[];
}

export default function EventPage({ event, related }: Props) {
    const now = useNow();
    const timezone = useTimezone();
    const { status, progress } = liveState(event, now);

    return (
        <>
            <Head title={event.title} />

            <div className="flex flex-col gap-10">
                <section
                    className="relative isolate overflow-hidden rounded-3xl text-white shadow-xl shadow-black/10 dark:shadow-black/40"
                    style={eventBackdrop(event, 'ee')}
                >
                    <div className="absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_center,transparent_25%,rgb(0_0_0/0.5))]" />

                    <div className="flex flex-col items-center gap-8 px-5 py-8 sm:px-10 sm:py-10">
                        <nav
                            className="flex w-full flex-wrap items-center gap-1 text-sm text-white/75"
                            aria-label="Навигация"
                        >
                            <Link
                                href={`/sports/${event.sport.key}`}
                                className="hover:text-white"
                            >
                                {event.sport.emoji} {event.sport.name}
                            </Link>
                            <ChevronRight className="size-3.5" />
                            <Link
                                href={`/sports/${event.sport.key}?competition=${event.competition.key}`}
                                className="inline-flex items-center gap-1.5 hover:text-white"
                            >
                                <CompetitionMark
                                    competition={event.competition}
                                />
                                {event.competition.name}
                            </Link>
                            {event.round && (
                                <>
                                    <ChevronRight className="size-3.5" />
                                    <span>{event.round}</span>
                                </>
                            )}
                            <EventStatusPill
                                event={event}
                                className="ml-auto"
                            />
                        </nav>

                        <Scoreboard event={event} />

                        {!event.home && (
                            <h1 className="-mt-2 text-center font-display text-3xl font-bold sm:text-4xl">
                                {event.title}
                            </h1>
                        )}
                        {event.home && (
                            <h1 className="sr-only">{event.title}</h1>
                        )}

                        <div className="flex flex-col items-center gap-3">
                            {event.venue && (
                                <span className="inline-flex items-center gap-1 text-sm text-white/70">
                                    <MapPin className="size-3.5" />{' '}
                                    {event.venue}
                                </span>
                            )}
                        </div>
                    </div>

                    {status === 'live' && (
                        <div className="h-1 bg-white/15">
                            <div
                                className="h-full bg-live"
                                style={{ width: `${progress * 100}%` }}
                            />
                        </div>
                    )}
                </section>

                <section className="grid gap-8">
                    <div className="rounded-3xl border border-border/60 bg-card/50 p-5 text-sm">
                        <h2 className="mb-3 font-display text-xl font-semibold">
                            О событии
                        </h2>
                        <dl className="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2.5">
                            <dt className="text-muted-foreground">
                                Вид спорта
                            </dt>
                            <dd>
                                {event.sport.emoji} {event.sport.name}
                            </dd>
                            <dt className="text-muted-foreground">Турнир</dt>
                            <dd className="flex items-center gap-1.5">
                                <CompetitionMark
                                    competition={event.competition}
                                />
                                {event.competition.name}
                            </dd>
                            {event.round && (
                                <>
                                    <dt className="text-muted-foreground">
                                        Стадия
                                    </dt>
                                    <dd>{event.round}</dd>
                                </>
                            )}
                            <dt className="text-muted-foreground">Начало</dt>
                            <dd>
                                {new Date(event.starts_at).toLocaleString(
                                    'ru-RU',
                                    {
                                        weekday: 'long',
                                        day: 'numeric',
                                        month: 'long',
                                        hour: '2-digit',
                                        minute: '2-digit',
                                        timeZone: timezone,
                                    },
                                )}
                            </dd>
                        </dl>
                    </div>
                </section>

                {related.length > 0 && (
                    <Shelf
                        title={`Ещё в турнире «${event.competition.name}»`}
                        href={`/sports/${event.sport.key}?competition=${event.competition.key}`}
                    >
                        {related.map((item) => (
                            <EventCard key={item.id} event={item} />
                        ))}
                    </Shelf>
                )}
            </div>
        </>
    );
}
