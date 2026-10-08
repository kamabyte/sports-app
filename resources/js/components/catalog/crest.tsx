import { useState } from 'react';
import { cn } from '@/lib/utils';

/** Флаг страны — маленький, с подписью для чтения с экрана. */
export function Flag({
    src,
    label,
    className,
}: {
    src: string | null | undefined;
    label?: string | null;
    className?: string;
}) {
    const [broken, setBroken] = useState(false);
    if (!src || broken) {
        return null;
    }

    return (
        <img
            src={src}
            alt={label ?? ''}
            title={label ?? undefined}
            loading="lazy"
            className={cn(
                'inline-block h-3 w-[18px] shrink-0 rounded-[2px] object-cover ring-1 ring-black/10 dark:ring-white/15',
                className,
            )}
            onError={() => setBroken(true)}
        />
    );
}

/** Инициалы, если эмблемы нет: «Manchester City» → «MC». */
function initials(name: string, code?: string | null): string {
    if (code) return code.slice(0, 3);
    const words = name
        .replace(/[^\p{L}\p{N}\s]/gu, ' ')
        .split(/\s+/)
        .filter(Boolean);

    return (
        words.length > 1 ? words[0][0] + words[1][0] : name.slice(0, 2)
    ).toUpperCase();
}

/**
 * Эмблема команды или турнира; у сборной — флаг на весь круг. Пока эмблемы
 * нет или она не загрузилась — нейтральный кружок с инициалами (не путается с
 * настоящей эмблемой).
 */
export function Crest({
    name,
    logo,
    national = false,
    code,
    className,
}: {
    name: string;
    logo: string | null | undefined;
    national?: boolean;
    code?: string | null;
    className?: string;
}) {
    const [broken, setBroken] = useState(false);

    if (logo && !broken) {
        return (
            <span
                className={cn(
                    'flex size-6 shrink-0 items-center justify-center overflow-hidden',
                    national
                        ? 'rounded-full ring-1 ring-black/10 dark:ring-white/15'
                        : 'rounded-md',
                    className,
                )}
            >
                <img
                    src={logo}
                    alt=""
                    loading="lazy"
                    className={cn(
                        'size-full',
                        national ? 'object-cover' : 'object-contain',
                    )}
                    onError={() => setBroken(true)}
                />
            </span>
        );
    }

    return (
        <span
            aria-hidden
            className={cn(
                'flex size-6 shrink-0 items-center justify-center rounded-full bg-muted text-[9px] font-bold tracking-tight text-muted-foreground',
                className,
            )}
        >
            {initials(name, code)}
        </span>
    );
}
