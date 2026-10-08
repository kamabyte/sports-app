import type { Auth } from '@/types/auth';
import type { CatalogShared } from '@/types/catalog';
import type { Sport } from '@/types/sports';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            /** Часовой пояс расписания (sports.timezone). */
            timezone: string;
            sports: Sport[];
            /** Спортивный каталог: меню видов спорта, демо, свежесть. */
            catalog: CatalogShared | null;
            [key: string]: unknown;
        };
    }
}
