import { Link } from '@inertiajs/react';
import PortalSeo from '@/components/portal-seo';

export default function PortalNotFound() {
    return <><PortalSeo title="Página não encontrada" noindex /><section className="mx-auto max-w-4xl px-5 py-20 text-center md:py-28"><p className="text-xs font-bold uppercase tracking-[.24em] text-[#bb623e]">404 · Caminho interrompido</p><h1 className="mt-4 font-serif text-4xl font-bold text-[#31453b] md:text-6xl">Esta lembrança ainda não encontrou morada.</h1><p className="mx-auto mt-5 max-w-xl leading-7 text-stone-600">O endereço pode ter mudado. Volte ao começo ou escolha um caderno para continuar a viagem.</p><div className="mt-8 flex flex-wrap justify-center gap-3"><Link href="/" className="rounded-full bg-[#31453b] px-5 py-3 font-semibold text-white">Ir para o início</Link><Link href="/cadernos/historias" className="rounded-full border border-[#31453b] px-5 py-3 font-semibold text-[#31453b]">Explorar histórias</Link></div></section></>;
}
