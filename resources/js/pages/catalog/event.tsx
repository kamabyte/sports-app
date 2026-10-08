import { Head, Link } from '@inertiajs/react';
import { CalendarClock, MapPin, Users } from 'lucide-react';
import { Crest, Flag } from '@/components/catalog/crest';
import { Crumbs } from '@/components/catalog/crumbs';
import { EventRow, ParticipantName } from '@/components/catalog/event-row';
import { Badge, Notice } from '@/components/catalog/states';
import { useTimezone } from '@/hooks/use-timezone';
import {
    eventDay,
    eventTime,
    formatLongDate,
    isLive,
    zoneLabel,
} from '@/lib/catalog';
import { cn } from '@/lib/utils';
import type {
    Crumb,
    EventDetail,
    EventItem,
    Participant,
    Side,
} from '@/types/catalog';

interface Props {
    event: EventDetail;
    crumbs: Crumb[];
    related: EventItem[];
    related_title: string;
}

/** Участник крупно: команда — эмблема и название; спортсмены — каждый своей ссылкой с флагом. */
function BigParticipant({
    side,
    align,
}: {
    side: Side;
    align: 'left' | 'right';
}) {
    const right = align === 'right';
    const box = cn(
        'flex min-w-0 flex-1 flex-col items-center gap-2 text-center sm:items-start sm:text-left',
        right && 'sm:items-end sm:text-right',
    );
    const participant = side.participant;

    if (!participant) {
        return (
            <div className={box}>
                {side.provisional ? (
                    <Crest
                        name={side.label ?? '?'}
                        logo={null}
                        className="size-14"
                    />
                ) : (
                    <span className="flex size-14 items-center justify-center rounded-full border border-dashed border-border text-muted-foreground">
                        ?
                    </span>
                )}
                <span
                    className={
                        side.provisional
                            ? 'text-[17px] font-bold'
                            : 'text-[15px] text-muted-foreground italic'
                    }
                >
                    {side.label ?? 'Уточняется'}
                </span>
            </div>
        );
    }

    if (participant.kind === 'team') {
        return (
            <div className={box}>
                <Crest
                    name={participant.name}
                    logo={participant.logo}
                    national={participant.national}
                    code={participant.code}
                    className="size-14"
                />
                {participant.url ? (
                    <Link
                        href={participant.url}
                        className="text-[17px] font-bold hover:underline"
                    >
                        {participant.name}
                    </Link>
                ) : (
                    <span className="text-[17px] font-bold">
                        {participant.name}
                    </span>
                )}
            </div>
        );
    }

    return (
        <div className={box}>
            <div className={cn('flex flex-col gap-1', right && 'sm:items-end')}>
                {participant.members.map((member) => (
                    <Link
                        key={member.id}
                        href={member.url}
                        className="inline-flex items-center gap-2 text-[17px] font-bold hover:underline"
                    >
                        <Flag src={member.flag} className="h-3.5 w-5" />
                        {member.name}
                    </Link>
                ))}
                {participant.kind === 'pair' && (
                    <span className="text-[12px] text-muted-foreground">
                        Пара
                    </span>
                )}
            </div>
        </div>
    );
}

function EntrantName({ participant }: { participant: Participant }) {
    if (participant.kind === 'relay' || participant.kind === 'team') {
        return participant.url ? (
            <Link
                href={participant.url}
                className="truncate font-medium hover:underline"
            >
                {participant.name}
            </Link>
        ) : (
            <ParticipantName participant={participant} />
        );
    }
    const member = participant.members[0];

    return member ? (
        <Link
            href={member.url}
            className="inline-flex max-w-full items-center gap-1.5 hover:underline"
        >
            <Flag src={member.flag} />
            <span className="truncate">{member.name}</span>
        </Link>
    ) : (
        <span>{participant.name}</span>
    );
}

/**
 * Событие: турнир, время с часовым поясом, участники, место. Для гонок и
 * турниров — список участников и сессии этапа. Здесь же позже появятся
 * трансляции.
 */
