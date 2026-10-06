import { Tv } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { ChannelSummary } from '@/types';

/**
 * Логотип канала на светлой подложке (логотипы рисуют под светлый фон), без
 * логотипа — значок телевизора.
 */
export function ChannelMark({
    channel,
    className,
}: {
    channel: Pick<ChannelSummary, 'logo' | 'name'>;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'flex size-7 shrink-0 items-center justify-center overflow-hidden rounded-lg text-[10px] font-bold text-muted-foreground tabular-nums',
                channel.logo ? 'bg-white' : 'bg-secondary',
                className,
            )}
            title={channel.name}
        >
            {channel.logo ? (
                <img
                    src={channel.logo}
                    alt=""
                    loading="lazy"
                    className="size-[85%] object-contain"
                />
            ) : (
                <Tv className="size-[55%]" />
            )}
        </span>
    );
}
