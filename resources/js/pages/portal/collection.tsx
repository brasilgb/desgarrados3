import { Link } from '@inertiajs/react';
import AdminPagination from '@/components/admin-pagination';
import PortalBreadcrumb from '@/components/portal-breadcrumb';
import PortalPageHero from '@/components/portal-page-hero';
import PortalSeo from '@/components/portal-seo';

type Item = { id: number; title: string; slug: string; excerpt: string | null; author: { name: string }; category: { name: string } | null };
type Props = { collection: { name: string; description: string | null }; publications: { data: Item[]; links: { url: string | null; label: string; active: boolean }[] } };

export default function CollectionPage({ collection, publications }: Props) {
    return <><PortalSeo title={collection.name} description={collection.description} noindex={publications.data.length === 0} /><PortalPageHero image="cultura" eyebrow="Coleção editorial" title={collection.name} intro={collection.description} /><PortalBreadcrumb items={[{ label: 'Portal', href: '/' }, { label: collection.name }]} /><section className="mx-auto max-w-7xl px-5 py-10">
        {publications.data.length ? <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">{publications.data.map((item, i) => <Link key={item.id} href={`/publicacao/${item.slug}`} className={`flex min-h-56 flex-col rounded-2xl p-6 ${i === 0 ? 'bg-[#e8dfcf]' : 'border border-[#ded5c5] bg-[#fbf8f1]'}`}><span className="text-xs font-bold uppercase tracking-widest text-[#bb623e]">{item.category?.name ?? 'Caderno cultural'}</span><h2 className="mt-7 font-serif text-2xl font-bold">{item.title}</h2><p className="mt-3 line-clamp-3 text-sm leading-6 text-stone-600">{item.excerpt}</p><span className="mt-auto pt-5 text-xs text-stone-500">{item.author.name}</span></Link>)}</div> : <div className="rounded-2xl bg-[#e8dfcf] p-8"><h2 className="font-serif text-xl font-bold">Esta coleção está reunindo novas histórias.</h2><Link href="/cadernos/historias" className="mt-4 inline-block underline">Explorar cadernos</Link></div>}
        <AdminPagination links={publications.links} label={`Paginação de ${collection.name}`} />
    </section></>;
}
