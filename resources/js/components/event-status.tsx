import { LiveBadge } from '@/components/live-badge';
import { useNow } from '@/hooks/use-now';
import { useTimezone } from '@/hooks/use-timezone';
import {
    formatCountdown,
    formatDay,
    formatTime,
    dateKey,
    liveState,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import type { SportEvent, SportEventDetail } from '@/types';

/**
 * Плашка в углу карточки: LIVE, «через 25 мин», время или «Завершён».
 */
export function EventStatusPill({
    event,
    className,
}: {
    event: SportEvent | SportEventDetail;
    className?: string;
}) {
    const now = useNow();
    const timezone = useTimezone();
    const { status } = liveState(event, now);

    if (status === 'live')
        return <LiveBadge label={event.live_label} className={className} />;

    const soon = new Date(event.starts_at).getTime() - now < 3 * 3_600_000;
    const day = dateKey(event.starts_at, timezone);
    const text =
        status === 'finished'
            ? 'Завершён'
            : soon
              ? formatCountdown(event.starts_at, now)
              : day === dateKey(new Date(now), timezone)
                ? formatTime(event.starts_at, timezone)
                : `${formatDay(day, timezone)}, ${formatTime(event.starts_at, timezone)}`;

    return (
        <span
            className={cn(
                'inline-flex h-6 items-center rounded-full bg-black/45 px-2.5 text-[11px] font-semibold text-white/90 tabular-nums backdrop-blur-md',
                className,
            )}
        >
            {text}
        </span>
    );
}
