import { Head, Link } from '@inertiajs/react';
import { Shield } from 'lucide-react';
import { TeamCard } from '@/components/catalog/cards';
import { Flag } from '@/components/catalog/crest';
import { SportShell } from '@/components/catalog/sport-header';
import { Empty } from '@/components/catalog/states';
import { withQuery } from '@/lib/catalog';
import { cn } from '@/lib/utils';
import type { SportPanelData, SportRef, TeamItem } from '@/types/catalog';

interface Props {
    sport: SportRef;
    countries: {
        code: string;
        name: string;
        flag: string | null;
        teams: number;
    }[];
    country: { code: string; name: string; flag: string | null } | null;
    teams: TeamItem[];
    without_country: TeamItem[];
    panel: SportPanelData;
}

/**
 * Сборные: страна → её сборные (основная, женская, молодёжные, олимпийская).
 * Это справочник, а не турнир: матчи сборных — в их настоящих турнирах.
 */
export default function NationalTeams({
    sport,
    countries,
    country,
    teams,
    without_country,
    panel,
}: Props) {
    const path = `${sport.url}/national-teams`;

    return (
        <>
            <Head
                title={`${sport.name}: сборные${country ? ` — ${country.name}` : ''}`}
            />
            <SportShell
                sport={sport}
                panel={panel}
                subtitle="Сборные по странам: у каждой команды — своя страница и свои матчи"
            >
                <div className="grid gap-6 lg:grid-cols-[16rem_minmax(0,1fr)]">
                    <nav
                        aria-label="Страны"
                        className="flex max-h-[60vh] flex-col overflow-y-auto rounded-xl border border-border/70 bg-card p-1 lg:max-h-[70vh]"
                    >
                        {countries.map((item) => (
                            <Link
                                key={item.code}
                                href={withQuery(path, { country: item.code })}
                                preserveScroll
                                aria-current={
                                    country?.code === item.code
                                        ? 'page'
                                        : undefined
                                }
                                className={cn(
                                    'flex items-center gap-2 rounded-md px-2.5 py-1.5 text-[14px] hover:bg-accent',
                                    country?.code === item.code &&
                                        'bg-accent font-semibold',
                                )}
                            >
                                <Flag src={item.flag} />
                                <span className="flex-1 truncate">
                                    {item.name}
                                </span>
                                <span className="text-[12px] text-muted-foreground">
                                    {item.teams}
                                </span>
                            </Link>
                        ))}
                        {countries.length === 0 && (
                            <p className="p-3 text-[13px] text-muted-foreground">
                                Сборных пока нет.
                            </p>
                        )}
                    </nav>
                    <div>
                        {country ? (
                            <>
                                <h2 className="mb-3 flex items-center gap-2 text-lg font-semibold">
                                    <Flag
                                        src={country.flag}
                                        className="h-4 w-6"
                                    />
                                    {country.name}: сборные
                                </h2>
                                <div className="grid gap-2 sm:grid-cols-2">
                                    {teams.map((team) => (
                                        <TeamCard key={team.id} team={team} />
                                    ))}
                                </div>
                            </>
                        ) : (
                            <Empty icon={Shield} title="Выберите страну">
                                У страны может быть несколько сборных —
                                основная, женская, молодёжные и олимпийская. Это
                                разные команды.
                            </Empty>
                        )}
                        {without_country.length > 0 && (
                            <section className="mt-6">
                                <h2 className="mb-2 text-[14px] font-semibold">
                                    Страна не указана источником
                                </h2>
                                <div className="grid gap-2 sm:grid-cols-2">
                                    {without_country.map((team) => (
                                        <TeamCard key={team.id} team={team} />
                                    ))}
                                </div>
                            </section>
                        )}
                    </div>
                </div>
            </SportShell>
        </>
    );
}
