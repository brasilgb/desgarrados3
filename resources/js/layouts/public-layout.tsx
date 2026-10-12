import { Head, Link, usePage } from '@inertiajs/react';
import { ChevronDown, Fence, Menu, X } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { useEffect, useRef, useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import PortalSearch from '@/components/portal-search';
import { Toaster } from '@/components/ui/sonner';
import { cultureLinks, landLinks } from '@/lib/public-navigation';

type Props = { portalSettings?: Record<string, string | null> };

export default function PublicLayout({ children }: PropsWithChildren) {
    const { props, url } = usePage<Props>();
    const settings = props.portalSettings ?? {};
    const currentUrl = url.split('?')[0];
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [openDesktopMenu, setOpenDesktopMenu] = useState<'culture' | 'land' | null>(null);
    const [openMobileGroup, setOpenMobileGroup] = useState<'culture' | 'land' | null>(null);
    const headerRef = useRef<HTMLElement>(null);
    const mobileMenuButtonRef = useRef<HTMLButtonElement>(null);
    const cultureMenuButtonRef = useRef<HTMLButtonElement>(null);
    const landMenuButtonRef = useRef<HTMLButtonElement>(null);
    const cultureIsActive = currentUrl.startsWith('/cadernos/') || currentUrl === '/ditados';
    const landIsActive = currentUrl.startsWith('/minha-regiao') || currentUrl.startsWith('/nossa-terra') || currentUrl.startsWith('/calendario-rural') || currentUrl.startsWith('/agenda-regional');

    useEffect(() => {
        setOpenDesktopMenu(null);
        setOpenMobileGroup(null);
        setMobileMenuOpen(false);
    }, [currentUrl]);

    useEffect(() => {
        function closeMenusFromOutside(event: PointerEvent) {
            if (event.target instanceof Node && !headerRef.current?.contains(event.target)) {
                setOpenDesktopMenu(null);
                setOpenMobileGroup(null);
                setMobileMenuOpen(false);
            }
        }

        document.addEventListener('pointerdown', closeMenusFromOutside);

        return () => document.removeEventListener('pointerdown', closeMenusFromOutside);
    }, []);

    function closeMenusFromKeyboard(event: React.KeyboardEvent<HTMLElement>) {
        if (event.key !== 'Escape') return;

        if (mobileMenuOpen) {
            setMobileMenuOpen(false);
            setOpenMobileGroup(null);
            mobileMenuButtonRef.current?.focus();
            return;
        }

        if (openDesktopMenu) {
            setOpenDesktopMenu(null);
            (openDesktopMenu === 'culture' ? cultureMenuButtonRef : landMenuButtonRef).current?.focus();
        }
    }

    return <div className="min-h-screen bg-[#f5f0e6] text-[#28271f]">
        <Toaster />
        <Head>
            <meta name="theme-color" content="#31453b" />
            <meta head-key="description" name="description" content={settings.portal_description ?? 'Memórias, causos e cultura de quem leva sua terra natal consigo.'} />
        </Head>
        <header ref={headerRef} onKeyDown={closeMenusFromKeyboard} className="sticky top-0 z-30 border-b border-[#d9d0bf] bg-[#f5f0e6]/95 backdrop-blur">
            <div className="mx-auto flex max-w-screen-2xl flex-wrap items-center gap-3 px-4 py-3 sm:px-5">
                <Link href="/" aria-label={`${settings.portal_name ?? 'Desgarrados'}, página inicial`} className="mr-auto flex shrink-0 items-center gap-2 font-serif text-xl font-black tracking-tight text-[#31453b] sm:text-2xl">
                    {settings.logo_path ? <><img src={`/storage/${settings.logo_path}`} alt={settings.portal_name ?? 'Desgarrados'} onError={event => { event.currentTarget.onerror = null; event.currentTarget.src = '/images/brand/logo-lockup.png'; }} className="h-9 max-w-32 object-contain" />{settings.portal_name ?? 'Desgarrados'}</> : settings.portal_name && settings.portal_name !== 'Desgarrados' ? <><span className="flex h-9 w-9 items-center justify-center rounded-full border border-[#bb623e]"><AppLogoIcon className="size-7 object-contain" /></span>{settings.portal_name}<span className="-ml-2 text-[#bb623e]">.</span></> : <img src="/images/brand/logo-lockup-180.webp" width={180} height={141} alt="Desgarrados" className="h-12 w-[3.85rem] object-contain sm:h-14 sm:w-[4.4rem]" />}
                </Link>
                <nav id="desktop-navigation" aria-label="Navegação principal" className="hidden items-center gap-1 text-sm font-semibold 2xl:flex">
                    <Link href="/" aria-current={currentUrl === '/' ? 'page' : undefined} className={`rounded-full px-3 py-2 hover:bg-[#e8dfcf] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#bb623e] ${currentUrl === '/' ? 'bg-[#e8dfcf] text-[#7e402b]' : ''}`}>Início</Link>
                    <div className="relative">
                        <button ref={cultureMenuButtonRef} type="button" aria-expanded={openDesktopMenu === 'culture'} aria-controls="desktop-culture-menu" onClick={() => setOpenDesktopMenu(openDesktopMenu === 'culture' ? null : 'culture')} className={`inline-flex items-center gap-1 rounded-full px-3 py-2 hover:bg-[#e8dfcf] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#bb623e] ${cultureIsActive ? 'bg-[#e8dfcf] text-[#7e402b]' : ''}`}>
                            Cultura Gaúcha <ChevronDown aria-hidden="true" className={`h-4 w-4 transition-transform ${openDesktopMenu === 'culture' ? 'rotate-180' : ''}`} />
                        </button>
                        <div id="desktop-culture-menu" hidden={openDesktopMenu !== 'culture'} className="absolute left-0 top-full z-40 mt-2 grid w-[32rem] grid-cols-2 gap-1 rounded-2xl border border-[#d9d0bf] bg-[#fbf8f1] p-3 shadow-xl">
                            {cultureLinks.map(item => <Link href={item.href} key={item.href} aria-current={currentUrl === item.href ? 'page' : undefined} className="flex min-w-0 items-center gap-3 rounded-xl px-4 py-3 text-[#31453b] transition-colors duration-200 hover:bg-[#e8dfcf] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#bb623e]"><item.icon aria-hidden="true" className="size-4 shrink-0 text-[#bb623e]"/><span className="min-w-0">{item.label}</span></Link>)}
                        </div>
                    </div>
                    <div className="relative">
                        <button ref={landMenuButtonRef} type="button" aria-expanded={openDesktopMenu === 'land'} aria-controls="desktop-land-menu" onClick={() => setOpenDesktopMenu(openDesktopMenu === 'land' ? null : 'land')} className={`inline-flex items-center gap-1 rounded-full px-3 py-2 hover:bg-[#e8dfcf] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#bb623e] ${landIsActive ? 'bg-[#e8dfcf] text-[#7e402b]' : ''}`}>
                            Nossa Terra <ChevronDown aria-hidden="true" className={`h-4 w-4 transition-transform ${openDesktopMenu === 'land' ? 'rotate-180' : ''}`} />
                        </button>
                        <div id="desktop-land-menu" hidden={openDesktopMenu !== 'land'} className="absolute left-0 top-full z-40 mt-2 grid w-[32rem] grid-cols-2 gap-1 rounded-2xl border border-[#d9d0bf] bg-[#fbf8f1] p-3 shadow-xl">
                            {landLinks.map(item => <Link href={item.href} key={item.href} aria-current={currentUrl === item.href ? 'page' : undefined} className="flex min-w-0 items-start gap-3 rounded-xl px-4 py-3 text-[#31453b] transition-colors duration-200 hover:bg-[#e8dfcf] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#bb623e]"><item.icon aria-hidden="true" className="mt-0.5 size-4 shrink-0 text-[#bb623e]"/><span className="min-w-0"><span className="block font-semibold">{item.label}</span>{item.description && <span className="mt-0.5 block text-xs font-normal text-stone-600">{item.description}</span>}</span></Link>)}
                        </div>
                    </div>
                    <Link href="/sobre" aria-current={currentUrl === '/sobre' ? 'page' : undefined} className={`rounded-full px-3 py-2 hover:bg-[#e8dfcf] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#bb623e] ${currentUrl === '/sobre' ? 'bg-[#e8dfcf] text-[#7e402b]' : ''}`}>Sobre o Desgarrados</Link>
                </nav>
                <PortalSearch />
                <Link href="/contribua" className="hidden rounded-full bg-[#bb623e] px-4 py-2 text-sm font-semibold text-white sm:inline-flex">Compartilhe uma história</Link>
                <Link href="/login" aria-label="Acesso da redação" title="Acesso da redação" className="hidden size-10 items-center justify-center rounded-full text-stone-600 transition-colors hover:bg-[#e8dfcf] hover:text-[#31453b] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#bb623e] md:inline-flex"><Fence aria-hidden="true" className="size-5 text-[#bb623e]"/></Link>
                <button ref={mobileMenuButtonRef} type="button" aria-label={mobileMenuOpen ? 'Fechar menu' : 'Abrir menu'} aria-expanded={mobileMenuOpen} aria-controls="mobile-navigation" onClick={() => setMobileMenuOpen(value => !value)} className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-[#cfc4b2] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#bb623e] 2xl:hidden">
                    {mobileMenuOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
                </button>
                <nav id="mobile-navigation" aria-label="Navegação para celular" hidden={!mobileMenuOpen} className="order-4 grid w-full gap-2 border-t border-[#d9d0bf] py-3 2xl:hidden">
                    <Link href="/" onClick={() => setMobileMenuOpen(false)} aria-current={currentUrl === '/' ? 'page' : undefined} className={`rounded-lg px-3 py-3 text-sm font-semibold ${currentUrl === '/' ? 'bg-[#e8dfcf] text-[#7e402b]' : 'hover:bg-[#e8dfcf]'}`}>Início</Link>
                    <section className="rounded-xl border border-[#d9d0bf] bg-[#fbf8f1]">
                        <button type="button" aria-expanded={openMobileGroup === 'culture'} aria-controls="mobile-culture-links" onClick={() => setOpenMobileGroup(openMobileGroup === 'culture' ? null : 'culture')} className={`flex w-full items-center justify-between rounded-xl px-3 py-3 text-left text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#bb623e] ${cultureIsActive ? 'text-[#7e402b]' : ''}`}>Cultura Gaúcha<ChevronDown aria-hidden="true" className={`h-4 w-4 transition-transform ${openMobileGroup === 'culture' ? 'rotate-180' : ''}`} /></button>
                        <div id="mobile-culture-links" hidden={openMobileGroup !== 'culture'} className="grid grid-cols-1 gap-1 border-t border-[#e6ddce] p-2 sm:grid-cols-2">{cultureLinks.map(item => <Link key={item.href} href={item.href} onClick={() => setMobileMenuOpen(false)} aria-current={currentUrl === item.href ? 'page' : undefined} className="flex min-w-0 items-center gap-3 rounded-lg px-3 py-3 text-sm transition-colors duration-200 hover:bg-[#e8dfcf] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#bb623e]"><item.icon aria-hidden="true" className="size-4 shrink-0 text-[#bb623e]"/><span className="min-w-0">{item.label}</span></Link>)}</div>
                    </section>
                    <section className="rounded-xl border border-[#d9d0bf] bg-[#fbf8f1]">
                        <button type="button" aria-expanded={openMobileGroup === 'land'} aria-controls="mobile-land-links" onClick={() => setOpenMobileGroup(openMobileGroup === 'land' ? null : 'land')} className={`flex w-full items-center justify-between rounded-xl px-3 py-3 text-left text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#bb623e] ${landIsActive ? 'text-[#7e402b]' : ''}`}>Nossa Terra<ChevronDown aria-hidden="true" className={`h-4 w-4 transition-transform ${openMobileGroup === 'land' ? 'rotate-180' : ''}`} /></button>
                        <div id="mobile-land-links" hidden={openMobileGroup !== 'land'} className="grid grid-cols-1 gap-1 border-t border-[#e6ddce] p-2">{landLinks.map(item => <Link key={item.href} href={item.href} onClick={() => setMobileMenuOpen(false)} aria-current={currentUrl === item.href ? 'page' : undefined} className="flex min-w-0 items-start gap-3 rounded-lg px-3 py-3 text-sm transition-colors duration-200 hover:bg-[#e8dfcf] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#bb623e]"><item.icon aria-hidden="true" className="mt-0.5 size-4 shrink-0 text-[#bb623e]"/><span className="min-w-0"><span className="block font-semibold">{item.label}</span>{item.description && <span className="mt-0.5 block text-xs text-stone-600">{item.description}</span>}</span></Link>)}</div>
                    </section>
                    <Link href="/sobre" onClick={() => setMobileMenuOpen(false)} aria-current={currentUrl === '/sobre' ? 'page' : undefined} className={`rounded-lg px-3 py-3 text-sm font-semibold ${currentUrl === '/sobre' ? 'bg-[#e8dfcf] text-[#7e402b]' : 'hover:bg-[#e8dfcf]'}`}>Sobre o Desgarrados</Link>
                    <Link href="/contribua" onClick={() => setMobileMenuOpen(false)} className="rounded-lg bg-[#bb623e] px-3 py-3 text-center text-sm font-semibold text-white">Compartilhe uma história</Link>
                    <Link href="/login" onClick={() => setMobileMenuOpen(false)} className="inline-flex items-center gap-2 px-3 py-2 text-sm"><Fence aria-hidden="true" className="size-4 text-[#bb623e]"/>Acesso da redação</Link>
                </nav>
            </div>
        </header>
        <main>{children}</main>
        <footer className="mt-20 bg-[#31453b] px-5 py-10 text-sm text-[#f5f0e6]">
            <div className="mx-auto grid max-w-7xl gap-8 md:grid-cols-[1fr_auto] md:items-end">
                <div><p className="font-serif text-xl font-bold">{settings.portal_name ?? 'Desgarrados'}</p><p className="mt-2 max-w-xl text-[#d2d9cd]">{settings.portal_description ?? 'Para quem partiu, para quem ficou e para quem nunca deixou de pertencer.'}</p><p className="mt-3 max-w-2xl text-xs leading-5 text-[#b8c3b6]">{settings.institutional_intro ?? 'Um espaço de encontro para quem carrega suas raízes por onde vai.'}</p></div>
                <nav aria-label="Links institucionais" className="flex flex-wrap gap-x-5 gap-y-3 text-sm text-[#e6d4b5]">
                    <Link href="/sobre">Sobre</Link><Link href="/proposta-cultural">Proposta cultural</Link><Link href="/contato">Contato</Link><Link href="/termos-de-uso">Termos de Uso</Link><Link href="/politica-de-privacidade">Privacidade</Link><Link href="/politica-de-privacidade#cookies">Cookies e preferências</Link><Link href="/termos-de-contribuicao">Termos de envio</Link><Link href="/ditados">Ditados</Link>
                    {settings.instagram_url && <a href={settings.instagram_url} rel="noreferrer" target="_blank">Instagram ↗</a>}{settings.facebook_url && <a href={settings.facebook_url} rel="noreferrer" target="_blank">Facebook ↗</a>}{settings.youtube_url && <a href={settings.youtube_url} rel="noreferrer" target="_blank">YouTube ↗</a>}
                </nav>
            </div>
            <div className="mx-auto mt-7 flex max-w-7xl flex-wrap items-center justify-between gap-3 border-t border-white/15 pt-4 text-xs text-[#b8c3b6]">
                <span>© {new Date().getFullYear()} {settings.portal_name ?? 'Desgarrados'} · Cultura e memória em movimento.</span>
                <a href="https://abrasilsistemas.com.br" target="_blank" rel="noopener noreferrer" className="underline decoration-white/30 underline-offset-4 hover:text-white">Desenvolvido por ABrasil Sistemas ↗</a>
            </div>
        </footer>
    </div>;
}
