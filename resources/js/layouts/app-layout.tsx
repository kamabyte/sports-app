import { Link, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    House,
    type LucideIcon,
    Search,
    Tv,
    UserRound,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { ChannelMark } from '@/components/channel-mark';
import { LiveDot } from '@/components/live-badge';
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
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';

interface NavItem {
    href: string;
    label: string;
    icon: LucideIcon;
    /** Какие адреса подсвечивают пункт, кроме самого href. */
    match?: RegExp;
}

const NAV: NavItem[] = [
    { href: '/', label: 'Главная', icon: House, match: /^\/(\?.*)?$/ },
    {
        href: '/schedule',
        label: 'Расписание',
        icon: CalendarDays,
        match: /^\/schedule\b/,
    },
];

const TABS: NavItem[] = [
    ...NAV,
    { href: '/channels', label: 'Каналы', icon: Tv, match: /^\/channels\b/ },
    { href: '/search', label: 'Поиск', icon: Search },
    {
        href: '/settings/profile',
        label: 'Профиль',
        icon: UserRound,
        match: /^\/settings\b/,
    },
];

function isActive(item: NavItem, url: string) {
    return item.match ? item.match.test(url) : url.startsWith(item.href);
}

function Sidebar() {
    const { url, props } = usePage();

    return (
        <aside className="fixed inset-y-0 left-0 z-30 hidden w-[76px] flex-col border-r border-border/60 bg-background md:flex lg:w-64">
            <div className="flex h-16 shrink-0 items-center justify-center px-5 lg:justify-start">
                <Link
                    href="/"
                    className="rounded-lg focus-visible:ring-2 focus-visible:ring-ring"
                    aria-label="Sports — главная"
                >
                    <LogoMark className="lg:hidden" />
                    <Logo className="hidden lg:inline-flex" />
                </Link>
            </div>

            <nav className="flex flex-col gap-0.5 px-3" aria-label="Разделы">
                {NAV.map((item) => {
                    const active = isActive(item, url);
                    const Icon = item.icon;

                    return (
                        <Tooltip key={item.href}>
                            <TooltipTrigger asChild>
                                <Link
                                    href={item.href}
                                    prefetch
                                    aria-current={active ? 'page' : undefined}
                                    className={cn(
                                        'flex h-11 items-center justify-center gap-3.5 rounded-xl px-3 text-[15px] font-medium text-muted-foreground transition-colors hover:bg-accent/70 hover:text-foreground lg:justify-start',
                                        active && 'bg-accent text-foreground',
                                    )}
                                >
                                    <Icon
                                        className={cn(
                                            'size-5 shrink-0',
                                            active && 'text-brand',
                                        )}
                                        strokeWidth={active ? 2.25 : 1.9}
                                    />
                                    <span className="hidden lg:inline">
                                        {item.label}
                                    </span>
                                </Link>
                            </TooltipTrigger>
                            <TooltipContent side="right" className="lg:hidden">
                                {item.label}
                            </TooltipContent>
                        </Tooltip>
                    );
                })}
            </nav>

            {/* Виды спорта и каналы прокручиваются вместе, навигация сверху — на месте. */}
            <div className="mt-6 scrollbar-none flex min-h-0 flex-1 flex-col overflow-y-auto pb-6">
                <SidebarHeading>Виды спорта</SidebarHeading>
                <div className="flex flex-col gap-0.5 px-3">
                    {props.sports.map((sport) => {
                        const href = `/sports/${sport.key}`;
                        const active = url.split('?')[0] === href;

                        return (
                            <Tooltip key={sport.key}>
                                <TooltipTrigger asChild>
                                    <Link
                                        href={href}
                                        prefetch
                                        className={cn(
                                            'flex h-10 items-center justify-center gap-3 rounded-xl px-3 text-sm text-muted-foreground transition-colors hover:bg-accent/70 hover:text-foreground lg:justify-start',
                                            active &&
                                                'bg-accent text-foreground',
                                        )}
                                    >
                                        <span
                                            className="flex size-7 shrink-0 items-center justify-center rounded-lg text-base"
                                            style={{
                                                backgroundColor: `${sport.color}26`,
                                            }}
                                            aria-hidden
                                        >
                                            {sport.emoji}
                                        </span>
                                        <span className="hidden truncate lg:inline">
                                            {sport.name}
                                        </span>
                                    </Link>
                                </TooltipTrigger>
                                <TooltipContent
                                    side="right"
                                    className="lg:hidden"
                                >
                                    {sport.name}
                                </TooltipContent>
                            </Tooltip>
                        );
                    })}
                </div>

                {props.sidebarChannels.length > 0 && (
                    <>
                        <SidebarHeading href="/channels" className="mt-6">
                            Каналы
                        </SidebarHeading>
                        <div className="flex flex-col gap-0.5 px-3">
                            {props.sidebarChannels.map((channel) => {
                                const href = `/channels/${channel.id}`;
                                const active = url.split('?')[0] === href;

                                return (
                                    <Tooltip key={channel.id}>
                                        <TooltipTrigger asChild>
                                            <Link
                                                href={href}
                                                className={cn(
                                                    'flex h-10 items-center justify-center gap-3 rounded-xl px-3 text-sm text-muted-foreground transition-colors hover:bg-accent/70 hover:text-foreground lg:justify-start',
                                                    active &&
                                                        'bg-accent text-foreground',
                                                )}
                                            >
                                                <span className="relative">
                                                    <ChannelMark
                                                        channel={channel}
                                                    />
                                                    {channel.on_air && (
                                                        <span
                                                            className="absolute -top-0.5 -right-0.5 size-2.5 rounded-full border-2 border-background bg-live lg:hidden"
                                                            aria-hidden
                                                        />
                                                    )}
                                                </span>
                                                <span className="hidden min-w-0 flex-1 truncate lg:inline">
                                                    {channel.name}
                                                </span>
                                                {channel.on_air && (
                                                    <LiveDot className="hidden text-live lg:block" />
                                                )}
                                            </Link>
                                        </TooltipTrigger>
                                        <TooltipContent
                                            side="right"
                                            className="lg:hidden"
                                        >
                                            {channel.name}
                                        </TooltipContent>
                                    </Tooltip>
                                );
                            })}
                            <Link
                                href="/channels"
                                className="mt-1 hidden h-9 items-center rounded-xl px-3 text-sm text-muted-foreground transition-colors hover:bg-accent/70 hover:text-foreground lg:flex"
                            >
                                Все каналы →
                            </Link>
                        </div>
                    </>
                )}
            </div>
        </aside>
    );
}

function SidebarHeading({
    children,
    href,
    className,
}: {
    children: ReactNode;
    href?: string;
    className?: string;
}) {
    const classes = cn(
        'hidden px-6 pb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase lg:block',
        className,
    );

    return href ? (
        <Link href={href} className={cn(classes, 'hover:text-foreground')}>
            {children}
        </Link>
    ) : (
        <div className={classes}>{children}</div>
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

function Header() {
    return (
        <header className="sticky top-0 z-20 border-b border-border/60 bg-chrome backdrop-blur-xl backdrop-saturate-150">
            <div className="flex h-14 items-center gap-3 px-4 md:h-16 md:px-6 lg:px-8">
                <Link
                    href="/"
                    className="md:hidden"
                    aria-label="Sports — главная"
                >
                    <Logo />
                </Link>
                <SearchBox className="hidden max-w-xl md:block" />
                <div className="ml-auto flex items-center gap-1">
                    <UserMenu />
                </div>
            </div>
        </header>
    );
}

/** Нижняя панель вкладок на телефоне — как в iOS. */
function TabBar() {
    const { url } = usePage();

    return (
        <nav
            className="fixed inset-x-0 bottom-0 z-30 border-t border-border/60 bg-chrome pb-safe backdrop-blur-xl backdrop-saturate-150 md:hidden"
            aria-label="Разделы"
        >
            <div className="grid h-14 grid-cols-5">
                {TABS.map((item) => {
                    const active = isActive(item, url);
                    const Icon = item.icon;

                    return (
                        <Link
                            key={item.href}
                            href={item.href}
                            aria-current={active ? 'page' : undefined}
                            className={cn(
                                'flex flex-col items-center justify-center gap-0.5 text-[10.5px] font-medium text-muted-foreground',
                                active && 'text-brand',
                            )}
                        >
                            <Icon
                                className="size-[22px]"
                                strokeWidth={active ? 2.2 : 1.8}
                            />
                            {item.label}
                        </Link>
                    );
                })}
            </div>
        </nav>
    );
}

export default function AppLayout({ children }: { children: ReactNode }) {
    return (
        <div className="min-h-dvh">
            <Sidebar />
            <div className="md:pl-[76px] lg:pl-64">
                <Header />
                <main className="px-4 pt-4 pb-28 md:px-6 md:pt-6 md:pb-12 lg:px-8">
                    {children}
                </main>
            </div>
            <TabBar />
        </div>
    );
}
