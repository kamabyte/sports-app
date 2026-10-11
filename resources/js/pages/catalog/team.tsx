import { Head, Link } from '@inertiajs/react';
import { CalendarClock, Trophy } from 'lucide-react';
import { CompetitionCard, TeamCard } from '@/components/catalog/cards';
import { Crest, Flag } from '@/components/catalog/crest';
import { Crumbs } from '@/components/catalog/crumbs';
import { EventTimeline } from '@/components/catalog/event-list';
import { Badge, Empty, MoreLink } from '@/components/catalog/states';
import { withQuery } from '@/lib/catalog';
import { cn } from '@/lib/utils';
import type {
    CompetitionItem,
    EventItem,
    SportRef,
    TeamItem,
} from '@/types/catalog';

interface Props {
    team: TeamItem;
    sport: SportRef;
    national_teams_url: string | null;
    upcoming: EventItem[];
    competitions: CompetitionItem[];
    variants: TeamItem[];
    tab: 'upcoming' | 'competitions';
    limit: number;
    more: boolean;
}

/** Профиль команды или сборной: признаки, матчи, турниры, другие сборные страны. */
export default function TeamPage({
    team,
    sport,
    national_teams_url,
    upcoming,
    competitions,
    variants,
    tab,
    limit,
    more,
}: Props) {
    const tabs = [
        {
            key: 'upcoming',
            label: `Ближайшие (${upcoming.length}${more ? '+' : ''})`,
        },
        { key: 'competitions', label: `Турниры (${competitions.length})` },
    ];
    const others = variants.filter((variant) => variant.id !== team.id);
    const crumbs = [
        { label: sport.name, url: sport.url },
        team.national && national_teams_url
            ? { label: 'Сборные', url: national_teams_url }
            : null,
        { label: team.name, url: null },
    ].filter(
        (crumb): crumb is { label: string; url: string | null } =>
            crumb !== null,
    );

    return (
        <>
            <Head title={`${team.name} — ${sport.name}`} />
            <div>
                <Crumbs items={crumbs} />
                <header className="mb-4 flex items-center gap-4 rounded-xl border border-border/70 bg-card p-4">
                    <Crest
                        name={team.name}
                        logo={team.logo}
                        national={team.national}
                        code={team.code}
                        className="size-16"
                    />
                    <div className="min-w-0 flex-1">
                        <h1 className="font-display text-2xl font-bold">
                            {team.name}
                        </h1>
                        {team.original_name && (
                            <div className="text-[13px] text-muted-foreground">
                                {team.original_name}
                            </div>
                        )}
                        <div className="mt-1.5 flex flex-wrap items-center gap-1.5 text-[13px]">
                            <Link
                                href={sport.url}
                                className="text-muted-foreground hover:text-foreground hover:underline"
                            >
                                {sport.name}
                            </Link>
                            {team.country && (
                                <span className="inline-flex items-center gap-1.5 text-muted-foreground">
                                    ·{' '}
                                    <Flag
                                        src={team.country.flag}
                                        label={team.country.name}
                                    />{' '}
                                    {team.country.name}
                                </span>
                            )}
                            {team.badges.map((badge) => (
                                <Badge key={badge}>{badge}</Badge>
                            ))}
                        </div>
                    </div>
                </header>

                {team.national && others.length > 0 && (
                    <section className="mb-4">
                        <h2 className="mb-2 text-[14px] font-semibold">
                            Другие сборные: {team.country?.name}
                        </h2>
                        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            {others.map((variant) => (
                                <TeamCard key={variant.id} team={variant} />
                            ))}
                        </div>
                    </section>
                )}

                <nav
                    className="mb-3 flex gap-1 overflow-x-auto border-b border-border"
                    aria-label="Разделы команды"
                >
                    {tabs.map((item) => (
                        <Link
                            key={item.key}
                            href={withQuery(team.url, {
                                tab: item.key === 'upcoming' ? null : item.key,
                            })}
                            preserveScroll
                            aria-current={item.key === tab ? 'page' : undefined}
                            className={cn(
                                '-mb-px shrink-0 border-b-2 px-3 py-2 text-[14px] font-medium',
                                item.key === tab
                                    ? 'border-brand'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {item.label}
                        </Link>
                    ))}
                </nav>

                {tab === 'upcoming' &&
                    (upcoming.length > 0 ? (
                        <>
                            <EventTimeline
                                events={upcoming}
                                groupBy="day"
                                showCompetition
                            />
                            {more && (
                                <MoreLink
                                    href={withQuery(team.url, {
                                        limit: limit + 30,
                                    })}
                                />
                            )}
                        </>
                    ) : (
                        <Empty
                            icon={CalendarClock}
                            title="Ближайших матчей нет"
                        />
                    ))}
                {tab === 'competitions' &&
                    (competitions.length > 0 ? (
                        <div className="overflow-hidden rounded-xl border border-border/70 bg-card">
                            {competitions.map((competition) => (
                                <CompetitionCard
                                    key={competition.id}
                                    competition={competition}
                                />
                            ))}
                        </div>
                    ) : (
                        <Empty icon={Trophy} title="Турниров пока нет" />
                    ))}
            </div>
        </>
    );
}
