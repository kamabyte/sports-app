import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, Globe2, ListTree, Search } from 'lucide-react';
import { useState } from 'react';
import { Crest, Flag } from '@/components/catalog/crest';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import type { PanelCategory, SportPanelData, SportRef } from '@/types/catalog';

const KIND_TITLES: Record<string, string> = {
    country: 'Страны',
    region: 'Международные',
    international: 'Международные',
    tour: 'Туры и разряды',
    series: 'Серии',
    discipline: 'Дисциплины',
    weight_class: 'Весовые категории',
    promotion: 'Промоушены',
    game: 'Игры',
    other: 'Другое',
};

/** Поиск по разделам в панели: поле остаётся заполненным, если закрыть и открыть панель. */
let rememberedFilter = '';

function CategoryLink({
    category,
    depth = 0,
    current,
}: {
    category: PanelCategory;
    depth?: number;
    current: string;
}) {
    const active =
        current === category.url || current.startsWith(`${category.url}/`);

    return (
        <Link
            href={category.url}
            aria-current={active ? 'page' : undefined}
            className={cn(
                'flex items-center gap-2 rounded-md px-2 py-1.5 text-[13px] hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                depth > 0 && 'ml-4',
                active && 'bg-accent font-semibold',
            )}
        >
            {category.flag ? (
                <Flag src={category.flag} />
            ) : category.kind === 'region' ? (
                <Globe2
                    className="h-3.5 w-[18px] shrink-0 text-muted-foreground"
                    aria-hidden
                />
            ) : (
                <span className="h-3 w-[18px] shrink-0" aria-hidden />
            )}
            <span className="min-w-0 flex-1 truncate">{category.name}</span>
            {category.competitions_count > 0 && (
                <span className="text-[11px] text-muted-foreground tabular-nums">
                    {category.competitions_count}
                </span>
            )}
        </Link>
    );
}

/** Содержимое панели: популярные турниры и все разделы с фильтром. */
export function SportPanelContent({
    sport,
    panel,
}: {
    sport: SportRef;
    panel: SportPanelData;
}) {
    const { url } = usePage();
    const current = url.split('?')[0];
    const [filter, setFilter] = useState(rememberedFilter);
    const [showAll, setShowAll] = useState(false);
    const needle = filter.trim().toLowerCase();

    const matches = (category: PanelCategory): boolean =>
        category.name.toLowerCase().includes(needle) ||
        (category.children ?? []).some(matches);
    const visible = needle
        ? panel.categories.filter(matches)
        : panel.categories;
    const byKind = visible.reduce<Record<string, PanelCategory[]>>(
        (groups, category) => {
            const title = KIND_TITLES[category.kind] ?? 'Разделы';
            (groups[title] ??= []).push(category);

            return groups;
        },
        {},
    );
    const limit = 24;
    let shown = 0;

    return (
        <div className="flex flex-col gap-5">
            {panel.popular.length > 0 && !needle && (
                <section>
                    <h2 className="mb-1.5 px-2 text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">
                        Популярные турниры
                    </h2>
                    <div className="flex flex-col">
                        {panel.popular.map((competition) => (
                            <Link
                                key={competition.id}
                                href={competition.url}
                                aria-current={
                                    current.startsWith(competition.url)
                                        ? 'page'
                                        : undefined
                                }
                                className={cn(
                                    'flex items-center gap-2 rounded-md px-2 py-1.5 text-[13px] hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                    current.startsWith(competition.url) &&
                                        'bg-accent font-semibold',
                                )}
                            >
                                {competition.category.flag ? (
                                    <Flag src={competition.category.flag} />
                                ) : (
                                    <Crest
                                        name={competition.name}
                                        logo={competition.logo}
                                        className="size-[18px]"
                                    />
                                )}
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate">
                                        {competition.name}
                                    </span>
                                    <span className="block truncate text-[11px] text-muted-foreground">
                                        {competition.category.name}
                                    </span>
                                </span>
                            </Link>
                        ))}
                    </div>
                </section>
            )}

            <section>
                <div className="relative mb-2">
                    <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
                    <input
                        type="search"
                        value={filter}
                        onChange={(event) => {
                            rememberedFilter = event.target.value;
                            setFilter(event.target.value);
                        }}
                        placeholder="Найти страну или раздел"
                        aria-label={`Найти раздел: ${sport.name}`}
                        className="h-8 w-full rounded-md border border-border bg-background pr-2 pl-8 text-[13px] outline-none focus:ring-2 focus:ring-ring/40"
                    />
                </div>
                {Object.entries(byKind).map(([title, categories]) => (
                    <div key={title} className="mb-3">
                        <h2 className="mb-1 px-2 text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">
                            {title}
                        </h2>
                        {categories.map((category) => {
                            if (!showAll && !needle && shown >= limit)
                                return null;
                            shown++;

                            return (
                                <div key={category.key}>
                                    <CategoryLink
                                        category={category}
                                        current={current}
                                    />
                                    {(category.children ?? []).map((child) => (
                                        <CategoryLink
                                            key={child.key}
                                            category={child}
                                            depth={1}
                                            current={current}
                                        />
                                    ))}
                                </div>
                            );
                        })}
                    </div>
                ))}
                {!needle && !showAll && panel.categories.length > limit && (
                    <button
                        type="button"
                        onClick={() => setShowAll(true)}
                        className="flex items-center gap-1 px-2 text-[13px] text-muted-foreground hover:text-foreground"
                    >
                        <ChevronDown className="size-3.5" />
                        Все разделы ({panel.categories.length})
                    </button>
                )}
                {visible.length === 0 && (
                    <p className="px-2 text-[13px] text-muted-foreground">
                        {needle
                            ? 'Ничего не нашлось.'
                            : 'Разделов пока нет — каталог этого вида спорта ещё не загружен.'}
                    </p>
                )}
            </section>
        </div>
    );
}

/** Панель разделов: на широком экране — сбоку, на узком — по кнопке. */
export function SportPanel({
    sport,
    panel,
}: {
    sport: SportRef;
    panel: SportPanelData;
}) {
    return (
        <aside
            className="hidden w-60 shrink-0 xl:block"
            aria-label={`Разделы: ${sport.name}`}
        >
            <div className="sticky top-20 max-h-[calc(100dvh-6rem)] overflow-y-auto pr-1 pb-6">
                <SportPanelContent sport={sport} panel={panel} />
            </div>
        </aside>
    );
}

export function SportPanelButton({
    sport,
    panel,
}: {
    sport: SportRef;
    panel: SportPanelData;
}) {
    return (
        <Sheet>
            <SheetTrigger asChild>
                <button
                    type="button"
                    className="inline-flex h-9 items-center gap-2 rounded-lg border border-border bg-card px-3 text-[13px] font-medium hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none xl:hidden"
                >
                    <ListTree className="size-4" />
                    <span className="hidden sm:inline">Страны и турниры</span>
                    <span className="sm:hidden">Разделы</span>
                </button>
            </SheetTrigger>
            <SheetContent
                side="left"
                className="w-[88vw] max-w-sm overflow-y-auto p-4"
            >
                <SheetHeader className="p-0">
                    <SheetTitle>{sport.name}: разделы</SheetTitle>
                </SheetHeader>
                <SportPanelContent sport={sport} panel={panel} />
            </SheetContent>
        </Sheet>
    );
}
