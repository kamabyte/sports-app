import { Head, Link } from '@inertiajs/react';
import { Search, Tv } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ChannelMark } from '@/components/channel-mark';
import { EmptyState, PageTitle } from '@/components/empty-state';
import { LiveDot } from '@/components/live-badge';
import { groupBy } from '@/components/competition-group';
import type { ChannelSummary } from '@/types';

interface Props {
    channels: ChannelSummary[];
}

/** Все каналы источника по категориям; у канала — матч, который идёт сейчас. */
export default function Channels({ channels }: Props) {
    const [query, setQuery] = useState('');

    const groups = useMemo(() => {
        const needle = query.trim().toLowerCase();
        const visible = needle
            ? channels.filter((channel) =>
                  channel.name.toLowerCase().includes(needle),
              )
            : channels;

        return groupBy(
            visible,
            (channel) => channel.category ?? 'Без категории',
        );
    }, [channels, query]);

    return (
        <>
            <Head title="Каналы" />
            <PageTitle
                actions={
                    <div className="relative w-full md:w-72">
                        <Search className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            type="search"
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Название канала"
                            aria-label="Найти канал"
                            className="h-10 w-full rounded-full border border-transparent bg-black/[0.05] pr-4 pl-10 text-[15px] outline-none placeholder:text-muted-foreground focus:border-ring/60 focus:ring-4 focus:ring-ring/15 dark:bg-white/[0.07]"
                        />
                    </div>
                }
            >
                Каналы
            </PageTitle>

            <div className="flex flex-col gap-8">
                {groups.map((group) => (
                    <section key={group[0].category ?? 'none'}>
                        <h2 className="mb-3 flex items-baseline gap-2 font-display text-lg font-semibold">
                            {group[0].category ?? 'Без категории'}
                            <span className="text-sm font-normal text-muted-foreground">
                                {group.length}
                            </span>
                        </h2>
                        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                            {group.map((channel) => (
                                <Link
                                    key={channel.id}
                                    href={`/channels/${channel.id}`}
                                    prefetch="hover"
                                    className="flex items-center gap-3 rounded-2xl border border-border/60 bg-card/60 p-3 transition-colors hover:bg-accent/60"
                                >
                                    <ChannelMark
                                        channel={channel}
                                        className="size-11 rounded-xl text-xs"
                                    />
                                    <div className="min-w-0 flex-1">
                                        <div className="truncate font-semibold">
                                            {channel.name}
                                        </div>
                                        {channel.now ? (
                                            <div className="flex items-center gap-1.5 truncate text-[13px]">
                                                {channel.now.replay ? (
                                                    <span className="text-muted-foreground">
                                                        Повтор:
                                                    </span>
                                                ) : (
                                                    <LiveDot className="text-live" />
                                                )}
                                                <span className="truncate">
                                                    {channel.now.title}
                                                </span>
                                            </div>
                                        ) : (
                                            <div className="text-[13px] text-muted-foreground">
                                                Матчей из расписания сейчас нет
                                            </div>
                                        )}
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </section>
                ))}

                {groups.length === 0 && (
                    <EmptyState
                        icon={Tv}
                        title={query ? 'Таких каналов нет' : 'Каналов пока нет'}
                    >
                        {query
                            ? 'Попробуйте другое название или номер.'
                            : 'Каналы появятся после загрузки из Dispatcharr (guide:import).'}
                    </EmptyState>
                )}
            </div>
        </>
    );
}
