import { Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({ children, title, description }: AuthLayoutProps) {
    return (
        <main className="grid min-h-svh bg-[#f4f0e7] text-[#28271f] lg:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)]">
            <aside className="relative hidden overflow-hidden bg-[#31453b] px-12 py-10 text-white lg:flex lg:flex-col lg:justify-between xl:px-20">
                <div aria-hidden="true" className="absolute -right-32 -top-28 size-[30rem] rounded-full border border-white/10" />
                <div aria-hidden="true" className="absolute -bottom-44 -left-40 size-[34rem] rounded-full border border-[#d4a476]/20" />
                <Link href={home()} className="relative z-10 inline-flex w-fit items-center gap-3 rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#e6b887]">
                    <span className="flex size-12 items-center justify-center rounded-xl bg-[#e6b887] text-[#31453b]"><AppLogoIcon className="size-8 fill-current" /></span>
                    <span><strong className="block font-serif text-xl">Desgarrados</strong><span className="text-sm text-[#e5d4bd]">Nossa terra, nossas histórias</span></span>
                </Link>
                <div className="relative z-10 max-w-xl pb-10">
                    <p className="text-xs font-bold uppercase tracking-[.22em] text-[#e6b887]">Redação</p>
                    <p className="mt-4 font-serif text-4xl font-semibold leading-tight xl:text-5xl">Histórias da nossa gente, cuidadas por quem conhece o lugar.</p>
                    <p className="mt-5 max-w-lg text-base leading-7 text-[#e7e9e2]">Acesse as ferramentas editoriais para organizar relatos, publicações e memória regional.</p>
                </div>
                <p className="relative z-10 text-xs text-[#dbe0d8]">Acesso reservado à equipe autorizada.</p>
            </aside>

            <section className="flex min-h-svh items-center justify-center px-5 py-10 sm:px-8 lg:px-12">
                <div className="w-full max-w-md rounded-2xl border border-[#ded5c5] bg-white p-6 shadow-[0_18px_60px_-35px_rgba(49,69,59,0.45)] sm:p-9">
                    {title && title !== 'Acesso administrativo' && <header className="mb-6 space-y-2 text-center"><h1 className="font-serif text-2xl font-bold text-[#31453b]">{title}</h1>{description && <p className="text-sm leading-6 text-stone-600">{description}</p>}</header>}
                    {children}
                </div>
            </section>
        </main>
    );
}
