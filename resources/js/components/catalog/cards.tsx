import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { Crest } from '@/components/catalog/crest';
import { Badge } from '@/components/catalog/states';
import type { CompetitionItem, TeamItem } from '@/types/catalog';

/** Турнир в списках раздела и каталога: эмблема, название, признаки. */
export function CompetitionCard({
    competition,
    context,
}: {
    competition: CompetitionItem;
    context?: string;
}) {
    return (
        <Link
            href={competition.url}
            className="group flex items-center gap-3 border-b border-border/60 px-3 py-2.5 last:border-b-0 hover:bg-accent/50 focus-visible:bg-accent/60 focus-visible:outline-none sm:px-4"
        >
            <Crest
                name={competition.name}
                logo={competition.logo}
                className="size-7"
            />
            <div className="min-w-0 flex-1">
                <div className="truncate text-[14px] font-semibold">
                    {competition.name}
                </div>
                {(context ||
                    competition.original_name ||
                    competition.badges.length > 0) && (
                    <div className="flex flex-wrap items-center gap-1 text-[12px] text-muted-foreground">
                        {context && <span className="mr-1">{context}</span>}
                        {competition.original_name && (
                            <span className="mr-1">
                                {competition.original_name}
                            </span>
                        )}
                        {competition.badges.map((badge) => (
                            <Badge key={badge}>{badge}</Badge>
                        ))}
                    </div>
                )}
            </div>
            <ChevronRight className="size-4 text-muted-foreground transition group-hover:translate-x-0.5" />
        </Link>
    );
}

/** Команда в справочниках: признаки отличают основную, женскую, молодёжную и олимпийскую сборные. */
export function TeamCard({ team }: { team: TeamItem }) {
    return (
        <Link
            href={team.url}
            className="flex items-center gap-3 rounded-xl border border-border/70 bg-card px-3 py-3 hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            <Crest
                name={team.name}
                logo={team.logo}
                national={team.national}
                code={team.code}
                className="size-9"
            />
            <div className="min-w-0 flex-1">
                <div className="truncate text-[14px] font-semibold">
                    {team.name}
                </div>
                <div className="mt-0.5 flex flex-wrap gap-1">
                    {team.badges.map((badge) => (
                        <Badge key={badge}>{badge}</Badge>
                    ))}
                </div>
            </div>
        </Link>
    );
}
