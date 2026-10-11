import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import {
    BroadcastList,
    ChannelLogo,
    splitBroadcasts,
} from '@/components/broadcast-list';
import { LivePlayer } from '@/components/live-player';
import { useNow } from '@/hooks/use-now';
import { useTimezone } from '@/hooks/use-timezone';
import { eventTitle, formatDay, formatTime } from '@/lib/catalog';
import { dateKey } from '@/lib/format';
import type { Broadcast } from '@/types';
import type { EventDetail } from '@/types/catalog';

interface Props {
    event: EventDetail;
    broadcasts: Broadcast[];
    broadcastId: number;
    /** Поток канала (с логином Xtream) — только на этой странице. */
    streamUrl: string | null;
    playlistUrl: string;
}

/**
 * Просмотр события: плеер канала и другие каналы, где событие идёт сейчас.
 * Повтор, который ещё не начался, открывается, но на канале пока идёт другое.
 */
export default function Watch({
    event,
    broadcasts,
    broadcastId,
    streamUrl,
    playlistUrl,
}: Props) {
    const timezone = useTimezone();
    const now = useNow();
    const broadcast =
        broadcasts.find((item) => item.id === broadcastId) ?? broadcasts[0];
    const { playable } = splitBroadcasts(broadcasts, now);
    // Переключаться — между каналами, где событие идёт; открытый остаётся в списке.
    const switchable = playable.some((item) => item.id === broadcast.id)
        ? playable
        : [broadcast, ...playable];
    const onAir =
        now >= new Date(broadcast.starts_at).getTime() &&
        now < new Date(broadcast.ends_at).getTime();
    const airs = `${formatDay(dateKey(broadcast.starts_at, timezone), timezone)}, ${formatTime(broadcast.starts_at, timezone)}–${formatTime(broadcast.ends_at, timezone)}`;
    const title = eventTitle(event);
    const eventUrl = `/event/${event.id}`;

    return (
        <>
            <Head title={`${title} · ${broadcast.channel}`} />

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
                <div className="flex min-w-0 flex-col gap-4">
                    <div className="relative aspect-video overflow-hidden rounded-xl bg-black ring-1 ring-border/60">
                        {streamUrl ? (
                            <LivePlayer
                                key={streamUrl}
                                url={streamUrl}
                                playlistUrl={playlistUrl}
                            />
                        ) : (
                            <div className="absolute inset-0 flex items-center justify-center p-6 text-center text-sm text-white/75">
                                У канала нет потока.
                            </div>
                        )}
                    </div>

                    <div className="flex items-start gap-3">
                        <Link
                            href={eventUrl}
                            className="mt-1 rounded-full p-1.5 text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            aria-label="К событию"
                        >
                            <ArrowLeft className="size-5" />
                        </Link>
                        <ChannelLogo
                            broadcast={broadcast}
                            className="size-12"
                        />
                        <div className="min-w-0">
                            <h1 className="font-display text-xl font-bold md:text-2xl">
                                <Link
                                    href={eventUrl}
                                    className="hover:underline"
                                >
                                    {title}
                                </Link>
                            </h1>
                            <p className="text-[13px] text-muted-foreground">
                                {event.competition.category.name}:{' '}
                                {event.competition.name}
                                {event.round && ` · ${event.round}`}
                            </p>
                            <p className="mt-1 text-[13px]">
                                <span className="font-semibold">
                                    {broadcast.channel}
                                </span>
                                <span className="text-muted-foreground">
                                    {' '}
                                    · {broadcast.replay
                                        ? 'повтор'
                                        : 'эфир'}{' '}
                                    {airs}
                                    {!onAir &&
                                        ' — сейчас на канале может идти другое'}
                                </span>
                            </p>
                        </div>
                    </div>
                </div>

                <aside>
                    <h2 className="mb-3 text-[15px] font-semibold">
                        Трансляции
                    </h2>
                    <BroadcastList
                        eventId={event.id}
                        broadcasts={switchable}
                        activeId={broadcast.id}
                        href={(item) => `/event/${event.id}/watch/${item.id}`}
                        compact
                    />
                </aside>
            </div>
        </>
    );
}
