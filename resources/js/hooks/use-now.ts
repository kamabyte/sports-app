import { useEffect, useState } from 'react';

/** Текущее время, обновляется раз в interval мс — для «в эфире» и обратного отсчёта. */
export function useNow(interval = 30_000): number {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const timer = window.setInterval(() => setNow(Date.now()), interval);
        return () => window.clearInterval(timer);
    }, [interval]);

    return now;
}
