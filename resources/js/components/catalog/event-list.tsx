import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { Crest, Flag } from '@/components/catalog/crest';
import { EventRow } from '@/components/catalog/event-row';
import { useTimezone } from '@/hooks/use-timezone';
import { eventDay, formatDay } from '@/lib/catalog';
import { cn } from '@/lib/utils';
import type { EventGroup, EventItem } from '@/types/catalog';

/** Заголовок группы: флаг/раздел, турнир, сезон — ссылкой на страницу турнира. */
export function GroupHeader({ group }: { group: EventGroup }) {
    const { competition } = group;

    return (
        <Link
            href={competition.url}
            className="group flex items-center gap-2.5 border-b border-border/60 bg-muted/60 px-3 py-2 hover:bg-muted sm:px-4"
        >
            {competition.category.flag ? (
                <Flag
                    src={competition.category.flag}
                    label={competition.category.name}
                />
            ) : (
                <Crest
                    name={competition.name}
                    logo={competition.logo}
                    className="size-4"
                />
            )}
            <div className="min-w-0 flex-1 truncate text-[13px]">
                <span className="font-medium text-muted-foreground uppercase">
                    {competition.category.name}:
                </span>{' '}
                <span className="font-semibold">{competition.name}</span>
                {group.parent?.name && (
                    <span className="text-muted-foreground">
                        {' '}
                        · {group.parent.name}
                    </span>
                )}
                {competition.badges.length > 0 && (
                    <span className="text-muted-foreground">
                        {' '}
                        · {competition.badges.join(' · ')}
                    </span>
                )}
            </div>
            {group.edition && (
                <span className="hidden shrink-0 text-[12px] text-muted-foreground sm:inline">
                    {group.edition}
                </span>
            )}
            <ChevronRight className="size-4 shrink-0 text-muted-foreground transition group-hover:translate-x-0.5" />
        </Link>
    );
}

/** События под заголовками турниров. */
export function EventGroups({
    groups,
    className,
    collapseAfter = 20,
}: {
    groups: EventGroup[];
    className?: string;
    /** Сколько турниров показывать сразу; остальные — по кнопке (длинный день футбола). */
    collapseAfter?: number;
}) {
    const [expanded, setExpanded] = useState(false);
    const visible = expanded ? groups : groups.slice(0, collapseAfter);
    const hidden = groups.slice(visible.length);
    const hiddenEvents = hidden.reduce(
        (sum, group) => sum + group.events.length,
        0,
    );

    return (
        <div className={cn('flex flex-col gap-3', className)}>
            {visible.map((group) => (
                <section
                    key={group.key}
                    className="overflow-hidden rounded-xl border border-border/70 bg-card"
                    aria-label={`${group.competition.category.name}: ${group.competition.name}`}
                >
                    <GroupHeader group={group} />
                    {group.events.map((event) => (
                        <EventRow key={event.id} event={event} />
                    ))}
                </section>
            ))}
            {hidden.length > 0 && (
                <button
                    type="button"
                    onClick={() => setExpanded(true)}
                    className="flex flex-col items-center gap-0.5 rounded-xl border border-dashed border-border bg-card py-3 text-[14px] font-medium hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    Ещё {hidden.length} турниров · {hiddenEvents} событий
                    <span className="text-[12px] font-normal text-muted-foreground">
                        {hidden
                            .slice(0, 6)
                            .map(
                                (group) =>
                                    `${group.competition.category.name}: ${group.competition.name}`,
                            )
                            .join(', ')}
                        {hidden.length > 6 ? '…' : ''}
                    </span>
                </button>
            )}
        </div>
    );
}

/**
 * Список событий турнира или команды: по турам/раундам, а без них — по дням.
 * В заголовке — тур и день, в строке — время.
 */
export function EventTimeline({
    events,
    groupBy = 'round',
    showCompetition = false,
}: {
    events: EventItem[];
    groupBy?: 'round' | 'day';
    showCompetition?: boolean;
}) {
    const timezone = useTimezone();
    const groups: { key: string; title: string; events: EventItem[] }[] = [];

    for (const event of events) {
        const day = eventDay(event, timezone);
        const dayTitle = day ? formatDay(day, timezone) : 'Дата уточняется';
        const key =
            groupBy === 'round' && event.round
                ? `${''}|${event.round}`
                : `day|${day ?? 'tbd'}`;
        const title =
            groupBy === 'round' && event.round
                ? [event.round].filter(Boolean).join(' · ')
                : dayTitle;
        const last = groups[groups.length - 1];
        if (last?.key === key) {
            last.events.push(event);
        } else {
            groups.push({ key, title, events: [event] });
        }
    }

    return (
        <div className="flex flex-col gap-3">
            {groups.map((group, index) => (
                <section
                    key={`${group.key}-${index}`}
                    className="overflow-hidden rounded-xl border border-border/70 bg-card"
                >
                    <h3 className="border-b border-border/60 bg-muted/60 px-3 py-2 text-[13px] font-semibold first-letter:uppercase sm:px-4">
                        {group.title}
                    </h3>
                    {group.events.map((event) => (
                        <EventRow
                            key={event.id}
                            event={event}
                            showDate={!group.key.startsWith('day|')}
                            showCompetition={showCompetition}
                        />
                    ))}
                </section>
            ))}
        </div>
    );
}
