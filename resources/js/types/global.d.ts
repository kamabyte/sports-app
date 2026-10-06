import type { Auth } from '@/types/auth';
import type { ChannelSummary, Sport } from '@/types/sports';

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
            /** Каналы с матчами — в боковой панели. */
            sidebarChannels: ChannelSummary[];
            [key: string]: unknown;
        };
    }
}
