import { Head, Link } from '@inertiajs/react';
import { CalendarX2, ChevronRight, Shield } from 'lucide-react';
import { CompetitionCard } from '@/components/catalog/cards';
import { Crumbs } from '@/components/catalog/crumbs';
import { Flag } from '@/components/catalog/crest';
import { DayFilter } from '@/components/catalog/day-filter';
import { EventGroups } from '@/components/catalog/event-list';
import { SportShell } from '@/components/catalog/sport-header';
import { Empty } from '@/components/catalog/states';
import { useTimezone } from '@/hooks/use-timezone';
import { formatDay, withQuery } from '@/lib/catalog';
import type {
    CompetitionItem,
    Crumb,
    EventGroup,
    SportPanelData,
    SportRef,
} from '@/types/catalog';

interface Props {
    sport: SportRef;
    category: {
        key: string;
        kind: string;
        name: string;
        flag: string | null;
        url: string;
    };
    crumbs: Crumb[];
    sections: { title: string; competitions: CompetitionItem[] }[];
    children: {
        key: string;
        name: string;
        url: string;
        competitions_count: number;
    }[];
    national_teams_url: string | null;
    filters: { date: string };
    groups: EventGroup[];
    next_date: string | null;
    panel: SportPanelData;
}

/** Страна, регион или тур: турниры по группам и события выбранного дня. */
export default function CategoryPage(props: Props) {
    const {
        sport,
        category,
        crumbs,
        sections,
        children,
        national_teams_url,
        filters,
        groups,
        next_date,
        panel,
    } = props;
    const timezone = useTimezone();

    return (
        <>
            <Head title={`${category.name} — ${sport.name}`} />
            <SportShell
                sport={sport}
                panel={panel}
                showTabs={false}
                title={
                    <span className="flex items-center gap-2">
                        {category.flag && (
                            <Flag src={category.flag} className="h-5 w-7" />
                        )}
                        {category.name}
                    </span>
                }
                subtitle={`${sport.name} · ${sections.reduce((sum, section) => sum + section.competitions.length, 0)} турниров`}
            >
                <Crumbs items={crumbs} />

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)]">
                    <div className="flex flex-col gap-4">
                        {national_teams_url && (
                            <Link
                                href={national_teams_url}
                                className="flex items-center gap-3 rounded-xl border border-border/70 bg-card px-4 py-3 hover:bg-accent"
                            >
                                <Shield className="size-5 text-brand" />
                                <span className="flex-1 text-[14px] font-semibold">
                                    Сборные: {category.name}
                                </span>
                                <span className="text-[12px] text-muted-foreground">
                                    мужские, женские, молодёжные
                                </span>
                                <ChevronRight className="size-4 text-muted-foreground" />
                            </Link>
                        )}
                        {children.length > 0 && (
                            <section className="overflow-hidden rounded-xl border border-border/70 bg-card">
                                <h2 className="border-b border-border/60 bg-muted/60 px-4 py-2 text-[13px] font-semibold">
                                    Разделы
                                </h2>
                                {children.map((child) => (
                                    <Link
                                        key={child.key}
                                        href={child.url}
                                        className="flex items-center justify-between border-b border-border/60 px-4 py-2.5 text-[14px] last:border-b-0 hover:bg-accent/50"
                                    >
                                        {child.name}
                                        <span className="text-[12px] text-muted-foreground">
                                            {child.competitions_count} турниров
                                        </span>
                                    </Link>
                                ))}
                            </section>
                        )}
                        {sections.map((section) => (
                            <section
                                key={section.title}
                                className="overflow-hidden rounded-xl border border-border/70 bg-card"
                            >
                                <h2 className="border-b border-border/60 bg-muted/60 px-4 py-2 text-[13px] font-semibold">
                                    {section.title}
                                </h2>
                                {section.competitions.map((competition) => (
                                    <CompetitionCard
                                        key={competition.id}
                                        competition={competition}
                                    />
                                ))}
                            </section>
                        ))}
                        {sections.length === 0 && children.length === 0 && (
                            <Empty icon={CalendarX2} title="Турниров пока нет">
                                В этом разделе ещё нет загруженных турниров.
                            </Empty>
                        )}
                    </div>

                    <div className="flex flex-col gap-3">
                        <h2 className="text-[15px] font-semibold">
                            События раздела
                        </h2>
                        <DayFilter path={category.url} date={filters.date} />
                        {groups.length > 0 ? (
                            <EventGroups groups={groups} />
                        ) : (
                            <Empty
                                icon={CalendarX2}
                                title={`${formatDay(filters.date, timezone)}: событий нет`}
                                action={
                                    next_date && (
                                        <Link
                                            href={withQuery(category.url, {
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
                </div>
            </SportShell>
        </>
    );
}
