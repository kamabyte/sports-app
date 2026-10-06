import { usePage } from '@inertiajs/react';

export function useTimezone(): string {
    return usePage().props.timezone;
}
