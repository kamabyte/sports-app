import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { AlertTriangle, Clock } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/** Пустое состояние: что случилось и что можно сделать. */
export function Empty({
    icon: Icon,
    title,
    children,
    action,
    className,
}: {
    icon: LucideIcon;
    title: string;
    children?: ReactNode;
    action?: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-border px-6 py-12 text-center',
                className,
            )}
        >
            <div className="flex size-11 items-center justify-center rounded-xl bg-muted">
                <Icon className="size-5 text-muted-foreground" />
            </div>
            <h2 className="font-display text-base font-semibold">{title}</h2>
            {children && (
                <div className="max-w-md text-sm text-muted-foreground">
                    {children}
                </div>
            )}
            {action && <div className="mt-2">{action}</div>}
        </div>
    );
}

/** Заметка над содержимым: устаревшие данные, перенос, ошибка источника. */
export function Notice({
    tone = 'info',
    children,
    className,
}: {
    tone?: 'info' | 'warning' | 'error';
    children: ReactNode;
    className?: string;
}) {
    const Icon = { info: Clock, warning: Clock, error: AlertTriangle }[tone];

    return (
        <div
            role={tone === 'error' ? 'alert' : 'status'}
            className={cn(
                'flex items-start gap-2 rounded-lg border px-3 py-2 text-[13px]',
                tone === 'info' &&
                    'border-border bg-muted/50 text-muted-foreground',
                tone === 'warning' &&
                    'border-amber-500/30 bg-amber-500/10 text-amber-800 dark:text-amber-200',
                tone === 'error' &&
                    'border-destructive/30 bg-destructive/10 text-destructive',
                className,
            )}
        >
            <Icon className="mt-0.5 size-4 shrink-0" aria-hidden />
            <div>{children}</div>
        </div>
    );
}

/** Бейдж признака: «Сборная», «Женщины», «U21», «Парный разряд». */
export function Badge({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-md border border-border bg-muted/60 px-1.5 py-0.5 text-[11px] font-medium text-muted-foreground',
                className,
            )}
        >
            {children}
        </span>
    );
}

/** Скелет строк — пока загружается следующая страница. */
export function RowsSkeleton({ rows = 6 }: { rows?: number }) {
    return (
        <div
            className="overflow-hidden rounded-xl border border-border/70 bg-card"
            aria-hidden
        >
            {Array.from({ length: rows }, (_, index) => (
                <div
                    key={index}
                    className="flex items-center gap-3 border-b border-border/60 px-4 py-3 last:border-b-0"
                >
                    <div className="h-4 w-12 animate-pulse rounded bg-muted" />
                    <div className="flex flex-1 flex-col gap-2">
                        <div className="h-3.5 w-1/2 animate-pulse rounded bg-muted" />
                        <div className="h-3.5 w-2/5 animate-pulse rounded bg-muted" />
                    </div>
                </div>
            ))}
        </div>
    );
}

/** «Показать ещё»: следующая порция списка — тем же адресом с большим limit. */
export function MoreLink({ href }: { href: string }) {
    return (
        <Link
            href={href}
            preserveScroll
            preserveState
            className="mt-3 flex w-full items-center justify-center rounded-xl border border-border bg-card py-2.5 text-[14px] font-medium hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            Показать ещё
        </Link>
    );
}
