import { Head, Link } from '@inertiajs/react';
import { CalendarX2 } from 'lucide-react';
import { DayFilter, filterChips } from '@/components/catalog/day-filter';
import { EventGroups } from '@/components/catalog/event-list';
import { SportShell } from '@/components/catalog/sport-header';
import { Empty } from '@/components/catalog/states';
import { useTimezone } from '@/hooks/use-timezone';
import {
    formatDay,
    formatLongDate,
    pluralize,
    withQuery,
    zoneLabel,
} from '@/lib/catalog';
import type { EventGroup, SportPanelData, SportRef } from '@/types/catalog';

interface Props {
    sport: SportRef;
    filters: {
        date: string;
        category: { key: string; name: string } | null;
        competition: { id: number; name: string } | null;
        tv: boolean;
    };
    groups: EventGroup[];
    next_date: string | null;
    panel: SportPanelData;
}

/**
 * Вид спорта: расписание выбранного дня под заголовками турниров — со
 * страной или разделом. Слева — все страны и турниры, они доступны всегда.
 */
export default function SportPage({
    sport,
    filters,
    groups,
    next_date,
    panel,
}: Props) {
    const timezone = useTimezone();
    const extra = {
        category: filters.category?.key ?? null,
        competition: filters.competition?.id ?? null,
        tv: filters.tv ? 1 : null,
    };
    const total = groups.reduce((sum, group) => sum + group.events.length, 0);

    return (
        <>
            <Head
                title={`${sport.name}: ${formatDay(filters.date, timezone)}`}
            />
            <SportShell
                sport={sport}
                panel={panel}
                subtitle={
                    <>
                        <span className="first-letter:uppercase">
                            {formatLongDate(filters.date)}
                        </span>
                        {filters.category && ` · ${filters.category.name}`}
                        {filters.competition &&
                            ` · ${filters.competition.name}`}
                    </>
                }
            >
                <div className="mb-4">
                    <DayFilter
                        path={sport.url}
                        date={filters.date}
                        extra={extra}
                        chips={filterChips(
                            filters.category,
                            filters.competition,
                        )}
                        tv={filters.tv}
                    />
                </div>

                {total > 0 ? (
                    <>
                        <p className="mb-2 text-[12px] text-muted-foreground">
                            {pluralize(total, 'событие', 'события', 'событий')}{' '}
                            в{' '}
                            {pluralize(
                                groups.length,
                                'турнире',
                                'турнирах',
                                'турнирах',
                            )}{' '}
                            · время: {zoneLabel(timezone)}
                        </p>
                        <EventGroups groups={groups} />
                    </>
                ) : (
                    <Empty
                        icon={CalendarX2}
                        title={`${formatDay(filters.date, timezone)}: ${filters.tv ? 'трансляций' : 'событий'} нет`}
                        action={
                            next_date && (
                                <Link
                                    href={withQuery(sport.url, {
                                        ...extra,
                                        date: next_date,
                                    })}
                                    className="rounded-lg bg-foreground px-3 py-1.5 text-[13px] font-medium text-background hover:opacity-90"
                                >
                                    Ближайшие события:{' '}
                                    {formatDay(next_date, timezone)}
                                </Link>
                            )
                        }
                    >
                        Страны и турниры по-прежнему доступны в разделе «Все
                        турниры» и на панели разделов.
                    </Empty>
                )}
            </SportShell>
        </>
    );
}
