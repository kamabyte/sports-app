import { Head, Link, router } from '@inertiajs/react';
import { Search, UserRound } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { Flag } from '@/components/catalog/crest';
import { SportShell } from '@/components/catalog/sport-header';
import { Empty } from '@/components/catalog/states';
import type { AthleteItem, SportPanelData, SportRef } from '@/types/catalog';

/** Игроки вида спорта: поиск по любому написанию имени (латиницей и по-русски). */
export default function Players({
    sport,
    query,
    players,
    panel,
}: {
    sport: SportRef;
    query: string;
    players: AthleteItem[];
    panel: SportPanelData;
}) {
    const [value, setValue] = useState(query);
    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            `${sport.url}/players`,
            value.trim() ? { q: value.trim() } : {},
            { preserveState: true },
        );
    };

    return (
        <>
            <Head title={`${sport.name}: игроки`} />
            <SportShell
                sport={sport}
                panel={panel}
                subtitle="Профили спортсменов"
            >
                <form
                    onSubmit={submit}
                    role="search"
                    className="relative mb-4 max-w-md"
                >
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        type="search"
                        value={value}
                        onChange={(event) => setValue(event.target.value)}
                        placeholder="Имя игрока"
                        aria-label="Найти игрока"
                        className="h-10 w-full rounded-lg border border-border bg-card pr-3 pl-9 text-[14px] outline-none focus:ring-2 focus:ring-ring/40"
                    />
                </form>
                {players.length > 0 ? (
                    <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                        {players.map((player) => (
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
                                        {player.country ? (
                                            <>
                                                <Flag
                                                    src={player.country.flag}
                                                />
                                                {player.country.name}
                                            </>
                                        ) : (
                                            'Страна не указана источником'
                                        )}
                                    </span>
                                </span>
                            </Link>
                        ))}
                    </div>
                ) : (
                    <Empty
                        icon={UserRound}
                        title={query ? 'Никого не нашлось' : 'Игроков пока нет'}
                    >
                        {query
                            ? 'Проверьте написание — можно латиницей или по-русски.'
                            : 'Профили появятся вместе с матчами.'}
                    </Empty>
                )}
            </SportShell>
        </>
    );
}
