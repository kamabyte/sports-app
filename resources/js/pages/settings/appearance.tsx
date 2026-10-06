import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';

export default function Appearance() {
    return (
        <>
            <Head title="Настройки оформления" />

            <h1 className="sr-only">Настройки оформления</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Настройки оформления"
                    description="Настройте внешний вид интерфейса"
                />
                <AppearanceTabs />
            </div>
        </>
    );
}
