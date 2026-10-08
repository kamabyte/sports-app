import { Head, Link } from '@inertiajs/react';
import { CalendarX2 } from 'lucide-react';
import { DayFilter, filterChips, visit } from '@/components/catalog/day-filter';
import { EventGroups } from '@/components/catalog/event-list';
import { Empty } from '@/components/catalog/states';
import { useTimezone } from '@/hooks/use-timezone';
import {
    formatDay,
    formatLongDate,
    SPORT_EMOJI,
    withQuery,
} from '@/lib/catalog';
import { cn } from '@/lib/utils';
import type { EventGroup } from '@/types/catalog';

interface Props {
    filters: {
        date: string;
        sport: string | null;
        category: { key: string; name: string } | null;
        competition: { id: number; name: string } | null;
    };
    sports: { key: string; name: string }[];
    groups: EventGroup[];
    next_date: string | null;
}

/**
 * Расписание всех видов спорта на день. Сменили вид спорта — фильтры раздела
 * и турнира сбрасываются (они принадлежат прежнему виду спорта).
 */
export default function Schedule({
    filters,
    sports,
    groups,
    next_date,
}: Props) {
    const timezone = useTimezone();
    const extra = {
        sport: filters.sport,
        category: filters.category?.key ?? null,
        competition: filters.competition?.id ?? null,
    };
    const selectSport = (sport: string | null) =>
        visit('/schedule', {
            date: filters.date,
            sport,
            category: null,
            competition: null,
        });

    return (
        <>
            <Head title={`Расписание: ${formatDay(filters.date, timezone)}`} />
            <div className="mx-auto flex max-w-5xl flex-col gap-4">
                <header>
                    <h1 className="font-display text-2xl font-bold md:text-3xl">
                        Расписание
                    </h1>
                    <p className="text-[14px] text-muted-foreground">
                        <span className="first-letter:uppercase">
                            {formatLongDate(filters.date)}
                        </span>
                        {' · '}
                        {filters.sport
                            ? sports.find(
                                  (sport) => sport.key === filters.sport,
                              )?.name
                            : 'все виды спорта'}
                    </p>
                </header>

                <div
                    className="-mx-1 scrollbar-none flex gap-1.5 overflow-x-auto px-1"
                    role="radiogroup"
                    aria-label="Вид спорта"
                >
                    {[
                        { key: null as string | null, name: 'Все виды' },
                        ...sports,
                    ].map((sport) => {
                        const active = filters.sport === sport.key;

                        return (
                            <button
                                key={sport.key ?? 'all'}
                                type="button"
                                role="radio"
                                aria-checked={active}
                                onClick={() => selectSport(sport.key)}
                                className={cn(
                                    'inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-[13px] font-medium',
                                    active
                                        ? 'border-foreground bg-foreground text-background'
                                        : 'border-border bg-card hover:bg-accent',
                                )}
                            >
                                {sport.key && (
                                    <span aria-hidden>
                                        {SPORT_EMOJI[sport.key]}
                                    </span>
                                )}
                                {sport.name}
                            </button>
                        );
                    })}
                </div>

                <DayFilter
                    path="/schedule"
                    date={filters.date}
                    extra={extra}
                    chips={filterChips(filters.category, filters.competition)}
                />

                {groups.length > 0 ? (
                    <EventGroups groups={groups} />
                ) : (
                    <Empty
                        icon={CalendarX2}
                        title={`${formatDay(filters.date, timezone)}: событий нет`}
                        action={
                            next_date && (
                                <Link
                                    href={withQuery('/schedule', {
                                        ...extra,
                                        date: next_date,
                                    })}
                                    className="rounded-lg bg-foreground px-3 py-1.5 text-[13px] font-medium text-background"
                                >
                                    Ближайшие события:{' '}
                                    {formatDay(next_date, timezone)}
                                </Link>
                            )
                        }
                    />
                )}
            </div>
        </>
    );
}
