import { Link } from '@inertiajs/react';
import { ArrowLeft, ChevronRight } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { Crumb } from '@/types/catalog';

/**
 * Хлебные крошки — канонический путь по каталогу; «Назад» возвращает туда,
 * откуда пришли (с фильтрами и прокруткой), а если пришли по ссылке извне — к
 * родительскому разделу.
 */
export function Crumbs({ items }: { items: Crumb[] }) {
    const [canGoBack, setCanGoBack] = useState(false);
    useEffect(() => {
        const sameOrigin = document.referrer.startsWith(window.location.origin);
        setCanGoBack(sameOrigin && window.history.length > 1);
    }, []);
    const parent = [...items].reverse().find((item) => item.url);

    return (
        <nav
            aria-label="Навигация по разделам"
            className="mb-4 flex min-w-0 items-center gap-2 text-[13px]"
        >
            {canGoBack ? (
                <button
                    type="button"
                    onClick={() => window.history.back()}
                    className="inline-flex shrink-0 items-center gap-1 rounded-md px-1.5 py-1 text-muted-foreground hover:bg-accent hover:text-foreground"
                >
                    <ArrowLeft className="size-3.5" />
                    Назад
                </button>
            ) : (
                parent?.url && (
                    <Link
                        href={parent.url}
                        className="inline-flex shrink-0 items-center gap-1 rounded-md px-1.5 py-1 text-muted-foreground hover:bg-accent hover:text-foreground"
                    >
                        <ArrowLeft className="size-3.5" />
                        <span className="sr-only">К разделу</span>
                    </Link>
                )
            )}
            <ol className="scrollbar-none flex min-w-0 items-center gap-1 overflow-x-auto whitespace-nowrap text-muted-foreground">
                {items.map((item, index) => (
                    <li
                        key={`${item.label}-${index}`}
                        className="flex items-center gap-1"
                    >
                        {index > 0 && (
                            <ChevronRight
                                className="size-3.5 shrink-0 opacity-60"
                                aria-hidden
                            />
                        )}
                        {item.url && index < items.length - 1 ? (
                            <Link
                                href={item.url}
                                className="rounded px-0.5 hover:text-foreground hover:underline"
                            >
                                {item.label}
                            </Link>
                        ) : (
                            <span
                                className="font-medium text-foreground"
                                aria-current={
                                    index === items.length - 1
                                        ? 'page'
                                        : undefined
                                }
                            >
                                {item.label}
                            </span>
                        )}
                    </li>
                ))}
            </ol>
        </nav>
    );
}
