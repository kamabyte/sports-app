import { Link, router, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    ChevronDown,
    House,
    type LucideIcon,
    Menu,
    Search,
    Trophy,
} from 'lucide-react';
import { type ReactNode, useEffect, useState } from 'react';
import { Notice } from '@/components/catalog/states';
import { Logo, LogoMark } from '@/components/logo';
import { SearchBox } from '@/components/search-box';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { useTimezone } from '@/hooks/use-timezone';
import { formatAgo, SPORT_EMOJI, useCatalog } from '@/lib/catalog';
import { cn } from '@/lib/utils';
import type { CatalogSportNav } from '@/types/catalog';

interface NavItem {
    href: string;
    label: string;
    icon: LucideIcon;
    match: (path: string) => boolean;
}

const NAV: NavItem[] = [
    { href: '/', label: 'Главная', icon: House, match: (path) => path === '/' },
    {
        href: '/schedule',
        label: 'Расписание',
        icon: CalendarDays,
        match: (path) => path.startsWith('/schedule'),
    },
    {
        href: '/search',
        label: 'Поиск',
        icon: Search,
        match: (path) => path.startsWith('/search'),
    },
];

function usePath(): string {
    return usePage().url.split('?')[0];
}

/** Идёт переход между страницами — содержимое приглушается, а не исчезает. */
function useNavigating(): boolean {
    const [navigating, setNavigating] = useState(false);
    useEffect(() => {
        let timer: ReturnType<typeof setTimeout> | undefined;
        const offStart = router.on('start', () => {
            timer = setTimeout(() => setNavigating(true), 200);
        });
        const offFinish = router.on('finish', () => {
            clearTimeout(timer);
            setNavigating(false);
        });

        return () => {
            clearTimeout(timer);
            offStart();
            offFinish();
        };
    }, []);

    return navigating;
}

function SportLink({
    sport,
    compact = false,
    onNavigate,
}: {
    sport: CatalogSportNav;
    compact?: boolean;
    onNavigate?: () => void;
}) {
    const path = usePath();
    const active = path === sport.url || path.startsWith(`${sport.url}/`);

    return (
        <Link
            href={sport.url}
            onClick={onNavigate}
            aria-current={active ? 'page' : undefined}
            title={compact ? sport.name : undefined}
            className={cn(
                'flex h-9 items-center gap-2.5 rounded-lg px-2.5 text-[14px] transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                compact && 'justify-center lg:justify-start',
                active
                    ? 'bg-accent font-semibold text-foreground'
                    : 'text-muted-foreground hover:text-foreground',
                !sport.has_data && 'opacity-70',
            )}
        >
            <span className="w-5 shrink-0 text-center text-base" aria-hidden>
                {SPORT_EMOJI[sport.key] ?? '🏅'}
            </span>
            <span
                className={cn(
                    'min-w-0 flex-1 truncate',
                    compact && 'hidden lg:inline',
                )}
            >
                {sport.name}
            </span>
            {!sport.has_data && (
                <span
                    className={cn(
                        'text-[10px] text-muted-foreground',
                        compact && 'hidden lg:inline',
                    )}
                >
                    нет данных
                </span>
            )}
        </Link>
    );
}

/** Виды спорта: с данными — сверху, без данных — свёрнутой группой с пометкой. */
function SportList({
    compact = false,
    onNavigate,
}: {
    compact?: boolean;
    onNavigate?: () => void;
}) {
    const { sports } = useCatalog();
    const path = usePath();
    const withData = sports.filter((sport) => sport.has_data);
    const withoutData = sports.filter((sport) => !sport.has_data);
    const [open, setOpen] = useState(() =>
        withoutData.some((sport) => path.startsWith(sport.url)),
    );

    return (
        <div className="flex flex-col gap-0.5">
            <div
                className={cn(
                    'px-2.5 pb-1 text-[11px] font-semibold tracking-wide text-muted-foreground uppercase',
                    compact && 'hidden lg:block',
                )}
            >
                Виды спорта
            </div>
            {withData.map((sport) => (
                <SportLink
                    key={sport.key}
                    sport={sport}
                    compact={compact}
                    onNavigate={onNavigate}
                />
            ))}
            {withoutData.length > 0 && (
                <>
                    <button
                        type="button"
                        onClick={() => setOpen(!open)}
                        aria-expanded={open}
                        className={cn(
                            'mt-2 flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-left text-[12px] text-muted-foreground hover:text-foreground',
                            compact && 'hidden lg:flex',
                        )}
                    >
                        <ChevronDown
                            className={cn(
                                'size-3.5 transition-transform',
                                !open && '-rotate-90',
                            )}
                        />
                        Ещё {withoutData.length} — данных пока нет
                    </button>
                    {open &&
                        withoutData.map((sport) => (
                            <SportLink
                                key={sport.key}
                                sport={sport}
                                compact={compact}
                                onNavigate={onNavigate}
                            />
                        ))}
                </>
            )}
        </div>
    );
}

