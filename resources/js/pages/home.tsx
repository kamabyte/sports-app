import { Head } from '@inertiajs/react';
import { Clock, Trophy } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { EventCard } from '@/components/event-card';
import { Hero } from '@/components/hero';
import { LiveDot } from '@/components/live-badge';
import { Shelf } from '@/components/shelf';
import { SportChips } from '@/components/sport-chips';
import type { SportEvent, SportShelf } from '@/types';

interface Props {
    featured: SportEvent[];
    live: SportEvent[];
    startingSoon: SportEvent[];
    sportShelves: SportShelf[];
}

export default function Home({
    featured,
    live,
    startingSoon,
    sportShelves,
}: Props) {
    if (
        featured.length === 0 &&
        live.length === 0 &&
        sportShelves.length === 0
    ) {
        return (
            <>
                <Head title="Главная" />
                <EmptyState icon={Trophy} title="Пока пусто">
                    Матчи появятся здесь после обновления спортивного каталога.
                </EmptyState>
            </>
        );
    }

    return (
        <>
            <Head title="Главная" />
            <div className="flex flex-col gap-10 md:gap-12">
                <div className="flex flex-col gap-5">
                    <Hero events={featured} />
                    <SportChips
                        active={null}
                        hrefFor={(sport) =>
                            sport ? `/sports/${sport}` : '/schedule'
                        }
                    />
                </div>

                {live.length > 0 && (
                    <Shelf
                        title="Матчи сейчас"
                        href="/schedule"
                        icon={
                            <span className="flex size-7 items-center justify-center rounded-full bg-live/15 text-live">
                                <LiveDot className="size-2" />
                            </span>
                        }
                    >
                        {live.map((event) => (
                            <EventCard key={event.id} event={event} />
                        ))}
                    </Shelf>
                )}

                {startingSoon.length > 0 && (
                    <Shelf
                        title="Скоро начнётся"
                        icon={<Clock className="size-5 text-brand" />}
                    >
                        {startingSoon.map((event) => (
                            <EventCard key={event.id} event={event} />
                        ))}
                    </Shelf>
                )}

                {sportShelves.map(({ sport, events }) => (
                    <Shelf
                        key={sport.key}
                        href={`/sports/${sport.key}`}
                        icon={
                            <span
                                className="flex size-8 items-center justify-center rounded-lg text-lg"
                                style={{ backgroundColor: `${sport.color}26` }}
                                aria-hidden
                            >
                                {sport.emoji}
                            </span>
                        }
                        title={sport.name}
                    >
                        {events.map((event) => (
                            <EventCard key={event.id} event={event} />
                        ))}
                    </Shelf>
                ))}
            </div>
        </>
    );
}
