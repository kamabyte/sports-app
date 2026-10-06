import { Link, usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';

/**
 * Фильтр по видам спорта. hrefFor строит адрес для вида (null — «Все»).
 */
export function SportChips({
    active,
    hrefFor,
}: {
    active: string | null;
    hrefFor: (sport: string | null) => string;
}) {
    const { sports } = usePage().props;

    const chip = (key: string | null, label: string, emoji?: string) => (
        <Link
            key={key ?? 'all'}
            href={hrefFor(key)}
            preserveScroll
            className={cn(
                'inline-flex h-9 shrink-0 items-center gap-1.5 rounded-full border px-4 text-sm font-medium transition-colors',
                active === key
                    ? 'border-transparent bg-foreground text-background'
                    : 'border-border bg-card/60 text-muted-foreground hover:bg-accent hover:text-foreground',
            )}
        >
            {emoji && <span aria-hidden>{emoji}</span>}
            {label}
        </Link>
    );

    return (
        <div className="-mx-4 scrollbar-none flex gap-2 overflow-x-auto px-4 md:-mx-6 md:px-6 lg:-mx-8 lg:px-8">
            {chip(null, 'Все')}
            {sports.map((sport) => chip(sport.key, sport.name, sport.emoji))}
        </div>
    );
}
