import { Head } from '@inertiajs/react';
import { SearchX } from 'lucide-react';
import { EmptyState, PageTitle } from '@/components/empty-state';
import { EventRow } from '@/components/event-row';
import { SearchBox } from '@/components/search-box';
import type { SportEvent } from '@/types';

interface Props {
    query: string;
    events: SportEvent[];
}

export default function Search({ query, events }: Props) {
    return (
        <>
            <Head title={query ? `Поиск: ${query}` : 'Поиск'} />
            <SearchBox className="mb-6 md:hidden" autoFocus={!query} />
            <PageTitle>{query ? `«${query}»` : 'Поиск'}</PageTitle>

            {events.length > 0 && (
                <div className="flex flex-col rounded-3xl border border-border/60 bg-card/50 p-1.5">
                    {events.map((event) => (
                        <EventRow key={event.id} event={event} showDate />
                    ))}
                </div>
            )}

            {query && events.length === 0 && (
                <EmptyState icon={SearchX} title="Ничего не нашлось">
                    Попробуйте название команды, турнира или вида спорта.
                </EmptyState>
            )}

            {!query && (
                <EmptyState icon={SearchX} title="Что ищем?">
                    Например, «Зенит», «Лига чемпионов» или «теннис».
                </EmptyState>
            )}
        </>
    );
}
