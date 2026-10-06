import { CircleAlert, Copy, Download } from 'lucide-react';
import mpegts from 'mpegts.js';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';

/**
 * Прямой эфир канала Dispatcharr (MPEG-TS) через mpegts.js. Если браузер не
 * справится (кодек, сеть), остаются плейлист для VLC/IINA и ссылка.
 */
export function LivePlayer({
    url,
    playlistUrl,
    onError,
}: {
    url: string;
    /** Плейлист .m3u для внешнего плеера; нет — только ссылка на поток. */
    playlistUrl?: string;
    onError?: () => void;
}) {
    const video = useRef<HTMLVideoElement>(null);
    const [error, setError] = useState<string | null>(null);
    const [, copy] = useClipboard();
    const [copied, setCopied] = useState(false);

    useEffect(() => {
        const element = video.current;
        if (!element) return;

        if (!mpegts.isSupported()) {
            setError('Браузер не умеет играть MPEG-TS.');
            return;
        }

        setError(null);
        // Dispatcharr отдаёт поток не ровно, а пачками: ~6 с видео раз в
        // ~7 с (сегменты HLS у источника). Поэтому без погони за «живым
        // краем» (она перематывала к концу буфера и плеер ждал следующую
        // пачку) — играем на пачку позади, с буфером. Разбор TS — в воркере,
        // чтобы перерисовки страницы не дёргали видео.
        const player = mpegts.createPlayer(
            { type: 'mpegts', isLive: true, url },
            {
                enableWorker: true,
                enableStashBuffer: true,
                stashInitialSize: 1024 * 1024,
                liveBufferLatencyChasing: false,
                liveSync: false,
                lazyLoad: false,
                autoCleanupSourceBuffer: true,
                autoCleanupMaxBackwardDuration: 60,
                autoCleanupMinBackwardDuration: 30,
            },
        );
        player.on(mpegts.Events.ERROR, (type: string, detail: string) => {
            setError(`Поток не воспроизводится (${type}: ${detail}).`);
            onError?.();
        });
        player.attachMediaElement(element);
        player.load();
        void Promise.resolve(player.play()).catch(() => undefined);

        return () => {
            player.destroy();
        };
    }, [url, onError]);

    return (
        <div className="relative size-full bg-black">
            <video
                ref={video}
                className="size-full"
                controls
                autoPlay
                playsInline
            />
            {error && (
                <div className="absolute inset-0 flex flex-col items-center justify-center gap-4 bg-black/80 p-6 text-center text-white">
                    <CircleAlert className="size-8 text-live" />
                    <div>
                        <p className="font-semibold">{error}</p>
                        <p className="mt-1 text-sm text-white/70">
                            Откройте канал во внешнем плеере.
                        </p>
                    </div>
                    <div className="flex flex-wrap justify-center gap-2">
                        {playlistUrl && (
                            <Button
                                asChild
                                variant="secondary"
                                className="rounded-full"
                            >
                                <a href={playlistUrl}>
                                    <Download className="size-4" />
                                    Плейлист .m3u
                                </a>
                            </Button>
                        )}
                        <Button
                            variant="secondary"
                            className="rounded-full"
                            onClick={() =>
                                void copy(url).then((ok) => setCopied(ok))
                            }
                        >
                            <Copy className="size-4" />
                            {copied ? 'Скопировано' : 'Ссылка на поток'}
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}
