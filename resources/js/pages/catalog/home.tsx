import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Trophy, Tv } from 'lucide-react';
import { EventGroups } from '@/components/catalog/event-list';
import { Empty } from '@/components/catalog/states';
import { useTimezone } from '@/hooks/use-timezone';
import {
    formatDay,
    formatLongDate,
    pluralize,
    SPORT_EMOJI,
    useCatalog,
} from '@/lib/catalog';
import type { EventGroup } from '@/types/catalog';

interface SportSummary {
    key: string;
    name: string;
    url: string;
    /** Событий на неделю вперёд. */
    week: number;
    /** День ближайших событий (сегодня или позже). */
    day: string | null;
    groups: EventGroup[];
}

/** Главная: виды спорта с числом событий на неделю и ближайшие события каждого. */
export default function Home({
    date,
    sports,
    broadcast_groups,
    broadcast_count,
}: {
    date: string;
    sports: SportSummary[];
    broadcast_groups: EventGroup[];
    broadcast_count: number;
}) {
    const timezone = useTimezone();
    const catalog = useCatalog();
    const withoutData = catalog.sports.filter((sport) => !sport.has_data);
    const upcoming = sports.filter((sport) => sport.groups.length > 0);

    return (
        <>
            <Head title="Расписание событий" />
            <div className="flex flex-col gap-8">
                <header>
                    <h1 className="font-display text-2xl font-bold md:text-3xl">
                        Ближайшие события
                    </h1>
                    <p className="text-[14px] text-muted-foreground first-letter:uppercase">
                        {formatLongDate(date)}
                    </p>
                </header>

                <section aria-labelledby="broadcasts">
                    <div className="mb-3 flex items-center justify-between gap-3">
                        <h2
                            id="broadcasts"
                            className="flex items-center gap-2 text-[15px] font-semibold"
                        >
                            <Tv className="size-4" aria-hidden />
                            Сегодня по ТВ
                            <span className="text-muted-foreground">
                                {broadcast_count}
                            </span>
                        </h2>
                        <Link
                            href={`/schedule?date=${date}&tv=1`}
                            className="inline-flex items-center gap-1 text-[13px] text-muted-foreground hover:text-foreground"
                        >
                            Все трансляции <ArrowRight className="size-3.5" />
                        </Link>
                    </div>
                    {broadcast_groups.length > 0 ? (
                        <EventGroups groups={broadcast_groups} />
                    ) : (
                        <p className="text-[13px] text-muted-foreground">
                            На сегодня подтверждённых трансляций нет.
                        </p>
                    )}
                </section>

                <section aria-labelledby="sports">
                    <h2 id="sports" className="mb-3 text-[15px] font-semibold">
                        Виды спорта
                    </h2>
                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                        {sports.map((sport) => (
                            <Link
                                key={sport.key}
                                href={sport.url}
                                className="flex items-center gap-3 rounded-xl border border-border/70 bg-card px-3 py-2.5 hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <span className="text-xl" aria-hidden>
                                    {SPORT_EMOJI[sport.key]}
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-[14px] font-semibold">
                                        {sport.name}
                                    </span>
                                    <span className="block text-[12px] text-muted-foreground">
                                        {sport.week > 0
                                            ? `${pluralize(sport.week, 'событие', 'события', 'событий')} за неделю`
                                            : 'на неделе событий нет'}
                                    </span>
                                </span>
                            </Link>
                        ))}
                    </div>
                    {withoutData.length > 0 && (
                        <p className="mt-2 text-[12px] text-muted-foreground">
                            Без данных:{' '}
                            {withoutData.map((sport) => sport.name).join(', ')}.
                        </p>
                    )}
                </section>

                {upcoming.length > 0 ? (
                    upcoming.map((sport) => (
                        <section
                            key={sport.key}
                            aria-labelledby={`sport-${sport.key}`}
                        >
                            <div className="mb-3 flex items-center justify-between gap-3">
                                <h2
                                    id={`sport-${sport.key}`}
                                    className="flex items-center gap-2 text-[15px] font-semibold"
                                >
                                    <span aria-hidden>
                                        {SPORT_EMOJI[sport.key]}
                                    </span>
                                    {sport.name}
                                    {sport.day && (
                                        <span className="font-normal text-muted-foreground first-letter:uppercase">
                                            · {formatDay(sport.day, timezone)}
                                        </span>
                                    )}
                                </h2>
                                <Link
                                    href={sport.url}
                                    className="inline-flex items-center gap-1 text-[13px] text-muted-foreground hover:text-foreground"
                                >
                                    Всё расписание
                                    <ArrowRight className="size-3.5" />
                                </Link>
                            </div>
                            <EventGroups groups={sport.groups} />
                        </section>
                    ))
                ) : (
                    <Empty icon={Trophy} title="Ближайших событий нет">
                        Откройте вид спорта слева — его страны и турниры
                        доступны всегда.
                    </Empty>
                )}
            </div>
        </>
    );
}
