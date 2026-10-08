import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { SportPanel, SportPanelButton } from '@/components/catalog/sport-panel';
import { Notice } from '@/components/catalog/states';
import { SPORT_EMOJI } from '@/lib/catalog';
import { cn } from '@/lib/utils';
import type { SportPanelData, SportRef } from '@/types/catalog';

/**
 * Разделы вида спорта: расписание, все турниры, сборные (у командных), игроки
 * (у индивидуальных). Видны всегда — даже если сегодня событий нет.
 */
function SportTabs({
    sport,
    panel,
}: {
    sport: SportRef;
    panel: SportPanelData;
}) {
    const { url } = usePage();
    const path = url.split('?')[0];
    const tabs = [
        { href: sport.url, label: 'Расписание', active: path === sport.url },
        {
            href: `${sport.url}/competitions`,
            label: 'Все турниры',
            active: path === `${sport.url}/competitions`,
        },
        panel.has_national_teams && {
            href: `${sport.url}/national-teams`,
            label: 'Сборные',
            active: path === `${sport.url}/national-teams`,
        },
        panel.has_players && {
            href: `${sport.url}/players`,
            label: 'Игроки',
            active: path === `${sport.url}/players`,
        },
    ].filter((tab): tab is { href: string; label: string; active: boolean } =>
        Boolean(tab),
    );

    return (
        <nav
            className="-mx-1 scrollbar-none flex gap-1 overflow-x-auto px-1"
            aria-label={`Разделы: ${sport.name}`}
        >
            {tabs.map((tab) => (
                <Link
                    key={tab.href}
                    href={tab.href}
                    aria-current={tab.active ? 'page' : undefined}
                    className={cn(
                        'shrink-0 rounded-lg px-3 py-1.5 text-[14px] font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                        tab.active
                            ? 'bg-foreground text-background'
                            : 'text-muted-foreground hover:bg-accent hover:text-foreground',
                    )}
                >
                    {tab.label}
                </Link>
            ))}
        </nav>
    );
}

/**
 * Каркас страниц вида спорта: заголовок с видом спорта, вкладки разделов,
 * панель стран и турниров (сбоку или по кнопке) и содержимое.
 */
export function SportShell({
    sport,
    panel,
    title,
    subtitle,
    children,
    showTabs = true,
}: {
    sport: SportRef;
    panel: SportPanelData;
    title?: ReactNode;
    subtitle?: ReactNode;
    children: ReactNode;
    showTabs?: boolean;
}) {
    return (
        <div className="flex gap-6">
            <SportPanel sport={sport} panel={panel} />
            <div className="min-w-0 flex-1">
                <div className="mb-4 flex flex-col gap-3">
                    <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0">
                            <h1 className="flex items-center gap-2 font-display text-2xl font-bold md:text-3xl">
                                <span aria-hidden>
                                    {SPORT_EMOJI[sport.key]}
                                </span>
                                <span className="min-w-0 truncate">
                                    {title ?? sport.name}
                                </span>
                            </h1>
                            {subtitle && (
                                <div className="mt-0.5 text-[14px] text-muted-foreground">
                                    {subtitle}
                                </div>
                            )}
                        </div>
                        <SportPanelButton sport={sport} panel={panel} />
                    </div>
                    {showTabs && <SportTabs sport={sport} panel={panel} />}
                    {sport.has_data === false && (
                        <Notice tone="warning">
                            Для этого вида спорта ещё нет проверенных данных:
                            источник не подключён или его разбор не проверен.
                            Разделы появятся после импорта — выдуманных матчей
                            здесь не будет.
                        </Notice>
                    )}
                </div>
                {children}
            </div>
        </div>
    );
}
