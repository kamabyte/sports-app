import { useState } from 'react';
import { flag } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Competition } from '@/types';

/**
 * Значок турнира: эмблема, если она есть, иначе флаг страны. Эмблемы рисуют
 * под светлый фон — подложка светлая.
 */
export function CompetitionMark({
    competition,
    className,
}: {
    competition: Competition;
    className?: string;
}) {
    const [broken, setBroken] = useState(false);

    if (competition.logo && !broken) {
        return (
            <span
                className={cn(
                    'inline-flex size-4 shrink-0 items-center justify-center rounded-[5px] bg-white',
                    className,
                )}
                aria-hidden
            >
                <img
                    src={competition.logo}
                    alt=""
                    loading="lazy"
                    className="size-[85%] object-contain"
                    onError={() => setBroken(true)}
                />
            </span>
        );
    }

    const country = flag(competition.country);

    return country ? (
        <span className={cn('shrink-0', className)} aria-hidden>
            {country}
        </span>
    ) : null;
}
