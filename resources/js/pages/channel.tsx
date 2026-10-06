import { Head, Link } from '@inertiajs/react';
import { CalendarX2, ChevronRight, History } from 'lucide-react';
import { ChannelMark } from '@/components/channel-mark';
import { EmptyState } from '@/components/empty-state';
import { LiveDot } from '@/components/live-badge';
import { LivePlayer } from '@/components/live-player';
import { TeamCrest } from '@/components/team-crest';
import { useNow } from '@/hooks/use-now';
import { useTimezone } from '@/hooks/use-timezone';
import { dateKey, formatDayTab, formatTime } from '@/lib/format';
import type { ChannelSummary, SportEvent } from '@/types';

interface ScheduleItem {
    starts_at: string;
    ends_at: string;
    replay: boolean;
    event: SportEvent;
}

interface Props {
    channel: ChannelSummary;
    streamUrl: string;
    schedule: ScheduleItem[];
}

/** «Сегодня», «Завтра» или «8 окт.» — в колонке времени места мало. */
function shortDay(iso: string, timezone: string): string {
    const tab = formatDayTab(dateKey(iso, timezone), timezone);

    return ['Сегодня', 'Завтра', 'Вчера'].includes(tab.label)
        ? tab.label
        : tab.sub;
}

/**
 * Показ матча на канале: время — показа (не начала матча), повтор помечен.
 */
function AiringRow({ item }: { item: ScheduleItem }) {
    const timezone = useTimezone();
    const { event } = item;

    return (
        <Link
            href={`/events/${event.id}`}
            prefetch="hover"
            className="group flex items-center gap-3 rounded-2xl px-3 py-2.5 transition-colors hover:bg-accent/60"
        >
            <div className="w-16 shrink-0 text-right">
                <div className="text-[15px] font-semibold tabular-nums">
                    {formatTime(item.starts_at, timezone)}
                </div>
                <div className="text-[11px] text-muted-foreground">
                    {shortDay(item.starts_at, timezone)}
                </div>
            </div>
            {event.home && event.away ? (
                <div className="flex shrink-0 -space-x-2.5">
                    <TeamCrest
                        team={event.home}
                        className="size-9 text-[10px]"
                    />
                    <TeamCrest
                        team={event.away}
                        className="size-9 text-[10px]"
                    />
                </div>
            ) : (
                <span
                    className="flex size-9 shrink-0 items-center justify-center text-lg"
                    aria-hidden
                >
                    {event.sport.emoji}
                </span>
            )}
            <div className="min-w-0 flex-1">
                <div className="truncate text-[15px] font-semibold">
                    {event.title}
                </div>
                <div className="flex items-center gap-1.5 truncate text-[13px] text-muted-foreground">
                    {item.replay ? (
                        <>
                            <History className="size-3.5 shrink-0" /> Повтор
                        </>
                    ) : (
                        <span className="font-medium text-live">
                            Прямой эфир
                        </span>
                    )}
                    <span className="truncate">· {event.competition.name}</span>
                </div>
            </div>
            <ChevronRight className="size-4 shrink-0 text-muted-foreground transition group-hover:translate-x-0.5" />
        </Link>
    );
}

/** Канал: прямой эфир и матчи из расписания на нём — сейчас и дальше. */
export default function Channel({ channel, streamUrl, schedule }: Props) {
    const now = useNow();
    const timezone = useTimezone();
    const current = schedule.find(
        (item) =>
            now >= new Date(item.starts_at).getTime() &&
            now < new Date(item.ends_at).getTime(),
    );
    const upcoming = schedule.filter((item) => item !== current);

    return (
        <>
            <Head title={channel.name} />

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
                <div className="flex min-w-0 flex-col gap-4">
                    <div className="relative aspect-video overflow-hidden rounded-3xl bg-black shadow-2xl ring-1 shadow-black/30 ring-white/10">
                        <LivePlayer key={streamUrl} url={streamUrl} />
                    </div>

                    <div className="flex items-center gap-3">
                        <ChannelMark
                            channel={channel}
                            className="size-12 rounded-xl text-sm"
                        />
                        <div className="min-w-0">
                            <h1 className="font-display text-2xl font-bold">
                                {channel.name}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {channel.category ?? 'Dispatcharr'}
                            </p>
                        </div>
                    </div>

                    {current && (
                        <Link
                            href={`/events/${current.event.id}`}
                            className="flex items-center gap-3 rounded-2xl border border-border/60 bg-card/60 p-4 transition-colors hover:bg-accent/60"
                        >
                            {current.replay ? (
                                <span className="inline-flex items-center gap-1.5 text-sm text-muted-foreground">
                                    <History className="size-4" /> Повтор
                                </span>
                            ) : (
                                <span className="inline-flex items-center gap-1.5 text-sm font-semibold text-live">
                                    <LiveDot /> Сейчас
                                </span>
                            )}
                            <span className="min-w-0 truncate font-semibold">
                                {current.event.title}
                            </span>
                            <span className="ml-auto shrink-0 text-sm text-muted-foreground tabular-nums">
                                до {formatTime(current.ends_at, timezone)}
                            </span>
                        </Link>
                    )}
                </div>

                <aside>
                    <h2 className="mb-3 font-display text-lg font-semibold">
                        Матчи на канале
                    </h2>
                    {upcoming.length > 0 ? (
                        <div className="flex flex-col rounded-3xl border border-border/60 bg-card/50 p-1.5">
                            {upcoming.map((item) => (
                                <AiringRow
                                    key={`${item.event.id}-${item.starts_at}`}
                                    item={item}
                                />
                            ))}
                        </div>
                    ) : (
                        <EmptyState icon={CalendarX2} title="Матчей не видно">
                            В телегиде этого канала нет матчей из расписания.
                        </EmptyState>
                    )}
                </aside>
            </div>
        </>
    );
}
