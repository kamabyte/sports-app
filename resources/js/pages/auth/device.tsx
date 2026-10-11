import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { show, update } from '@/routes/device';

type Props = {
    code: string;
    device: { name: string; requested_at: string | null } | null;
    invalid: boolean;
    status?: 'approved' | 'denied';
};

/**
 * Подключение ТВ по коду: ввод кода с экрана, затем подтверждение с именем
 * устройства. Токен получает сам телевизор — здесь только решение.
 */
export default function Device({ code, device, invalid, status }: Props) {
    if (status) {
        return (
            <>
                <Head title="Подключение ТВ" />
                <p className="text-center text-sm">
                    {status === 'approved'
                        ? 'Телевизор подключён — через несколько секунд он войдёт сам.'
                        : 'Вход отклонён. Телевизор не получит доступ.'}
                </p>
                <Button variant="outline" className="w-full" asChild>
                    <a href={show.url()}>Подключить ещё одно устройство</a>
                </Button>
            </>
        );
    }

    if (device) {
        return (
            <>
                <Head title="Подключение ТВ" />
                <div className="space-y-2 text-center">
                    <p className="text-sm text-muted-foreground">
                        Подключить устройство к вашему аккаунту?
                    </p>
                    <p className="text-lg font-semibold">{device.name}</p>
                    <p className="font-mono text-2xl tracking-widest">{code}</p>
                    <p className="text-xs text-muted-foreground">
                        Код должен совпадать с кодом на экране телевизора. Не
                        подтверждайте код, который вам прислали.
                    </p>
                </div>

                <div className="grid grid-cols-2 gap-3">
                    <Decision code={code} approve={false} />
                    <Decision code={code} approve />
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Подключение ТВ" />
            <Form {...show.form()}>
                {({ processing }) => (
                    <div className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="code">Код с экрана</Label>
                            <Input
                                id="code"
                                name="code"
                                defaultValue={code}
                                placeholder="BCDF-GHJK"
                                autoComplete="off"
                                autoCapitalize="characters"
                                spellCheck={false}
                                autoFocus
                                className="text-center font-mono text-lg tracking-widest uppercase"
                            />
                            <InputError
                                message={
                                    invalid
                                        ? 'Код не найден или истёк. Запросите новый на телевизоре.'
                                        : undefined
                                }
                            />
                        </div>
                        <Button
                            className="w-full"
                            disabled={processing}
                            data-test="device-code-button"
                        >
                            {processing && <Spinner />}
                            Продолжить
                        </Button>
                    </div>
                )}
            </Form>
        </>
    );
}

function Decision({ code, approve }: { code: string; approve: boolean }) {
    return (
        <Form {...update.form()} className="grid gap-2">
            {({ processing, errors }) => (
                <>
                    <input type="hidden" name="user_code" value={code} />
                    <input
                        type="hidden"
                        name="approve"
                        value={approve ? '1' : '0'}
                    />
                    <Button
                        variant={approve ? 'default' : 'outline'}
                        disabled={processing}
                        data-test={
                            approve
                                ? 'approve-device-button'
                                : 'deny-device-button'
                        }
                    >
                        {processing && <Spinner />}
                        {approve ? 'Подключить' : 'Отклонить'}
                    </Button>
                    {approve && <InputError message={errors.user_code} />}
                </>
            )}
        </Form>
    );
}

Device.layout = {
    title: 'Подключение телевизора',
    description: 'Введите код, который показывает приложение на телевизоре',
};
