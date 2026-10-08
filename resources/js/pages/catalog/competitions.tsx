import { Head, Link } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import { CompetitionCard } from '@/components/catalog/cards';
import { Flag } from '@/components/catalog/crest';
import { SportShell } from '@/components/catalog/sport-header';
import { Empty } from '@/components/catalog/states';
import type {
    CompetitionItem,
    SportPanelData,
    SportRef,
} from '@/types/catalog';

interface Section {
    key: string;
    name: string;
    kind: string;
    url: string;
    flag: string | null;
    parent_key: string | null;
    competitions: CompetitionItem[];
}

/** Все разделы и турниры вида спорта — каталог, не зависящий от сегодняшних матчей. */
export default function Competitions({
    sport,
    sections,
    panel,
}: {
    sport: SportRef;
    sections: Section[];
    panel: SportPanelData;
}) {
    const [filter, setFilter] = useState('');
    const needle = filter.trim().toLowerCase();
    const visible = sections
        .map((section) => ({
            ...section,
            competitions:
                needle && !section.name.toLowerCase().includes(needle)
                    ? section.competitions.filter((competition) =>
                          `${competition.name} ${competition.original_name ?? ''}`
                              .toLowerCase()
                              .includes(needle),
                      )
                    : section.competitions,
        }))
        .filter((section) => section.competitions.length > 0);

    return (
        <>
            <Head title={`${sport.name}: все турниры`} />
            <SportShell
                sport={sport}
                panel={panel}
                subtitle="Все разделы и турниры"
            >
                <div className="relative mb-4 max-w-md">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        type="search"
                        value={filter}
                        onChange={(event) => setFilter(event.target.value)}
                        placeholder="Страна или турнир"
                        aria-label="Фильтр турниров"
                        className="h-10 w-full rounded-lg border border-border bg-card pr-3 pl-9 text-[14px] outline-none focus:ring-2 focus:ring-ring/40"
                    />
                </div>
                <div className="columns-1 gap-4 lg:columns-2">
                    {visible.map((section) => (
                        <section
                            key={section.key}
                            className="mb-4 break-inside-avoid overflow-hidden rounded-xl border border-border/70 bg-card"
                        >
                            <Link
                                href={section.url}
                                className="flex items-center gap-2 border-b border-border/60 bg-muted/60 px-4 py-2 text-[13px] font-semibold hover:bg-muted"
                            >
                                <Flag src={section.flag} />
                                {section.name}
                            </Link>
                            {section.competitions.map((competition) => (
                                <CompetitionCard
                                    key={competition.id}
                                    competition={competition}
                                />
                            ))}
                        </section>
                    ))}
                </div>
                {visible.length === 0 && (
                    <Empty
                        icon={Search}
                        title={
                            needle ? 'Ничего не нашлось' : 'Турниров пока нет'
                        }
                    >
                        {needle
                            ? 'Попробуйте другое название или страну.'
                            : 'Каталог этого вида спорта ещё не загружен.'}
                    </Empty>
                )}
            </SportShell>
        </>
    );
}
