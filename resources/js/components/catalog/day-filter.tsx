import { router } from '@inertiajs/react';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    RotateCcw,
    Tv,
    X,
} from 'lucide-react';
import { useEffect, useRef } from 'react';
import { useTimezone } from '@/hooks/use-timezone';
import { dayChip, shiftDate, todayKey, withQuery } from '@/lib/catalog';
import { cn } from '@/lib/utils';

export interface FilterChip {
    label: string;
    remove: Record<string, null>;
}

/** Снимаемые фильтры раздела и турнира. */
export function filterChips(
    category: { name: string } | null,
    competition: { name: string } | null,
): FilterChip[] {
    const chips: FilterChip[] = [];
    if (category)
        chips.push({ label: category.name, remove: { category: null } });
    if (competition)
        chips.push({ label: competition.name, remove: { competition: null } });

    return chips;
}

/** Перейти с новыми фильтрами, не теряя прокрутку и состояние панели. */
export function visit(
    path: string,
    params: Record<string, string | number | null | undefined>,
) {
    router.get(
        withQuery(path, params),
        {},
        { preserveScroll: true, preserveState: true },
    );
}

/** Сколько дней показывает полоса: сегодня и неделя вперёд. */
const DAYS = 8;

/**
 * Выбор дня расписания: полоса «сегодня + неделя», переход дальше и
 * календарь. Прошедших дней нет — каталог показывает расписание. Фильтры
 * раздела/турнира снимаются крестиком, «С трансляцией» — переключатель;
 * всё хранится в адресе.
 */
export function DayFilter({
    path,
    date,
    extra = {},
    chips = [],
    tv,
}: {
    path: string;
    date: string;
    /** Прочие параметры адреса (раздел, турнир, вид спорта) — сохраняются при смене дня. */
    extra?: Record<string, string | number | null | undefined>;
    chips?: FilterChip[];
    /** Переключатель «С трансляцией» (параметр tv); не задан — переключателя нет. */
    tv?: boolean;
}) {
    const timezone = useTimezone();
    const today = todayKey(timezone);
    const params = (
        patch: Record<string, string | number | null | undefined>,
    ) => {
        const next: Record<string, string | number | null | undefined> = {
            date,
            ...extra,
            ...patch,
        };
        if (next.date === today) next.date = null;

        return next;
    };
    // Полоса начинается с сегодня; если выбран далёкий день — с него минус три.
    const first =
        date > shiftDate(today, DAYS - 1) ? shiftDate(date, -3) : today;
    const days = Array.from({ length: DAYS }, (_, index) =>
        shiftDate(first, index),
    );
    const strip = useRef<HTMLDivElement>(null);
    useEffect(() => {
        const container = strip.current;
        const active = container?.querySelector<HTMLElement>(
            '[aria-selected="true"]',
        );
        if (container && active) {
            container.scrollLeft =
                active.offsetLeft -
                container.offsetLeft -
                (container.clientWidth - active.clientWidth) / 2;
        }
    }, [date]);
    const button =
        'flex size-9 shrink-0 items-center justify-center rounded-lg border border-border bg-card hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-40 disabled:hover:bg-card';

    return (
        <div className="flex flex-col gap-2">
            <div className="flex items-center gap-1">
                <button
                    type="button"
                    disabled={date <= today}
                    onClick={() =>
                        visit(path, params({ date: shiftDate(date, -1) }))
                    }
                    className={button}
                    aria-label="Предыдущий день"
                >
                    <ChevronLeft className="size-4" />
                </button>
                <div
                    ref={strip}
                    className="relative scrollbar-none flex min-w-0 flex-1 gap-1 overflow-x-auto"
                    role="tablist"
                    aria-label="День"
                >
                    {days.map((day) => {
                        const chip = dayChip(day, timezone);
                        const active = day === date;

                        return (
                            <button
                                key={day}
                                type="button"
                                role="tab"
                                aria-selected={active}
                                onClick={() =>
                                    visit(path, params({ date: day }))
                                }
                                className={cn(
                                    'flex min-w-[4.25rem] shrink-0 flex-col items-center rounded-lg border px-2 py-1 text-center transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                    active
                                        ? 'border-foreground bg-foreground text-background'
                                        : 'border-border bg-card hover:bg-accent',
                                )}
                            >
                                <span className="text-[12px] font-semibold first-letter:uppercase">
                                    {chip.top}
                                </span>
                                <span
                                    className={cn(
                                        'text-[11px]',
                                        active
                                            ? 'text-background/75'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    {chip.bottom}
                                </span>
                            </button>
                        );
                    })}
                </div>
                <button
                    type="button"
                    onClick={() =>
                        visit(path, params({ date: shiftDate(date, 1) }))
                    }
                    className={button}
                    aria-label="Следующий день"
                >
                    <ChevronRight className="size-4" />
                </button>
                <label className="relative flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-lg border border-border bg-card focus-within:ring-2 focus-within:ring-ring hover:bg-accent">
                    <CalendarDays className="size-4" aria-hidden />
                    <span className="sr-only">Выбрать дату</span>
                    <input
                        type="date"
                        min={today}
                        value={date}
                        onChange={(event) =>
                            event.target.value &&
                            visit(path, params({ date: event.target.value }))
                        }
                        className="absolute inset-0 cursor-pointer opacity-0"
                    />
                </label>
            </div>

            {(chips.length > 0 || date !== today || tv !== undefined) && (
                <div className="flex flex-wrap items-center gap-2">
                    {tv !== undefined && (
                        <button
                            type="button"
                            aria-pressed={tv}
                            onClick={() =>
                                visit(path, params({ tv: tv ? null : 1 }))
                            }
                            className={cn(
                                'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-[13px] focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                tv
                                    ? 'border-foreground bg-foreground text-background'
                                    : 'border-border bg-card hover:bg-accent',
                            )}
                        >
                            <Tv className="size-3.5" aria-hidden />С трансляцией
                        </button>
                    )}
                    {chips.map((chip) => (
                        <button
                            key={chip.label}
                            type="button"
                            onClick={() => visit(path, params(chip.remove))}
                            className="inline-flex items-center gap-1 rounded-full border border-border bg-card py-1 pr-2 pl-3 text-[13px] hover:bg-accent"
                            aria-label={`Убрать фильтр: ${chip.label}`}
                        >
                            {chip.label}
                            <X className="size-3.5 text-muted-foreground" />
                        </button>
                    ))}
                    {(chips.length > 0 || date !== today || tv) && (
                        <button
                            type="button"
                            onClick={() =>
                                visit(
                                    path,
                                    Object.fromEntries(
                                        Object.keys(extra)
                                            .filter((key) => key !== 'sport')
                                            .map((key) => [key, null]),
                                    ),
                                )
                            }
                            className="inline-flex items-center gap-1 rounded-full px-2 py-1 text-[13px] text-muted-foreground hover:text-foreground"
                        >
                            <RotateCcw className="size-3.5" />
                            Сегодня, без фильтров
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}
