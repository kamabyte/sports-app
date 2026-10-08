import { Head, Link } from '@inertiajs/react';
import { CalendarClock, Trophy, UserRound, Users } from 'lucide-react';
import { CompetitionCard } from '@/components/catalog/cards';
import { Flag } from '@/components/catalog/crest';
import { Crumbs } from '@/components/catalog/crumbs';
import { EventTimeline } from '@/components/catalog/event-list';
import { Empty, MoreLink } from '@/components/catalog/states';
import { withQuery } from '@/lib/catalog';
import { cn } from '@/lib/utils';
import type {
    AthleteItem,
    CompetitionItem,
    EventItem,
    SportRef,
} from '@/types/catalog';

interface Props {
    athlete: AthleteItem & { gender: string | null };
    sport: SportRef | null;
    upcoming: EventItem[];
    competitions: CompetitionItem[];
    partnerships: { kind: string; partners: AthleteItem[] }[];
    tab: 'upcoming' | 'competitions';
    limit: number;
    more: boolean;
}

/**
 * Профиль спортсмена. Флаг — только если источник его показал; пары и
 * эстафеты — отдельные заявки, партнёры — ссылками на свои профили.
 */
export default function AthletePage({
    athlete,
    sport,
    upcoming,
    competitions,
    partnerships,
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
    const crumbs = [
        sport ? { label: sport.name, url: sport.url } : null,
        sport ? { label: 'Игроки', url: `${sport.url}/players` } : null,
        { label: athlete.name, url: null },
    ].filter(
        (crumb): crumb is { label: string; url: string | null } =>
            crumb !== null,
    );

    return (
        <>
            <Head title={`${athlete.name}${sport ? ` — ${sport.name}` : ''}`} />
            <div className="mx-auto max-w-5xl">
                <Crumbs items={crumbs} />
                <header className="mb-4 flex items-center gap-4 rounded-xl border border-border/70 bg-card p-4">
                    {athlete.portrait ? (
                        <img
                            src={athlete.portrait}
                            alt=""
                            className="size-16 rounded-full object-cover"
                        />
                    ) : (
                        <span className="flex size-16 items-center justify-center rounded-full bg-muted">
                            <UserRound className="size-7 text-muted-foreground" />
                        </span>
                    )}
                    <div className="min-w-0 flex-1">
                        <h1 className="font-display text-2xl font-bold">
                            {athlete.name}
                        </h1>
                        {athlete.original_name && (
                            <div className="text-[13px] text-muted-foreground">
                                {athlete.original_name}
                            </div>
                        )}
                        <div className="mt-1.5 flex flex-wrap items-center gap-2 text-[13px] text-muted-foreground">
                            {sport && (
                                <Link
                                    href={sport.url}
                                    className="hover:text-foreground hover:underline"
                                >
                                    {sport.name}
                                </Link>
                            )}
                            <span>·</span>
                            {athlete.country ? (
                                <span className="inline-flex items-center gap-1.5">
                                    <Flag
                                        src={athlete.country.flag}
                                        label={athlete.country.name}
                                    />
                                    {athlete.country.name}
                                </span>
                            ) : (
                                <span>Страна не указана источником</span>
                            )}
                        </div>
                    </div>
                </header>

                {partnerships.length > 0 && (
                    <section className="mb-4 rounded-xl border border-border/70 bg-card p-3">
                        <h2 className="mb-2 flex items-center gap-2 text-[14px] font-semibold">
                            <Users className="size-4" />
                            Партнёры по парам и эстафетам
                        </h2>
                        <ul className="flex flex-wrap gap-2">
                            {partnerships.map((partnership, index) => (
                                <li
                                    key={index}
                                    className="flex items-center gap-1.5 rounded-lg border border-border px-2.5 py-1 text-[13px]"
                                >
                                    <span className="text-muted-foreground">
                                        {partnership.kind === 'pair'
                                            ? 'Пара с'
                                            : 'Эстафета:'}
                                    </span>
                                    {partnership.partners.map(
                                        (partner, position) => (
                                            <span
                                                key={partner.id}
                                                className="inline-flex items-center gap-1"
                                            >
                                                {position > 0 && ', '}
                                                <Flag
                                                    src={partner.country?.flag}
                                                />
                                                <Link
                                                    href={partner.url}
                                                    className="font-medium hover:underline"
                                                >
                                                    {partner.name}
                                                </Link>
                                            </span>
                                        ),
                                    )}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <nav
                    className="mb-3 flex gap-1 overflow-x-auto border-b border-border"
                    aria-label="Разделы игрока"
                >
                    {tabs.map((item) => (
                        <Link
                            key={item.key}
                            href={withQuery(athlete.url, {
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
                                    href={withQuery(athlete.url, {
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
