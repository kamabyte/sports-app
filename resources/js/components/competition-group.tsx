import { CompetitionMark } from '@/components/competition-badge';
import { EventRow } from '@/components/event-row';

import type { SportEvent } from '@/types';

/** События одного турнира — как блок в телегиде. */
export function CompetitionGroup({ events }: { events: SportEvent[] }) {
    const { competition, sport } = events[0];

    return (
        <section className="overflow-hidden rounded-3xl border border-border/60 bg-card/50">
            <header className="flex items-center gap-2.5 border-b border-border/60 px-4 py-3 sm:px-5">
                <span aria-hidden>{sport.emoji}</span>
                <h2 className="font-display text-base font-semibold">
                    {competition.name}
                </h2>
                <CompetitionMark
                    competition={competition}
                    className="size-6 rounded-md p-0.5 text-sm"
                />
                <span className="ml-auto text-xs text-muted-foreground tabular-nums">
                    {events.length}
                </span>
            </header>
            <div className="flex flex-col p-1.5">
                {events.map((event) => (
                    <EventRow
                        key={event.id}
                        event={event}
                        showCompetition={false}
                    />
                ))}
            </div>
        </section>
    );
}

/** Группировка с сохранением порядка: турнир — по его первому событию. */
export function groupBy<T>(items: T[], key: (item: T) => string): T[][] {
    const groups = new Map<string, T[]>();
    for (const item of items) {
        const k = key(item);
        groups.set(k, [...(groups.get(k) ?? []), item]);
    }
    return [...groups.values()];
}