export default function EventPage({
    event,
    crumbs,
    related,
    related_title,
}: Props) {
    const timezone = useTimezone();
    const day = eventDay(event, timezone);
    const time = eventTime(event, timezone);
    const live = isLive(event);
    const title = event.sides
        ? event.sides
              .map(
                  (side) =>
                      side.participant?.name ?? side.label ?? 'Уточняется',
              )
              .join(' — ')
        : (event.name ?? event.competition.name);
    const notice = {
        postponed: 'Событие перенесено — новое время пока не объявлено.',
        cancelled: 'Событие отменено.',
    }[event.status as string];

    return (
        <>
            <Head title={`${title} · ${event.competition.name}`} />
            <div className="mx-auto max-w-4xl">
                <Crumbs items={crumbs} />

                <header className="mb-4 rounded-xl border border-border/70 bg-card p-4 sm:p-6">
                    <div className="mb-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-[13px] text-muted-foreground">
                        <Link
                            href={event.competition.url}
                            className="inline-flex items-center gap-1.5 font-medium text-foreground hover:underline"
                        >
                            {event.competition.category.flag && (
                                <Flag src={event.competition.category.flag} />
                            )}
                            {event.competition.category.name}:{' '}
                            {event.competition.name}
                        </Link>
                        {event.round && <span>· {event.round}</span>}
                        {event.competition.badges.map((badge) => (
                            <Badge key={badge}>{badge}</Badge>
                        ))}
                    </div>

                    {event.sides ? (
                        <div className="flex flex-col items-stretch gap-4 sm:flex-row sm:items-center">
                            <BigParticipant
                                side={event.sides[0]}
                                align="left"
                            />
                            <div className="flex shrink-0 flex-col items-center gap-1 px-2">
                                <div className="font-display text-3xl font-bold tabular-nums">
                                    {time ?? '—'}
                                </div>
                                {live && (
                                    <span className="rounded-full bg-live/15 px-2.5 py-0.5 text-[12px] font-semibold text-live">
                                        Идёт
                                    </span>
                                )}
                            </div>
                            <BigParticipant
                                side={event.sides[1]}
                                align="right"
                            />
                        </div>
                    ) : (
                        <div className="flex flex-col gap-2">
                            <h1 className="font-display text-2xl font-bold">
                                {event.name ?? event.competition.name}
                            </h1>
                            {live && (
                                <span className="w-fit rounded-full bg-live/15 px-2.5 py-0.5 text-[12px] font-semibold text-live">
                                    Идёт
                                </span>
                            )}
                        </div>
                    )}

                    <dl className="mt-5 grid gap-x-6 gap-y-1 border-t border-border/60 pt-3 text-[13px] sm:grid-cols-2">
                        <div className="flex gap-2">
                            <dt className="inline-flex items-center gap-1 text-muted-foreground">
                                <CalendarClock className="size-3.5" />
                                Начало:
                            </dt>
                            <dd>
                                {day ? (
                                    <span className="first-letter:uppercase">
                                        {formatLongDate(day)}
                                    </span>
                                ) : (
                                    'Дата уточняется'
                                )}
                                {', '}
                                {time
                                    ? `${time} (${zoneLabel(timezone)})`
                                    : 'время уточняется'}
                            </dd>
                        </div>
                        {event.venue && (
                            <div className="flex gap-2">
                                <dt className="sr-only">Место</dt>
                                <dd className="inline-flex items-center gap-1 text-muted-foreground">
                                    <MapPin className="size-3.5" />
                                    {event.venue}
                                </dd>
                            </div>
                        )}
                    </dl>
                    {notice && (
                        <Notice tone="warning" className="mt-3">
                            {notice}
                        </Notice>
                    )}
                </header>

                <div className="flex flex-col gap-4">
                    {event.siblings.length > 1 && (
                        <nav
                            className="scrollbar-none flex gap-1.5 overflow-x-auto"
                            aria-label="Сессии этапа"
                        >
                            {event.siblings.map((sibling) => (
                                <Link
                                    key={sibling.id}
                                    href={sibling.url}
                                    aria-current={
                                        sibling.current ? 'page' : undefined
                                    }
                                    className={cn(
                                        'shrink-0 rounded-full border px-3 py-1 text-[13px]',
                                        sibling.current
                                            ? 'border-foreground bg-foreground text-background'
                                            : 'border-border bg-card hover:bg-accent',
                                    )}
                                >
                                    {sibling.name}
                                </Link>
                            ))}
                        </nav>
                    )}

                    {event.start_list.length > 0 && (
                        <section className="overflow-hidden rounded-xl border border-border/70 bg-card">
                            <h2 className="flex items-center gap-2 border-b border-border/60 px-4 py-2 text-[13px] font-semibold">
                                <Users className="size-3.5" />
                                Участники · {event.start_list.length}
                            </h2>
                            {event.start_list.map((row, index) => (
                                <div
                                    key={index}
                                    className="flex items-center justify-between gap-3 border-b border-border/60 px-4 py-2 text-[14px] last:border-b-0"
                                >
                                    <EntrantName
                                        participant={row.participant}
                                    />
                                    {row.affiliation && (
                                        <span className="text-[13px] text-muted-foreground">
                                            {row.affiliation}
                                        </span>
                                    )}
                                </div>
                            ))}
                        </section>
                    )}

                    {event.children.length > 0 && (
                        <section className="overflow-hidden rounded-xl border border-border/70 bg-card">
                            <h2 className="border-b border-border/60 bg-muted/60 px-4 py-2 text-[13px] font-semibold">
                                {event.family === 'bout'
                                    ? 'Бои'
                                    : event.competition.sport.key === 'cycling'
                                      ? 'Этапы'
                                      : 'Сессии'}
                            </h2>
                            {event.children.map((child) => (
                                <EventRow
                                    key={child.id}
                                    event={child}
                                    showDate
                                />
                            ))}
                        </section>
                    )}

                    {related.length > 0 && event.children.length === 0 && (
                        <section className="overflow-hidden rounded-xl border border-border/70 bg-card">
                            <h2 className="border-b border-border/60 bg-muted/60 px-4 py-2 text-[13px] font-semibold">
                                {related_title}
                            </h2>
                            {related.map((item) => (
                                <EventRow key={item.id} event={item} showDate />
                            ))}
                        </section>
                    )}
                </div>
            </div>
        </>
    );
}
