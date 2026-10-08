import { Head, Link } from '@inertiajs/react';
import { Search as SearchIcon, UserRound } from 'lucide-react';
import { CompetitionCard, TeamCard } from '@/components/catalog/cards';
import { Flag } from '@/components/catalog/crest';
import { Empty } from '@/components/catalog/states';
import { SearchBox } from '@/components/search-box';
import { SPORT_EMOJI } from '@/lib/catalog';
import type { AthleteItem, CompetitionItem, TeamItem } from '@/types/catalog';

interface Props {
    query: string;
    too_short: boolean;
    results: {
        sports: { key: string; name: string; url: string; has_data: boolean }[];
        competitions: CompetitionItem[];
        teams: TeamItem[];
        national_teams: TeamItem[];
        players: AthleteItem[];
    };
}

function Section({
    title,
    count,
    children,
}: {
    title: string;
    count: number;
    children: React.ReactNode;
}) {
    if (count === 0) return null;

    return (
        <section aria-label={title}>
            <h2 className="mb-2 text-[15px] font-semibold">
                {title} <span className="text-muted-foreground">{count}</span>
            </h2>
            {children}
        </section>
    );
}

/**
 * Поиск: результаты по типам — виды спорта, турниры, команды, сборные,
 * игроки. У одинаковых названий — подписи, по которым их различить.
 */
export default function Search({ query, too_short, results }: Props) {
    const total =
        results.sports.length +
        results.competitions.length +
        results.teams.length +
        results.national_teams.length +
        results.players.length;

    return (
        <>
            <Head title={query ? `Поиск: ${query}` : 'Поиск'} />
            <div className="mx-auto flex max-w-4xl flex-col gap-6">
                <div>
                    <h1 className="mb-3 font-display text-2xl font-bold">
                        Поиск
                    </h1>
                    <SearchBox autoFocus={!query} />
                    <p className="mt-2 text-[12px] text-muted-foreground">
                        Ищет по английским и русским названиям: «Франция» и
                        «France» — одна и та же сборная.
                    </p>
                </div>

                {!query && (
                    <Empty icon={SearchIcon} title="Что найти?">
                        Команда, сборная, игрок, турнир или вид спорта.
                    </Empty>
                )}
                {too_short && (
                    <Empty icon={SearchIcon} title="Слишком коротко">
                        Введите хотя бы две буквы.
                    </Empty>
                )}
                {query && !too_short && total === 0 && (
                    <Empty icon={SearchIcon} title="Ничего не нашлось">
                        По запросу «{query}» ничего нет. Проверьте написание или
                        поищите на другом языке.
                    </Empty>
                )}

                <Section title="Виды спорта" count={results.sports.length}>
                    <div className="flex flex-wrap gap-2">
                        {results.sports.map((sport) => (
                            <Link
                                key={sport.key}
                                href={sport.url}
                                className="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-[14px] hover:bg-accent"
                            >
                                <span aria-hidden>
                                    {SPORT_EMOJI[sport.key]}
                                </span>
                                {sport.name}
                                {!sport.has_data && (
                                    <span className="text-[11px] text-muted-foreground">
                                        нет данных
                                    </span>
                                )}
                            </Link>
                        ))}
                    </div>
                </Section>

                <Section title="Турниры" count={results.competitions.length}>
                    <div className="overflow-hidden rounded-xl border border-border/70 bg-card">
                        {results.competitions.map((competition) => (
                            <CompetitionCard
                                key={competition.id}
                                competition={competition}
                                context={`${competition.sport.name} · ${competition.category.name}`}
                            />
                        ))}
                    </div>
                </Section>

                <Section title="Сборные" count={results.national_teams.length}>
                    <div className="grid gap-2 sm:grid-cols-2">
                        {results.national_teams.map((team) => (
                            <TeamCard
                                key={team.id}
                                team={{
                                    ...team,
                                    badges: [
                                        team.sport?.name ?? '',
                                        ...team.badges,
                                    ].filter(Boolean),
                                }}
                            />
                        ))}
                    </div>
                </Section>

                <Section title="Команды" count={results.teams.length}>
                    <div className="grid gap-2 sm:grid-cols-2">
                        {results.teams.map((team) => (
                            <TeamCard
                                key={team.id}
                                team={{
                                    ...team,
                                    badges: [
                                        team.sport?.name ?? '',
                                        ...team.badges,
                                    ].filter(Boolean),
                                }}
                            />
                        ))}
                    </div>
                </Section>

                <Section title="Игроки" count={results.players.length}>
                    <div className="grid gap-2 sm:grid-cols-2">
                        {results.players.map((player) => (
                            <Link
                                key={player.id}
                                href={player.url}
                                className="flex items-center gap-3 rounded-xl border border-border/70 bg-card px-3 py-2.5 hover:bg-accent"
                            >
                                <span className="flex size-9 items-center justify-center rounded-full bg-muted">
                                    <UserRound className="size-4 text-muted-foreground" />
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-[14px] font-semibold">
                                        {player.name}
                                    </span>
                                    <span className="flex items-center gap-1.5 text-[12px] text-muted-foreground">
                                        {player.sports
                                            ?.map((sport) => sport.name)
                                            .join(', ')}
                                        {player.country && (
                                            <>
                                                {' · '}
                                                <Flag
                                                    src={player.country.flag}
                                                />
                                                {player.country.name}
                                            </>
                                        )}
                                    </span>
                                </span>
                            </Link>
                        ))}
                    </div>
                </Section>
            </div>
        </>
    );
}
