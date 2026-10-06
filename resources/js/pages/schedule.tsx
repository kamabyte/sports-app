import { Head, Link } from '@inertiajs/react';
import { CalendarX2 } from 'lucide-react';
import { CompetitionGroup, groupBy } from '@/components/competition-group';
import { EmptyState, PageTitle } from '@/components/empty-state';
import { SportChips } from '@/components/sport-chips';
import { useTimezone } from '@/hooks/use-timezone';
import { formatDayTab } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { SportEvent } from '@/types';

interface Props {
    date: string;
    days: string[];
    sport: string | null;
    events: SportEvent[];
}

function scheduleUrl(date: string, sport: string | null): string {
    const params = new URLSearchParams({ date });
    if (sport) params.set('sport', sport);
    return `/schedule?${params}`;
}

export default function Schedule({ date, days, sport, events }: Props) {
    const timezone = useTimezone();
    const groups = groupBy(events, (event) => event.competition.key);

    return (
        <>
            <Head title="Расписание" />
            <PageTitle>Расписание</PageTitle>

            <div className="flex flex-col gap-4">
                <div className="-mx-4 scrollbar-none flex gap-2 overflow-x-auto px-4 md:-mx-6 md:px-6 lg:-mx-8 lg:px-8">
                    {days.map((day) => {
                        const tab = formatDayTab(day, timezone);
                        const active = day === date;

                        return (
                            <Link
                                key={day}
                                href={scheduleUrl(day, sport)}
                                preserveScroll
                                className={cn(
                                    'flex min-w-24 shrink-0 flex-col items-center rounded-2xl border px-4 py-2 transition-colors',
                                    active
                                        ? 'border-transparent bg-brand text-brand-foreground'
                                        : 'border-border bg-card/60 hover:bg-accent',
                                )}
                            >
                                <span className="text-sm font-semibold capitalize">
                                    {tab.label}
                                </span>
                                <span
                                    className={cn(
                                        'text-xs',
                                        active
                                            ? 'opacity-80'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    {tab.sub}
                                </span>
                            </Link>
                        );
                    })}
                </div>

                <SportChips
                    active={sport}
                    hrefFor={(key) => scheduleUrl(date, key)}
                />
            </div>

            <div className="mt-6 grid gap-4 xl:grid-cols-2">
                {groups.map((group) => (
                    <CompetitionGroup
                        key={group[0].competition.key}
                        events={group}
                    />
                ))}
            </div>

            {events.length === 0 && (
                <EmptyState icon={CalendarX2} title="В этот день ничего нет">
                    Выберите другой день или вид спорта.
                </EmptyState>
            )}
        </>
    );
}
