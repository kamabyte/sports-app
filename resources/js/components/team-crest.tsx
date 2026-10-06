import { useState } from 'react';
import { cn } from '@/lib/utils';
import type { Team } from '@/types';

/** Эмблема команды; пока её нет (или не загрузилась) — цвет и короткое имя. */
export function TeamCrest({
    team,
    className,
}: {
    team: Team;
    className?: string;
}) {
    const [broken, setBroken] = useState(false);

    // Сборная — круглый флаг на весь значок.
    if (team.logo && team.flag && !broken) {
        return (
            <span
                className={cn(
                    'flex size-10 shrink-0 overflow-hidden rounded-full shadow-md ring-1 ring-white/25',
                    className,
                )}
                title={team.name}
            >
                <img
                    src={team.logo}
                    alt={team.name}
                    loading="lazy"
                    className="size-full object-cover"
                    onError={() => setBroken(true)}
                />
            </span>
        );
    }

    if (team.logo && !broken) {
        return (
            <span
                className={cn(
                    'flex size-10 shrink-0 items-center justify-center rounded-full bg-white/12 shadow-md ring-1 ring-white/20 backdrop-blur-md',
                    className,
                )}
                title={team.name}
            >
                <img
                    src={team.logo}
                    alt={team.name}
                    loading="lazy"
                    className="size-[72%] object-contain"
                    onError={() => setBroken(true)}
                />
            </span>
        );
    }

    return (
        <span
            className={cn(
                'flex size-10 shrink-0 items-center justify-center rounded-full font-display text-[11px] font-bold tracking-tight text-white shadow-md ring-1 ring-white/20 [text-shadow:0_1px_2px_rgb(0_0_0/0.45)]',
                className,
            )}
            style={{
                background: `linear-gradient(150deg, ${team.color}, color-mix(in oklch, ${team.color} 55%, black))`,
            }}
            title={team.name}
        >
            {team.short_name}
        </span>
    );
}
