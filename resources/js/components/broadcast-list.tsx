import { Link } from '@inertiajs/react';
import { History, Play, Tv } from 'lucide-react';
import { LiveDot } from '@/components/live-badge';
import { useTimezone } from '@/hooks/use-timezone';
import {
    dateKey,
    flag,
    formatDay,
    formatTime,
    languageName,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Broadcast } from '@/types';

export function QualityBadge({ quality }: { quality: Broadcast['quality'] }) {
    if (!quality) return null;

    return (
        <span
            className={cn(
                'rounded-md border px-1.5 py-px text-[10px] font-bold tracking-wide',
                quality === '4K'
                    ? 'border-brand/40 text-brand'
                    : 'border-border text-muted-foreground',
            )}
        >
            {quality}
        </span>
    );
}

export function ChannelLogo({
    broadcast,
    className,
}: {
    broadcast: Broadcast;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-xl text-xs font-bold text-muted-foreground tabular-nums',
                // Логотипы каналов рисуют под светлый фон: на тёмном часть пропадает.
                broadcast.logo ? 'bg-white' : 'bg-secondary',
                className,
            )}
        >
            {broadcast.logo ? (
                <img
                    src={broadcast.logo}
                    alt=""
                    loading="lazy"
                    className="size-full object-contain p-1.5"
                />
            ) : (
                <Tv className="size-1/2" />
            )}
        </span>
    );
}

/** «В эфире», «LIVE» или «Повтор · чт, 03:00». */
function BroadcastWhen({ broadcast }: { broadcast: Broadcast }) {
    const timezone = useTimezone();
    const when = `${formatDay(dateKey(broadcast.starts_at, timezone), timezone)}, ${formatTime(broadcast.starts_at, timezone)}`;

    if (broadcast.replay) {
        return (
            <span className="inline-flex items-center gap-1 text-muted-foreground">
                <History className="size-3.5" />
                Повтор · {when}
            </span>
        );
    }

    if (broadcast.live) {
        return (
            <span className="inline-flex items-center gap-1 font-semibold text-live">
                <LiveDot />
                Прямой эфир
            </span>
        );
    }

    return <span>{when}</span>;
}

/**
 * Что можно включить: показы самого матча (прямой эфир, одновременные) и
 * повтор, который идёт прямо сейчас. Повтор позже — только подсказка «когда»:
 * архива у каналов нет, а «включить» его сейчас значит открыть канал, на
 * котором идёт другое. Прошедшие повторы не нужны совсем.
 */
export function splitBroadcasts(
    broadcasts: Broadcast[],
    now: number,
): { playable: Broadcast[]; replays: Broadcast[] } {
    const onAir = (broadcast: Broadcast) =>
        now >= new Date(broadcast.starts_at).getTime() &&
        now < new Date(broadcast.ends_at).getTime();

    return {
        playable: broadcasts.filter(
            (broadcast) => !broadcast.replay || onAir(broadcast),
        ),
        replays: broadcasts
            .filter(
                (broadcast) =>
                    broadcast.replay &&
                    !onAir(broadcast) &&
                    new Date(broadcast.starts_at).getTime() > now,
            )
            .sort(
                (a, b) =>
                    new Date(a.starts_at).getTime() -
                    new Date(b.starts_at).getTime(),
            ),
    };
}

/** Повторы впереди: канал и время, без кнопки — включать их сейчас нечего. */
export function ReplayList({ broadcasts }: { broadcasts: Broadcast[] }) {
    const timezone = useTimezone();

    return (
        <div className="flex flex-col divide-y divide-border/60 rounded-2xl border border-border/60 bg-card/40">
            {broadcasts.map((broadcast) => (
                <div
                    key={broadcast.id}
                    className="flex items-center gap-3 px-3 py-2.5"
                >
                    <ChannelLogo
                        broadcast={broadcast}
                        className="size-8 rounded-lg"
                    />
                    <span className="min-w-0 flex-1 truncate text-sm font-medium">
                        {broadcast.channel}
                    </span>
                    <span className="inline-flex shrink-0 items-center gap-1.5 text-[13px] text-muted-foreground tabular-nums">
                        <History className="size-3.5" />
                        {formatDay(
                            dateKey(broadcast.starts_at, timezone),
                            timezone,
                        )}
                        , {formatTime(broadcast.starts_at, timezone)}
                    </span>
                </div>
            ))}
        </div>
    );
}

/**
 * «Где смотреть»: сначала то, что идёт вместе с основным показом (прямой
 * эфир — первым), потом повторы.
 */
export function BroadcastList({
    eventId,
    broadcasts,
    activeId,
    compact = false,
}: {
    eventId: string;
    broadcasts: Broadcast[];
    activeId?: number;
    compact?: boolean;
}) {
    return (
        <div className="flex flex-col gap-2">
            {broadcasts.map((broadcast) => {
                const active = broadcast.id === activeId;
                const details = [
                    broadcast.country ? flag(broadcast.country) : null,
                    broadcast.language
                        ? languageName(broadcast.language)
                        : null,
                    broadcast.provider,
                ]
                    .filter(Boolean)
                    .join(' · ');

                return (
                    <Link
                        key={broadcast.id}
                        href={`/events/${eventId}/watch/${broadcast.id}`}
                        preserveScroll
                        aria-current={active ? 'true' : undefined}
                        className={cn(
                            'group flex items-center gap-3 rounded-2xl border p-3 transition-colors',
                            active
                                ? 'border-brand/50 bg-brand/10'
                                : 'border-border/60 bg-card/60 hover:bg-accent/60',
                        )}
                    >
                        <ChannelLogo broadcast={broadcast} />
                        <div className="min-w-0 flex-1">
                            <div className="flex items-center gap-2">
                                <span className="truncate font-semibold">
                                    {broadcast.channel}
                                </span>
                                <QualityBadge quality={broadcast.quality} />
                            </div>
                            <div className="flex flex-wrap items-center gap-x-2 text-[13px] text-muted-foreground">
                                <BroadcastWhen broadcast={broadcast} />
                                {details && (
                                    <span className="truncate">{details}</span>
                                )}
                            </div>
                            {!compact && (
                                <div className="mt-0.5 truncate text-xs text-muted-foreground/70">
                                    ТВ-гид: {broadcast.epg_title}
                                </div>
                            )}
                        </div>
                        <span
                            className={cn(
                                'flex size-9 shrink-0 items-center justify-center rounded-full transition',
                                active
                                    ? 'bg-brand text-brand-foreground'
                                    : 'bg-secondary text-foreground group-hover:bg-foreground group-hover:text-background',
                            )}
                        >
                            <Play className="size-4 translate-x-px fill-current" />
                        </span>
                    </Link>
                );
            })}
        </div>
    );
}
