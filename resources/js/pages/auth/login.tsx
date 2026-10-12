import { Form, Head } from '@inertiajs/react';
import { ArrowLeft, LoaderCircle } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import { home } from '@/routes';
import AppLogoIcon from '@/components/app-logo-icon';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    return (
        <>
            <Head title="Entrar na redação" />
            <div className="mb-6 flex items-center gap-3 rounded-xl border border-[#ded5c5] bg-white/80 p-4 lg:hidden">
                <span className="flex size-12 shrink-0 items-center justify-center rounded-xl bg-[#31453b] text-[#f4e9d6]"><AppLogoIcon className="size-8 fill-current" /></span>
                <span><strong className="block font-serif text-lg text-[#31453b]">Desgarrados</strong><span className="text-sm text-stone-600">Acesso da redação</span></span>
            </div>

            <div className="space-y-6">
                <div>
                    <p className="text-xs font-bold uppercase tracking-[.18em] text-[#bb623e]">Área administrativa</p>
                    <h1 className="mt-2 font-serif text-3xl font-bold text-[#31453b]">Que bom ter você de volta.</h1>
                    <p className="mt-2 text-sm leading-6 text-stone-600">Entre com sua conta para continuar o trabalho da redação.</p>
                </div>

                {status && <div role="status" aria-live="polite" className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">{status}</div>}

                <Form {...store.form()} resetOnSuccess={['password']} className="flex flex-col gap-5">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="email" className="text-stone-700">E-mail</Label>
                                <Input id="email" type="email" name="email" required autoFocus autoComplete="username" placeholder="voce@exemplo.com" className="min-h-12 border-stone-300 bg-white px-3.5" />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center justify-between gap-3">
                                    <Label htmlFor="password" className="text-stone-700">Senha</Label>
                                    {canResetPassword && <TextLink href={request()} className="text-sm text-[#31453b]">Esqueceu a senha?</TextLink>}
                                </div>
                                <PasswordInput id="password" name="password" required autoComplete="current-password" placeholder="Digite sua senha" className="min-h-12 border-stone-300 bg-white px-3.5" />
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center gap-3">
                                <Checkbox id="remember" name="remember" />
                                <Label htmlFor="remember" className="font-normal text-stone-600">Manter conectado neste dispositivo</Label>
                            </div>

                            <Button type="submit" className="min-h-12 w-full bg-[#31453b] text-base font-semibold text-white hover:bg-[#26382f]" disabled={processing} data-test="login-button">
                                {processing && <LoaderCircle className="size-4 animate-spin" aria-hidden="true" />}
                                {processing ? 'Verificando acesso…' : 'Entrar na redação'}
                            </Button>
                        </>
                    )}
                </Form>

                <TextLink href={home()} className="inline-flex items-center gap-2 text-sm text-stone-600"><ArrowLeft className="size-4" aria-hidden="true" /> Voltar ao portal</TextLink>
            </div>
        </>
    );
}

Login.layout = {
    title: 'Acesso administrativo',
    description: 'Entre com sua conta da redação Desgarrados.',
};
