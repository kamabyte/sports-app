import { Link } from '@inertiajs/react';
import { Tv } from 'lucide-react';
import { Crest, Flag } from '@/components/catalog/crest';
import { useTimezone } from '@/hooks/use-timezone';
import {
    eventDay,
    eventTime,
    formatDay,
    isLive,
    pluralize,
} from '@/lib/catalog';
import { cn } from '@/lib/utils';
import type { EventItem, Participant, Side } from '@/types/catalog';

/**
 * Время события — или его состояние текстом: «Идёт», «Перенесён», «Отменён»,
 * «Время уточн.» (а не полночь).
 */
export function StatusCell({
    event,
    showDate = false,
}: {
    event: EventItem;
    showDate?: boolean;
}) {
    const timezone = useTimezone();
    const time = eventTime(event, timezone);
    const day = eventDay(event, timezone);
    const live = isLive(event);

    let label: string | null = null;
    let tone = '';
    if (live) {
        label = 'Идёт';
        tone = 'text-live font-semibold';
    } else if (event.status === 'postponed') {
        label = 'Перенесён';
        tone = 'text-amber-600 dark:text-amber-400';
    } else if (event.status === 'cancelled') {
        label = 'Отменён';
        tone = 'text-destructive';
    }

    return (
        <div className="flex w-[4.5rem] shrink-0 flex-col items-start justify-center gap-0.5 text-[13px] leading-tight sm:w-20">
            {showDate && day && (
                <span className="text-[11px] text-muted-foreground first-letter:uppercase">
                    {formatDay(day, timezone)}
                </span>
            )}
            <span
                className={cn(
                    time
                        ? 'font-semibold tabular-nums'
                        : 'text-[11px] text-muted-foreground',
                    (event.status === 'postponed' ||
                        event.status === 'cancelled') &&
                        'line-through decoration-1 opacity-70',
                )}
            >
                {time ?? 'Время уточн.'}
            </span>
            {label && (
                <span
                    className={cn(
                        'inline-flex items-center gap-1 text-[11px]',
                        tone,
                    )}
                >
                    {live && (
                        <span
                            className="size-1.5 animate-live-pulse rounded-full bg-live"
                            aria-hidden
                        />
                    )}
                    {label}
                </span>
            )}
        </div>
    );
}

/** Имя участника: команда с эмблемой, спортсмен с флагом, пара — оба с флагами. */
export function ParticipantName({
    participant,
    className,
    stacked = false,
}: {
    participant: Participant;
    className?: string;
    /** Пара — по строке на спортсмена (узкие места). */
    stacked?: boolean;
}) {
    if (
        participant.kind === 'team' ||
        (participant.kind === 'relay' && participant.id)
    ) {
        return (
            <span className={cn('flex min-w-0 items-center gap-2', className)}>
                <Crest
                    name={participant.name}
                    logo={participant.logo}
                    national={participant.national}
                    code={participant.code}
                    className="size-5"
                />
                <span className="truncate">{participant.name}</span>
            </span>
        );
    }

    return (
        <span
            className={cn(
                'flex min-w-0 gap-1.5',
                stacked && participant.members.length > 1
                    ? 'flex-col'
                    : 'items-center',
                className,
            )}
        >
            {participant.members.map((member, index) => (
                <span
                    key={member.id}
                    className="flex min-w-0 items-center gap-1.5"
                >
                    {index > 0 && !stacked && (
                        <span className="text-muted-foreground">/</span>
                    )}
                    <Flag src={member.flag} />
                    <span className="truncate">{member.name}</span>
                </span>
            ))}
        </span>
    );
}

function SideLine({ side }: { side: Side }) {
    return (
        <div className="flex min-h-6 min-w-0 items-center text-[14px]">
            {side.participant ? (
                <ParticipantName participant={side.participant} />
            ) : side.provisional ? (
                <span
                    className="truncate"
                    title="Имя из дневного списка: профиль участника появится после загрузки турнира"
                >
                    {side.label}
                </span>
            ) : (
                <span className="text-muted-foreground italic">
                    {side.label ?? 'Уточняется'}
                </span>
            )}
        </div>
    );
}

/**
 * Строка расписания: время слева, участники — каждый на своей строке,
 * справа — отметка «по ТВ». Гонка или турнир без сторон — название и число
 * участников.
 */
export function EventRow({
    event,
    showDate = false,
    showCompetition = false,
}: {
    event: EventItem;
    showDate?: boolean;
    showCompetition?: boolean;
}) {
    const context = [
        showCompetition ? event.competition.name : null,
        event.round,
        event.parent && showCompetition ? event.parent.name : null,
    ].filter(Boolean);

    return (
        <Link
            href={event.url}
            prefetch="hover"
            className={cn(
                'group flex items-stretch gap-2 border-b border-border/60 px-3 py-2 transition-colors last:border-b-0 hover:bg-accent/50 focus-visible:bg-accent/60 focus-visible:outline-none sm:gap-3 sm:px-4',
                event.status === 'cancelled' && 'opacity-60',
            )}
        >
            <StatusCell event={event} showDate={showDate} />
            <div className="min-w-0 flex-1">
                {context.length > 0 && (
                    <div className="truncate text-[11px] text-muted-foreground">
                        {context.join(' · ')}
                    </div>
                )}
                {event.sides ? (
                    <div className="flex flex-col">
                        <SideLine side={event.sides[0]} />
                        <SideLine side={event.sides[1]} />
                    </div>
                ) : (
                    <div className="flex min-h-12 flex-col justify-center">
                        <div className="truncate text-[14px] font-semibold">
                            {event.name ?? event.competition.name}
                        </div>
                        <div className="truncate text-[12px] text-muted-foreground">
                            {event.entrants
                                ? pluralize(
                                      event.entrants,
                                      'участник',
                                      'участника',
                                      'участников',
                                  )
                                : 'Участники пока не известны'}
                        </div>
                    </div>
                )}
            </div>
            {event.on_tv && <TvMark />}
        </Link>
    );
}

/** «По ТВ»: у события есть трансляция (прямой эфир или повтор впереди). */
export function TvMark({ className }: { className?: string }) {
    return (
        <span
            className={cn(
                'flex shrink-0 items-center self-center text-brand',
                className,
            )}
            title="Есть трансляция"
        >
            <Tv className="size-4" aria-hidden />
            <span className="sr-only">Есть трансляция</span>
        </span>
    );
}
