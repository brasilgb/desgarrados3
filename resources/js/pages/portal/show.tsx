import { Link, usePage } from '@inertiajs/react';
import PortalBreadcrumb from '@/components/portal-breadcrumb';
import PortalSeo from '@/components/portal-seo';
import JsonLd from '@/components/json-ld';
import { absoluteUrl } from '@/lib/seo';

type Item = { id: number; title: string; slug: string; excerpt: string | null; category: { name: string } | null };
type Props = { publication: { title: string; seo_title: string | null; seo_description: string | null; excerpt: string | null; body: string; type: string; credit: string | null; origin_scope: string; origin_city: string | null; origin_state: string | null; municipality_code: number | null; municipality: { ibge_code: number; name: string; state_code: string } | null; source_references: { name: string; url: string; note: string | null }[] | null; cover_path: string | null; cover_media: { alt_text: string | null; credit: string | null; width: number | null; height: number | null } | null; published_at: string; updated_at: string; author: { name: string }; category: { name: string } | null; categories: { id: number; name: string; slug: string }[]; series: { id: number; title: string; slug: string; description: string | null } | null; collections: { id: number; name: string; slug: string }[] }; related: Item[]; chapters: { id: number; title: string; slug: string; series_position: number | null }[] };

export default function Show({ publication: p, related, chapters }: Props) {
    const { props, url } = usePage<{ appUrl?: string }>();
    const appUrl = props.appUrl;
    const currentSlug = url.split('/').pop()?.split('?')[0];
    return <>
        <PortalSeo
            title={p.seo_title || p.title}
            description={p.seo_description || p.excerpt || p.body}
            type="article"
            image={p.cover_path ? `/storage/${p.cover_path}` : null}
            imageAlt={p.cover_path ? p.cover_media?.alt_text || p.title : null}
            article={{ publishedTime: p.published_at, modifiedTime: p.updated_at, section: p.category?.name }}
        />
        <JsonLd id="article" schema={{
            '@type': 'Article',
            headline: p.title,
            description: p.seo_description || p.excerpt,
            datePublished: p.published_at,
            dateModified: p.updated_at,
            author: { '@type': 'Person', name: p.author.name },
            image: p.cover_path ? [absoluteUrl(appUrl, `/storage/${p.cover_path}`)] : null,
            articleSection: p.category?.name,
            mainEntityOfPage: absoluteUrl(appUrl, `/publicacao/${currentSlug}`),
            inLanguage: 'pt-BR',
        }} />
        <article className="mx-auto max-w-3xl px-5 py-14">
            <PortalBreadcrumb items={[{ label: 'Portal', href: '/' }, ...(p.category ? [{ label: p.category.name }] : []), { label: p.title }]} />
            {p.cover_path && <figure className="mt-8"><img src={`/storage/${p.cover_path}`} alt={p.cover_media?.alt_text || p.title} width={p.cover_media?.width ?? undefined} height={p.cover_media?.height ?? undefined} decoding="async" className="max-h-[32rem] w-full rounded-2xl object-cover" />{p.cover_media?.credit && <figcaption className="mt-2 text-right text-xs text-stone-500">Fotografia: {p.cover_media.credit}</figcaption>}</figure>}
            <p className="mt-10 text-xs font-bold uppercase tracking-[.2em] text-[#bb623e]">{p.category?.name ?? p.type} · {new Date(p.published_at).toLocaleDateString('pt-BR')}</p>
            <h1 className="mt-4 font-serif text-4xl font-bold leading-tight md:text-6xl">{p.title}</h1>
            {p.excerpt && <p className="mt-6 text-xl leading-8 text-[#625f55]">{p.excerpt}</p>}
            <div className="mt-8 border-y border-[#d9d0bf] py-4 text-sm text-[#625f55]">Por <strong>{p.author.name}</strong>{p.credit && <> · Crédito: {p.credit}</>}{p.origin_scope === 'municipality' && (p.municipality || p.origin_city) && <> · <Link className="underline" href={`/minha-regiao?state=${p.municipality?.state_code ?? p.origin_state}&municipality_code=${p.municipality?.ibge_code ?? p.municipality_code}`}>{p.municipality?.name ?? p.origin_city}{p.municipality?.state_code ?? p.origin_state ? `, ${p.municipality?.state_code ?? p.origin_state}` : ''}</Link></>}{p.origin_scope === 'state' && p.origin_state && <> · Conteúdo estadual de {p.origin_state}</>}{p.origin_scope === 'regional' && <> · Memória regional / várias localidades</>}</div>
            {p.collections.length > 0 && <div className="mt-5 flex flex-wrap gap-2">{p.collections.map(collection => <Link key={collection.id} href={`/colecoes/${collection.slug}`} className="rounded-full bg-[#e8dfcf] px-3 py-1 text-xs font-semibold text-[#31453b]">{collection.name}</Link>)}</div>}
            {p.categories.length > 0 && <div className="mt-3 flex flex-wrap gap-2" aria-label="Assuntos">{p.categories.map(category => <Link key={category.id} href={`/cadernos/${p.type === 'tradição' ? 'tradicoes' : p.type === 'poema' ? 'poesia' : p.type === 'historia' ? 'historias' : p.type === 'causo' ? 'causos' : p.type === 'anedota' ? 'anedotas' : p.type === 'musica' ? 'musica' : 'literatura'}?assunto=${category.slug}`} className="rounded-full border border-[#d9d0bf] px-3 py-1 text-xs font-semibold text-[#31453b]">{category.name}</Link>)}</div>}
            <div className="editorial-body mt-9" dangerouslySetInnerHTML={{ __html: p.body }} />
            {p.source_references?.length ? <section aria-labelledby="references-title" className="mt-10 rounded-2xl bg-[#e8dfcf] p-5"><h2 id="references-title" className="font-serif text-xl font-bold text-[#31453b]">Fontes e referências</h2><ul className="mt-3 space-y-3">{p.source_references.map((source, index) => <li key={`${source.url}-${index}`}><a href={source.url} target="_blank" rel="noreferrer" className="font-semibold text-[#31453b] underline">{source.name}</a>{source.note && <p className="mt-1 text-sm text-stone-600">{source.note}</p>}</li>)}</ul></section> : null}
            {p.series && chapters.length > 0 && <section className="mt-10 rounded-2xl border border-[#d9d0bf] bg-[#fbf8f1] p-5"><p className="text-xs font-bold uppercase tracking-widest text-[#bb623e]">Série</p><h2 className="mt-2 font-serif text-xl font-bold">{p.series.title}</h2>{p.series.description && <p className="mt-2 text-sm text-stone-600">{p.series.description}</p>}<ol className="mt-4 space-y-2">{chapters.map(chapter => <li key={chapter.id}><Link aria-current={chapter.slug === currentSlug ? 'page' : undefined} href={`/publicacao/${chapter.slug}`} className={`block rounded-lg px-3 py-2 ${chapter.slug === currentSlug ? 'bg-[#31453b] text-white' : 'hover:bg-[#e8dfcf]'}`}>{chapter.series_position}. {chapter.title}</Link></li>)}</ol></section>}
        </article>
        {related.length > 0 && <section className="bg-[#e8dfcf] px-5 py-12"><div className="mx-auto max-w-5xl"><p className="text-xs font-bold uppercase tracking-[.2em] text-[#bb623e]">Continue a viagem</p><h2 className="mt-2 font-serif text-2xl font-bold">Outras histórias que conversam com esta</h2><div className="mt-5 grid gap-4 sm:grid-cols-3">{related.map(item => <Link key={item.id} href={`/publicacao/${item.slug}`} className="rounded-xl bg-[#fbf8f1] p-5"><span className="text-xs text-stone-500">{item.category?.name ?? 'Caderno cultural'}</span><h3 className="mt-2 font-serif text-xl font-bold">{item.title}</h3>{item.excerpt && <p className="mt-2 line-clamp-3 text-sm text-stone-600">{item.excerpt}</p>}</Link>)}</div></div></section>}
    </>;
}
