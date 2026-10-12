import { Head } from '@inertiajs/react';

type PreviewPublication = {
    title: string; seo_title?: string | null; seo_description?: string | null;
    excerpt?: string | null; body: string; type: string; credit?: string | null;
    origin_scope?: string; origin_city?: string | null; origin_state?: string | null; source_references?: { name: string; url: string; note?: string | null }[]; cover_path?: string | null;
    cover_media?: { alt_text: string | null; credit: string | null } | null;
    author: { name: string }; category?: { name: string } | null;
    categories?: { id: number; name: string }[];
    collections?: { id: number; name: string }[];
    series?: { title: string; description: string | null } | null;
};

export default function Preview({ publication: p }: { publication: PreviewPublication }) {
    return <>
        <Head title={`Prévia: ${p.seo_title || p.title}`}><meta head-key="description" name="description" content={p.seo_description || p.excerpt || ''} /><meta head-key="robots" name="robots" content="noindex, nofollow" /></Head>
        <div className="border-b border-secondary bg-secondary px-5 py-3 text-center text-sm font-semibold text-secondary-foreground">Pré-visualização privada · esta publicação ainda não está no portal</div>
        <article className="mx-auto max-w-3xl px-5 py-12 md:py-16">
            <button type="button" onClick={() => window.history.back()} className="text-sm font-semibold text-primary">← Voltar ao editor</button>
            {p.cover_path && <figure className="mt-8"><img src={`/storage/${p.cover_path}`} alt={p.cover_media?.alt_text || p.title} className="max-h-[32rem] w-full rounded-2xl object-cover" />{(p.cover_media?.credit || p.credit) && <figcaption className="mt-2 text-xs text-muted-foreground">Crédito da imagem: {p.cover_media?.credit || p.credit}</figcaption>}</figure>}
            <p className="mt-10 text-xs font-bold uppercase tracking-[.2em] text-primary">{p.category?.name ?? p.type}</p>
            <h1 className="mt-4 font-serif text-4xl font-bold leading-tight md:text-6xl">{p.title}</h1>
            {p.excerpt && <p className="mt-6 text-xl leading-8 text-muted-foreground">{p.excerpt}</p>}
            <div className="mt-8 border-y border-border py-4 text-sm text-muted-foreground">Por <strong>{p.author.name}</strong>{p.credit && <> · Crédito: {p.credit}</>}{p.origin_scope === 'municipality' && p.origin_city && <> · Municipal · {p.origin_city}{p.origin_state ? `, ${p.origin_state}` : ''}</>}{p.origin_scope === 'state' && p.origin_state && <> · Estadual · {p.origin_state}</>}{p.origin_scope === 'regional' && <> · Regional / várias localidades</>}</div>
            {p.categories && p.categories.length > 0 && <div className="mt-4 flex flex-wrap gap-2">{p.categories.map(category => <span key={category.id} className="rounded-full bg-secondary px-3 py-1 text-xs font-semibold text-secondary-foreground">{category.name}</span>)}</div>}
            {p.collections && p.collections.length > 0 && <div className="mt-5 flex flex-wrap gap-2">{p.collections.map(collection => <span key={collection.id} className="rounded-full bg-secondary px-3 py-1 text-xs font-semibold text-secondary-foreground">{collection.name}</span>)}</div>}
            <div className="editorial-body mt-9" dangerouslySetInnerHTML={{ __html: p.body }} />
            {p.source_references?.length ? <section className="mt-10 rounded-2xl border border-border bg-secondary p-5"><h2 className="font-serif text-xl font-bold text-secondary-foreground">Fontes e referências</h2><ul className="mt-3 space-y-3">{p.source_references.map((source, index) => <li key={`${source.url}-${index}`}><a href={source.url} target="_blank" rel="noreferrer" className="font-semibold underline">{source.name}</a>{source.note && <p className="mt-1 text-sm text-muted-foreground">{source.note}</p>}</li>)}</ul></section> : null}
            {p.series && <aside className="mt-10 rounded-2xl border border-border bg-card p-5"><p className="text-xs font-bold uppercase tracking-widest text-primary">Série</p><h2 className="mt-2 font-serif text-xl font-bold">{p.series.title}</h2>{p.series.description && <p className="mt-2 text-sm text-muted-foreground">{p.series.description}</p>}</aside>}
        </article>
    </>;
}
