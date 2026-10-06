import AppLogoIcon from '@/components/app-logo-icon';
import { cn } from '@/lib/utils';

export function LogoMark({ className }: { className?: string }) {
    return (
        <span
            className={cn(
                'flex size-9 items-center justify-center rounded-xl bg-brand text-brand-foreground shadow-md shadow-brand/25',
                className,
            )}
        >
            <AppLogoIcon className="size-5 fill-current" />
        </span>
    );
}

export function Logo({ className }: { className?: string }) {
    return (
        <span className={cn('inline-flex items-center gap-2.5', className)}>
            <LogoMark />
            <span className="font-display text-xl font-bold">Sports</span>
        </span>
    );
}
