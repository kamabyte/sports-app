import { CompetitionMark } from '@/components/competition-badge';
import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Info, Play, Tv } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { EventStatusPill } from '@/components/event-status';
import { TeamCrest } from '@/components/team-crest';
import { Button } from '@/components/ui/button';
import { useNow } from '@/hooks/use-now';
import { useTimezone } from '@/hooks/use-timezone';
import { eventBackdrop, hasTeams } from '@/lib/event-visuals';
import {
    channelsSummary,
    dateKey,
    formatDay,
    formatTime,
    liveState,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import type { SportEvent } from '@/types';

const INTERVAL = 8000;
const SWIPE_THRESHOLD = 50;

/** Витрина главной — главные матчи: что идёт сейчас и ближайшее за сутки. */
export function Hero({ events }: { events: SportEvent[] }) {
    const [index, setIndex] = useState(0);
    const [paused, setPaused] = useState(false);
    const touchStartX = useRef<number | null>(null);
    const now = useNow();
    const timezone = useTimezone();

    useEffect(() => {
        if (paused || events.length < 2) return;
        const timer = window.setTimeout(
            () => setIndex((i) => (i + 1) % events.length),
            INTERVAL,
        );
        return () => window.clearTimeout(timer);
    }, [index, paused, events.length]);

    if (events.length === 0) return null;

    const go = (delta: number) =>
        setIndex((i) => (i + delta + events.length) % events.length);
    const current = events[index];
    const { status } = liveState(current, now);
    const channels = channelsSummary(current.channels);
    const when =
        status === 'upcoming'
            ? `${formatDay(dateKey(current.starts_at, timezone), timezone)}, ${formatTime(current.starts_at, timezone)}`
            : null;

    return (
        <section
            className="relative isolate overflow-hidden rounded-3xl text-white shadow-xl shadow-black/10 dark:shadow-black/40"
            onMouseEnter={() => setPaused(true)}
            onMouseLeave={() => setPaused(false)}
            onFocusCapture={() => setPaused(true)}
            onBlurCapture={() => setPaused(false)}
            onKeyDown={(event) => {
                if (event.key === 'ArrowLeft') go(-1);
                if (event.key === 'ArrowRight') go(1);
            }}
            onTouchStart={(event) =>
                (touchStartX.current = event.touches[0].clientX)
            }
            onTouchEnd={(event) => {
                if (touchStartX.current === null) return;
                const dx =
                    event.changedTouches[0].clientX - touchStartX.current;
                touchStartX.current = null;
                if (Math.abs(dx) >= SWIPE_THRESHOLD) go(dx < 0 ? 1 : -1);
            }}
            aria-roledescription="карусель"
        >
            {events.map((event, i) => (
                <div
                    key={event.id}
                    aria-hidden
                    className={cn(
                        'absolute inset-0 -z-10 transition-opacity duration-1000',
                        i === index ? 'opacity-100' : 'opacity-0',
                    )}
                    style={eventBackdrop(event, 'ee')}
                />
            ))}
            <div className="absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_center,transparent_30%,rgb(0_0_0/0.55))]" />

            <div className="grid min-h-[340px] items-center gap-8 p-6 sm:p-8 md:min-h-[400px] lg:grid-cols-[1fr_auto] lg:p-12">
                <div
                    key={current.id}
                    className="flex animate-fade-in flex-col items-start gap-4"
                >
                    <div className="flex flex-wrap items-center gap-2 text-sm font-medium text-white/80">
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 backdrop-blur-md">
                            <span aria-hidden>{current.sport.emoji}</span>
                            <CompetitionMark
                                competition={current.competition}
                            />
                            {current.competition.name}
                        </span>
                        {current.round && <span>{current.round}</span>}
                    </div>

                    <h2 className="max-w-2xl font-display text-3xl leading-tight font-bold text-balance sm:text-4xl lg:text-5xl">
                        {current.title}
                    </h2>

                    <div className="flex flex-wrap items-center gap-3 text-sm text-white/80">
                        {/* У будущего — день и время, у остального — LIVE или «Завершён». */}
                        {when ? (
                            <span className="font-semibold text-white">
                                {when}
                            </span>
                        ) : (
                            <EventStatusPill event={current} />
                        )}
                        {channels && (
                            <span className="inline-flex items-center gap-1.5">
                                <Tv className="size-4" />
                                {channels}
                            </span>
                        )}
                    </div>

                    <div className="mt-2 flex gap-3">
                        <Button
                            asChild
                            size="lg"
                            className="h-11 rounded-full bg-white px-6 text-[15px] font-semibold text-black hover:bg-white/90"
                        >
                            <Link href={`/events/${current.id}/watch`}>
                                <Play className="size-4 fill-current" />
                                {status === 'live'
                                    ? 'Смотреть'
                                    : 'Открыть канал'}
                            </Link>
                        </Button>
                        <Button
                            asChild
                            size="lg"
                            variant="secondary"
                            className="h-11 rounded-full border-0 bg-white/15 px-5 text-[15px] text-white backdrop-blur-md hover:bg-white/25"
                        >
                            <Link href={`/events/${current.id}`}>
                                <Info className="size-4" />
                                Подробнее
                            </Link>
                        </Button>
                    </div>
                </div>

                {hasTeams(current) && (
                    <div
                        key={`${current.id}-teams`}
                        className="hidden animate-fade-in items-center gap-6 lg:flex"
                    >
                        <TeamCrest
                            team={current.home}
                            className="size-28 text-2xl shadow-2xl"
                        />
                        <span className="min-w-24 text-center font-display text-5xl font-bold tabular-nums">
                            {current.score && status !== 'upcoming'
                                ? `${current.score.home}:${current.score.away}`
                                : 'vs'}
                        </span>
                        <TeamCrest
                            team={current.away}
                            className="size-28 text-2xl shadow-2xl"
                        />
                    </div>
                )}
            </div>

            {events.length > 1 && (
                <>
                    <div className="absolute right-6 bottom-6 flex items-center gap-1.5 lg:right-12">
                        {events.map((event, i) => (
                            <button
                                key={event.id}
                                type="button"
                                onClick={() => setIndex(i)}
                                className={cn(
                                    'h-1.5 rounded-full bg-white/35 transition-all',
                                    i === index
                                        ? 'w-6 bg-white'
                                        : 'w-1.5 hover:bg-white/60',
                                )}
                                aria-label={`Событие ${i + 1}`}
                                aria-current={i === index}
                            />
                        ))}
                    </div>
                    <button
                        type="button"
                        onClick={() => go(-1)}
                        className="absolute top-1/2 left-3 hidden size-9 -translate-y-1/2 items-center justify-center rounded-full bg-black/30 text-white opacity-0 backdrop-blur-md transition hover:bg-black/50 focus-visible:opacity-100 md:flex [section:hover_&]:opacity-100"
                        aria-label="Назад"
                    >
                        <ChevronLeft className="size-5" />
                    </button>
                    <button
                        type="button"
                        onClick={() => go(1)}
                        className="absolute top-1/2 right-3 hidden size-9 -translate-y-1/2 items-center justify-center rounded-full bg-black/30 text-white opacity-0 backdrop-blur-md transition hover:bg-black/50 focus-visible:opacity-100 md:flex [section:hover_&]:opacity-100"
                        aria-label="Вперёд"
                    >
                        <ChevronRight className="size-5" />
                    </button>
                </>
            )}
        </section>
    );
}
