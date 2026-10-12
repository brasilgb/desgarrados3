import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

type Snapshot = { title: string; excerpt: string | null; body: string; type: string; status: string; category_id: number | null; category_ids?: number[]; credit: string | null; cover_path: string | null; origin_scope?: string; origin_state?: string | null; origin_city?: string | null; municipality_code?: number | null; source_references?: { name: string; url: string; note: string | null }[] | null; published_at: string | null };
type Revision = { id: number; restored_from_revision_id: number | null; created_at: string; editor: { name: string } | null; snapshot: Snapshot };
type Props = { publication: { id: number; title: string; slug: string }; current: Snapshot; revisions: Revision[] };

const statusLabels: Record<string, string> = { draft: 'Rascunho', scheduled: 'Agendada', published: 'Publicada', archived: 'Arquivada' };

export default function RevisionsPage() {
    const { publication, current, revisions } = usePage<Props>().props;
    const [selectedId, setSelectedId] = useState(revisions[0]?.id ?? 0);
    const selected = revisions.find(revision => revision.id === selectedId) ?? null;
    const query = new URLSearchParams(usePage().url.split('?')[1] ?? '').toString();
    const editUrl = `/admin/publications/${publication.id}/edit${query ? `?${query}` : ''}`;

    function restore(revision: Revision) {
        const state = statusLabels[revision.snapshot.status] ?? revision.snapshot.status;
        if (!confirm(`Restaurar esta versão e retornar o estado editorial para “${state}”? A autoria original da publicação será mantida.`)) return;
        router.post(`/admin/publications/${publication.id}/revisions/${revision.id}/restore${query ? `?${query}` : ''}`);
    }

    return <>
        <Head title={`Revisões · ${publication.title}`} />
        <div className="space-y-6">
            <div><Link href={editUrl} className="text-sm font-semibold text-primary underline">← Voltar ao editor</Link><p className="mt-4 text-xs font-bold uppercase tracking-widest text-muted-foreground">Histórico editorial</p><h1 className="mt-1 font-serif text-3xl font-bold">{publication.title}</h1><p className="mt-2 text-sm text-muted-foreground">Versões anteriores e alterações feitas por cada pessoa.</p></div>
            {revisions.length === 0 ? <div className="rounded-xl border bg-card p-8 text-center"><p className="font-semibold">Ainda não há revisões salvas.</p><p className="mt-2 text-sm text-muted-foreground">O histórico começa quando uma publicação é atualizada ou uma operação em lote altera seu estado.</p></div> : <>
                <section className="rounded-xl border bg-card p-4"><label htmlFor="revision-select" className="block text-sm font-semibold">Escolha uma versão para comparar</label><select id="revision-select" value={selectedId} onChange={event => setSelectedId(Number(event.target.value))} className="mt-2 w-full rounded-lg border-input sm:max-w-2xl">{revisions.map(revision => <option key={revision.id} value={revision.id}>{new Date(revision.created_at).toLocaleString('pt-BR')} · {revision.editor?.name ?? 'Conta removida'} · {statusLabels[revision.snapshot.status] ?? revision.snapshot.status}{revision.restored_from_revision_id ? ' · restauração' : ''}</option>)}</select></section>
                {selected && <div className="grid gap-5 xl:grid-cols-2">
                    <article className="min-w-0 rounded-xl border bg-card p-5"><div className="flex flex-wrap items-start justify-between gap-3"><div><p className="text-xs font-bold uppercase tracking-wider text-secondary-foreground">Versão selecionada</p><p className="mt-1 text-sm text-muted-foreground">{selected.editor?.name ?? 'Conta removida'} · {new Date(selected.created_at).toLocaleString('pt-BR')}</p></div><button type="button" onClick={() => restore(selected)} className="admin-button">Restaurar versão</button></div><h2 className="mt-5 font-serif text-2xl font-bold">{selected.snapshot.title}</h2><p className="mt-2 text-sm text-muted-foreground">{statusLabels[selected.snapshot.status] ?? selected.snapshot.status} · {selected.snapshot.type}</p>{selected.snapshot.excerpt && <p className="mt-4 border-l-2 border-secondary pl-3 text-muted-foreground">{selected.snapshot.excerpt}</p>}<div className="editorial-body mt-5 max-h-[65vh] overflow-auto border-t pt-4" dangerouslySetInnerHTML={{ __html: selected.snapshot.body }} /></article>
                    <article className="min-w-0 rounded-xl border border-primary/30 bg-primary/5/50 p-5"><p className="text-xs font-bold uppercase tracking-wider text-primary">Versão atual</p><p className="mt-1 text-sm text-muted-foreground">{statusLabels[current.status] ?? current.status} · {current.type}</p><h2 className="mt-5 font-serif text-2xl font-bold">{current.title}</h2>{current.excerpt && <p className="mt-4 border-l-2 border-primary pl-3 text-muted-foreground">{current.excerpt}</p>}<div className="editorial-body mt-5 max-h-[65vh] overflow-auto border-t pt-4" dangerouslySetInnerHTML={{ __html: current.body }} /></article>
                </div>}
            </>}
        </div>
    </>;
}
