import { Head, Link } from '@inertiajs/react';
import { CalendarClock } from 'lucide-react';
import { Crest, Flag } from '@/components/catalog/crest';
import { Crumbs } from '@/components/catalog/crumbs';
import { EventTimeline } from '@/components/catalog/event-list';
import { SportShell } from '@/components/catalog/sport-header';
import { Badge, Empty, MoreLink } from '@/components/catalog/states';
import { withQuery } from '@/lib/catalog';
import type {
    Crumb,
    EventItem,
    SportPanelData,
    SportRef,
} from '@/types/catalog';

interface Props {
    competition: {
        id: number;
        name: string;
        original_name: string | null;
        logo: string | null;
        url: string;
        badges: string[];
        category: { name: string; flag: string | null; url: string };
    };
    sport: SportRef;
    crumbs: Crumb[];
    fixtures: EventItem[];
    limit: number;
    more: boolean;
    panel: SportPanelData;
}

/** Турнир: шапка с разделом и признаками и ближайшие матчи по дням. */
export default function CompetitionPage({
    competition,
    sport,
    crumbs,
    fixtures,
    limit,
    more,
    panel,
}: Props) {
    return (
        <>
            <Head
                title={`${competition.name} — ${competition.category.name}`}
            />
            <SportShell
                sport={sport}
                panel={panel}
                showTabs={false}
                title={sport.name}
            >
                <Crumbs items={crumbs} />

                <header className="mb-4 flex items-center gap-4 rounded-xl border border-border/70 bg-card p-4">
                    <Crest
                        name={competition.name}
                        logo={competition.logo}
                        className="size-14 rounded-xl"
                    />
                    <div className="min-w-0 flex-1">
                        <h1 className="font-display text-xl font-bold md:text-2xl">
                            {competition.name}
                        </h1>
                        <div className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-[13px] text-muted-foreground">
                            <Link
                                href={competition.category.url}
                                className="inline-flex items-center gap-1.5 hover:text-foreground hover:underline"
                            >
                                <Flag src={competition.category.flag} />
                                {competition.category.name}
                            </Link>
                            {competition.original_name && (
                                <span>· {competition.original_name}</span>
                            )}
                            {competition.badges.map((badge) => (
                                <Badge key={badge}>{badge}</Badge>
                            ))}
                        </div>
                    </div>
                </header>

                <h2 className="mb-2 text-[15px] font-semibold">
                    Ближайшие матчи
                </h2>
                {fixtures.length > 0 ? (
                    <>
                        <EventTimeline events={fixtures} groupBy="day" />
                        {more && (
                            <MoreLink
                                href={withQuery(competition.url, {
                                    limit: limit + 60,
                                })}
                            />
                        )}
                    </>
                ) : (
                    <Empty
                        icon={CalendarClock}
                        title="Запланированных событий нет"
                    >
                        Новые матчи появятся, когда турнир их объявит.
                    </Empty>
                )}
            </SportShell>
        </>
    );
}
