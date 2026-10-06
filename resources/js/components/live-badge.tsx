import { cn } from '@/lib/utils';

export function LiveDot({ className }: { className?: string }) {
    return (
        <span
            className={cn(
                'size-1.5 animate-live-pulse rounded-full bg-current',
                className,
            )}
            aria-hidden
        />
    );
}

/** «● LIVE 67′» — красная плашка события в эфире. */
export function LiveBadge({
    label,
    className,
}: {
    label?: string | null;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex h-6 items-center gap-1.5 rounded-full bg-live px-2.5 text-[11px] font-bold tracking-wide text-white uppercase shadow-sm',
                className,
            )}
        >
            <LiveDot />
            Live
            {label && (
                <span className="font-semibold normal-case tabular-nums opacity-90">
                    {label}
                </span>
            )}
        </span>
    );
}
