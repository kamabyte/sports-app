import { CompetitionMark } from '@/components/competition-badge';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import {
    BroadcastList,
    ChannelLogo,
    QualityBadge,
    splitBroadcasts,
} from '@/components/broadcast-list';
import { LiveBadge } from '@/components/live-badge';
import { LivePlayer } from '@/components/live-player';
import { Scoreboard } from '@/components/scoreboard';
import { useNow } from '@/hooks/use-now';
import { useTimezone } from '@/hooks/use-timezone';
import { eventBackdrop } from '@/lib/event-visuals';
import { dateKey, formatDay, formatTime, liveState } from '@/lib/format';
import type { Broadcast, SportEventDetail } from '@/types';

interface Props {
    event: SportEventDetail;
    broadcastId: number;
    /** Поток канала; null — у источника потока нет (фейковый каталог). */
    streamUrl: string | null;
    playlistUrl: string;
}

/** Заставка вместо видео, когда потока нет. */
function DemoScreen({
    event,
    broadcast,
}: {
    event: SportEventDetail;
    broadcast: Broadcast;
}) {
    const now = useNow();
    const { status } = liveState(event, now);

    return (
        <>
            <div
                className="absolute inset-0 animate-gradient bg-[length:220%_220%] opacity-90"
                style={eventBackdrop(event, 'ff')}
            />
            <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_center,transparent_20%,rgb(0_0_0/0.65))]" />
            <div className="absolute inset-0 flex flex-col items-center justify-center gap-4 p-6">
                <Scoreboard event={event} size="md" />
                <span className="rounded-full bg-black/40 px-3 py-1 text-xs text-white/75 backdrop-blur-md">
                    Демо: у этого источника нет потока
                </span>
            </div>
            <div className="absolute inset-x-4 top-4 flex items-start justify-between gap-3">
                <span className="inline-flex items-center gap-2 rounded-full bg-black/45 px-3 py-1.5 text-sm font-semibold text-white backdrop-blur-md">
                    {broadcast.channel}
                    <QualityBadge quality={broadcast.quality} />
                </span>
                {status === 'live' && <LiveBadge label={event.live_label} />}
            </div>
        </>
    );
}

export default function Watch({
    event,
    broadcastId,
    streamUrl,
    playlistUrl,
}: Props) {
    const timezone = useTimezone();
    const broadcast =
        event.broadcasts.find((item) => item.id === broadcastId) ??
        event.broadcasts[0];
    const now = useNow();
    // Переключаться — только между каналами, где матч идёт сейчас; открытый
    // канал остаётся в списке, даже если это повтор, который ещё не начался.
    const { playable } = splitBroadcasts(event.broadcasts, now);
    const switchable = playable.some((item) => item.id === broadcast.id)
        ? playable
        : [broadcast, ...playable];
    const onAir =
        now >= new Date(broadcast.starts_at).getTime() &&
        now < new Date(broadcast.ends_at).getTime();
    const airs = `${formatDay(dateKey(broadcast.starts_at, timezone), timezone)}, ${formatTime(broadcast.starts_at, timezone)}–${formatTime(broadcast.ends_at, timezone)}`;

    return (
        <>
            <Head title={`${event.title} · ${broadcast.channel}`} />

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
                <div className="flex min-w-0 flex-col gap-4">
                    <div className="relative aspect-video overflow-hidden rounded-3xl bg-black shadow-2xl ring-1 shadow-black/30 ring-white/10">
                        {streamUrl ? (
                            <LivePlayer
                                key={streamUrl}
                                url={streamUrl}
                                playlistUrl={playlistUrl}
                            />
                        ) : (
                            <DemoScreen event={event} broadcast={broadcast} />
                        )}
                    </div>

                    <div className="flex items-start gap-3">
                        <Link
                            href={`/events/${event.id}`}
                            className="mt-1 rounded-full p-1.5 text-muted-foreground hover:bg-accent hover:text-foreground"
                            aria-label="К событию"
                        >
                            <ArrowLeft className="size-5" />
                        </Link>
                        <ChannelLogo
                            broadcast={broadcast}
                            className="size-12"
                        />
                        <div className="min-w-0">
                            <h1 className="font-display text-2xl font-bold">
                                {event.title}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {event.sport.emoji}{' '}
                                <CompetitionMark
                                    competition={event.competition}
                                    className="inline-flex align-[-2px]"
                                />{' '}
                                {event.competition.name}
                                {event.round && ` · ${event.round}`}
                            </p>
                            <p className="mt-1 text-sm">
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
                    <h2 className="mb-3 font-display text-lg font-semibold">
                        Трансляции
                    </h2>
                    <BroadcastList
                        eventId={event.id}
                        broadcasts={switchable}
                        activeId={broadcast.id}
                        compact
                    />
                </aside>
            </div>
        </>
    );
}
