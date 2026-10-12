import { Link, router } from '@inertiajs/react';
import type { FormEvent } from 'react';
import PortalBreadcrumb from '@/components/portal-breadcrumb';
import AdminPagination from '@/components/admin-pagination';
import PortalPageHero from '@/components/portal-page-hero';
import PortalSeo from '@/components/portal-seo';

type Publication = { id: number; title: string; slug: string; excerpt: string | null; published_at: string; author: { name: string }; category: { name: string } | null };
type Props = { notebook: { slug: string; title: string; intro: string; type: string }; publications: { data: Publication[]; links: { url: string | null; label: string; active: boolean }[] }; categories: { name: string; slug: string }[]; filters: { q?: string; assunto?: string } };

export default function NotebookPage({ notebook, publications, categories, filters }: Props) {
    function search(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const data = new FormData(event.currentTarget);
        router.get(`/cadernos/${notebook.slug}`, { q: data.get('q') || undefined, assunto: data.get('assunto') || undefined });
    }

    return <><PortalSeo title={notebook.title} description={notebook.intro} />
        <PortalPageHero image="cultura" eyebrow="Caderno cultural" title={notebook.title} titleClassName="whitespace-nowrap text-[clamp(1.4rem,8vw,3.75rem)] md:text-6xl" intro={notebook.intro} />
        <PortalBreadcrumb items={[{ label: 'Portal', href: '/' }, { label: notebook.title }]} />
        <section className="mx-auto max-w-7xl px-5 py-10"><form onSubmit={search} className="mb-8 grid gap-3 rounded-2xl bg-[#e8dfcf] p-4 sm:grid-cols-[1fr_16rem_auto]"><input name="q" defaultValue={filters.q} placeholder="Buscar neste caderno" aria-label="Buscar neste caderno" className="portal-field"/><select name="assunto" defaultValue={filters.assunto ?? ''} aria-label="Filtrar por assunto" className="portal-field"><option value="">Todos os assuntos</option>{categories.map(category => <option key={category.slug} value={category.slug}>{category.name}</option>)}</select><button className="portal-button">Filtrar</button></form>
            {publications.data.length ? <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">{publications.data.map(publication => <Link key={publication.id} href={`/publicacao/${publication.slug}`} className="portal-card flex min-h-60 flex-col rounded-2xl border border-[#ded5c5] bg-[#fbf8f1] p-6"><span className="text-xs font-semibold uppercase tracking-widest text-[#bb623e]">{publication.category?.name ?? notebook.title}</span><h2 className="mt-5 font-serif text-2xl font-bold">{publication.title}</h2><p className="mt-3 line-clamp-4 text-sm leading-6 text-[#625f55]">{publication.excerpt}</p><span className="mt-auto pt-6 text-xs text-[#777366]">{publication.author.name} · {new Date(publication.published_at).toLocaleDateString('pt-BR')}</span></Link>)}</div> : <div className="rounded-2xl bg-[#e8dfcf] p-8"><h2 className="font-serif text-xl font-bold">Este caderno está esperando novas histórias.</h2><p className="mt-2 text-stone-600">Você pode enviar uma lembrança para a equipe editorial avaliar.</p><Link href="/contribua" className="mt-4 inline-block font-semibold text-emerald-900 underline">Contribua com uma história →</Link></div>}
            <AdminPagination links={publications.links} label={`Paginação de ${notebook.title}`} />
        </section>
    </>;
}