function Sidebar() {
    const path = usePath();

    return (
        <aside className="fixed inset-y-0 left-0 z-30 hidden w-[72px] flex-col border-r border-border/60 bg-background md:flex lg:w-60">
            <div className="flex h-14 shrink-0 items-center justify-center px-4 lg:justify-start">
                <Link
                    href="/"
                    className="rounded-lg focus-visible:ring-2 focus-visible:ring-ring"
                    aria-label="Sports — главная"
                >
                    <LogoMark className="lg:hidden" />
                    <Logo className="hidden lg:inline-flex" />
                </Link>
            </div>
            <nav
                className="flex flex-col gap-0.5 px-2.5"
                aria-label="Разделы приложения"
            >
                {NAV.map((item) => {
                    const active = item.match(path);
                    const Icon = item.icon;

                    return (
                        <Link
                            key={item.href}
                            href={item.href}
                            aria-current={active ? 'page' : undefined}
                            title={item.label}
                            className={cn(
                                'flex h-10 items-center justify-center gap-3 rounded-lg px-2.5 text-[14px] font-medium transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none lg:justify-start',
                                active
                                    ? 'bg-accent text-foreground'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            <Icon
                                className={cn(
                                    'size-[18px] shrink-0',
                                    active && 'text-brand',
                                )}
                            />
                            <span className="hidden lg:inline">
                                {item.label}
                            </span>
                        </Link>
                    );
                })}
            </nav>
            <div className="mt-5 scrollbar-none flex min-h-0 flex-1 flex-col overflow-y-auto px-2.5 pb-6">
                <SportList compact />
            </div>
        </aside>
    );
}

function UserMenu() {
    const { auth } = usePage().props;
    const getInitials = useInitials();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-9 rounded-full"
                    aria-label="Профиль"
                >
                    <Avatar className="size-8">
                        <AvatarFallback className="bg-secondary text-xs font-semibold">
                            {getInitials(auth.user.name)}
                        </AvatarFallback>
                    </Avatar>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-60 rounded-xl">
                <UserMenuContent user={auth.user} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function Freshness() {
    const catalog = useCatalog();
    const timezone = useTimezone();
    if (!catalog.updated_at) return null;

    return (
        <span
            className={cn(
                'hidden text-[12px] lg:inline',
                catalog.stale
                    ? 'text-amber-600 dark:text-amber-400'
                    : 'text-muted-foreground',
            )}
        >
            Обновлено {formatAgo(catalog.updated_at, timezone)}
        </span>
    );
}

function Header({ onMenu }: { onMenu: () => void }) {
    return (
        <header className="sticky top-0 z-20 border-b border-border/60 bg-chrome backdrop-blur-xl backdrop-saturate-150">
            <div className="mx-auto flex h-14 max-w-6xl items-center gap-2 px-3 md:px-6 lg:px-8">
                <button
                    type="button"
                    onClick={onMenu}
                    className="flex size-9 items-center justify-center rounded-lg hover:bg-accent md:hidden"
                    aria-label="Виды спорта"
                >
                    <Menu className="size-5" />
                </button>
                <Link
                    href="/"
                    className="md:hidden"
                    aria-label="Sports — главная"
                >
                    <Logo />
                </Link>
                <SearchBox className="hidden max-w-lg md:block" />
                <div className="ml-auto flex items-center gap-2">
                    <Freshness />
                    <Link
                        href="/search"
                        className="flex size-9 items-center justify-center rounded-lg hover:bg-accent md:hidden"
                        aria-label="Поиск"
                    >
                        <Search className="size-5" />
                    </Link>
                    <UserMenu />
                </div>
            </div>
        </header>
    );
}

function TabBar({ onSports }: { onSports: () => void }) {
    const path = usePath();
    const items: {
        key: string;
        label: string;
        icon: LucideIcon;
        href?: string;
        active: boolean;
    }[] = [
        {
            key: 'home',
            label: 'Главная',
            icon: House,
            href: '/',
            active: path === '/',
        },
        {
            key: 'sports',
            label: 'Спорт',
            icon: Trophy,
            active:
                path.startsWith('/sport') ||
                path.startsWith('/team') ||
                path.startsWith('/player') ||
                path.startsWith('/event'),
        },
        {
            key: 'schedule',
            label: 'Расписание',
            icon: CalendarDays,
            href: '/schedule',
            active: path.startsWith('/schedule'),
        },
        {
            key: 'search',
            label: 'Поиск',
            icon: Search,
            href: '/search',
            active: path.startsWith('/search'),
        },
    ];

    return (
        <nav
            className="fixed inset-x-0 bottom-0 z-30 border-t border-border/60 bg-chrome pb-safe backdrop-blur-xl backdrop-saturate-150 md:hidden"
            aria-label="Разделы приложения"
        >
            <div className="grid h-14 grid-cols-4">
                {items.map((item) => {
                    const Icon = item.icon;
                    const classes = cn(
                        'flex flex-col items-center justify-center gap-0.5 text-[10.5px] font-medium text-muted-foreground',
                        item.active && 'text-brand',
                    );

                    return item.href ? (
                        <Link
                            key={item.key}
                            href={item.href}
                            aria-current={item.active ? 'page' : undefined}
                            className={classes}
                        >
                            <Icon
                                className="size-[21px]"
                                strokeWidth={item.active ? 2.2 : 1.8}
                            />
                            {item.label}
                        </Link>
                    ) : (
                        <button
                            key={item.key}
                            type="button"
                            onClick={onSports}
                            className={classes}
                        >
                            <Icon
                                className="size-[21px]"
                                strokeWidth={item.active ? 2.2 : 1.8}
                            />
                            {item.label}
                        </button>
                    );
                })}
            </div>
        </nav>
    );
}

/** Каркас спортивного каталога: виды спорта слева (или в меню на телефоне), поиск сверху. */
export default function CatalogLayout({ children }: { children: ReactNode }) {
    const catalog = useCatalog();
    const timezone = useTimezone();
    const navigating = useNavigating();
    const [menu, setMenu] = useState(false);

    return (
        <div className="min-h-dvh">
            <Sidebar />
            <div className="md:pl-[72px] lg:pl-60">
                <Header onMenu={() => setMenu(true)} />
                <main
                    aria-busy={navigating}
                    className={cn(
                        'mx-auto max-w-6xl px-3 pt-4 pb-28 transition-opacity md:px-6 md:pb-12 lg:px-8',
                        navigating && 'opacity-60',
                    )}
                >
                    {catalog.stale && catalog.updated_at && (
                        <Notice tone="warning" className="mb-4">
                            Расписание обновлялось{' '}
                            {formatAgo(catalog.updated_at, timezone)} — время и
                            состав матчей могли измениться.
                        </Notice>
                    )}
                    {children}
                </main>
            </div>
            <TabBar onSports={() => setMenu(true)} />
            <Sheet open={menu} onOpenChange={setMenu}>
                <SheetContent
                    side="left"
                    className="w-[82vw] max-w-xs overflow-y-auto p-3"
                >
                    <SheetHeader className="p-1">
                        <SheetTitle>Виды спорта</SheetTitle>
                    </SheetHeader>
                    <SportList onNavigate={() => setMenu(false)} />
                </SheetContent>
            </Sheet>
        </div>
    );
}
