import { Head, Link, router } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useMemo, useState } from 'react';
import AdminPagination from '@/components/admin-pagination';

type Item = { id: number; title: string; status: string; type: string; published_at: string | null; updated_at: string; origin_scope: string; origin_state: string | null; municipality: { name: string } | null; category: { name: string } | null; author: { name: string } };
type PaginationLink = { url: string | null; label: string; active: boolean };
type FilterValues = { q?: string; status?: string; category?: string; author?: string; from?: string; to?: string; type?: string; place?: string; origin_scope?: string; origin_state?: string };
type Props = {
    publications: { data: Item[]; links: PaginationLink[] };
    categories: { id: number; name: string }[];
    authors: { id: number; name: string }[];
    states: { code: string; name: string }[];
    filters: FilterValues;
};
const statuses: Record<string, string> = { draft: 'Rascunho', published: 'Publicado', scheduled: 'Agendado', archived: 'Arquivado' };
const notebooks = [['historia', 'Histórias'], ['causo', 'Causos'], ['anedota', 'Anedotas'], ['poema', 'Poesia'], ['literatura', 'Literatura'], ['musica', 'Música'], ['tradição', 'Tradições']];

export default function Index({ publications, categories, authors, states, filters }: Props) {
    const [selected, setSelected] = useState<number[]>([]);
    const [action, setAction] = useState('archive');
    const [notebook, setNotebook] = useState('historia');
    const query = useMemo(() => {
        const params = new URLSearchParams();
        Object.entries(filters).forEach(([key, value]) => { if (value) params.set(key, value); });
        return params.toString();
    }, [filters]);
    const editHref = (id: number) => `/admin/publications/${id}/edit${query ? `?${query}` : ''}`;

    function filter(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const data = new FormData(event.currentTarget);
        router.get('/admin/publications', Object.fromEntries(data.entries()), { preserveState: true, preserveScroll: true });
    }

    function applyBulk(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!selected.length) return;
        if (action === 'archive' && !confirm(`Arquivar ${selected.length} publicação(ões)?`)) return;
        router.post(`/admin/publications/bulk${query ? `?${query}` : ''}`, { ids: selected, action, type: notebook }, { preserveScroll: true, onSuccess: () => setSelected([]) });
    }

    const allSelected = publications.data.length > 0 && publications.data.every(item => selected.includes(item.id));
    const toggleAll = () => setSelected(allSelected ? selected.filter(id => !publications.data.some(item => item.id === id)) : [...new Set([...selected, ...publications.data.map(item => item.id)])]);

    return <>
        <Head title="Publicações" />
        <div className="space-y-6">
            <div className="flex flex-wrap items-end justify-between gap-4"><div><p className="text-sm uppercase tracking-widest text-muted-foreground">Redação</p><h1 className="font-serif text-3xl font-bold">Publicações</h1><p className="mt-1 text-sm text-muted-foreground">Rascunhos, agenda e conteúdo no portal.</p></div><Link href="/admin/publications/create" className="admin-button">Nova publicação +</Link></div>
            <div className="flex flex-wrap gap-2">{[['Assuntos', '/admin/categories'], ['Biblioteca de mídia', '/admin/media']].map(([title, url]) => <Link key={url} href={url} className="rounded-full border px-4 py-2 text-sm">{title}</Link>)}<span className="ml-auto self-center text-sm text-muted-foreground">{categories.length} assuntos</span></div>
            <form onSubmit={filter} className="grid gap-3 rounded-xl border bg-card p-4 sm:grid-cols-2 xl:grid-cols-4">
                <label className="text-sm font-semibold">Busca<input name="q" defaultValue={filters.q} placeholder="Título da publicação" className="mt-1 w-full rounded-lg border px-3 py-2 text-sm" /></label>
                <label className="text-sm font-semibold">Estado<select name="status" defaultValue={filters.status ?? ''} className="mt-1 w-full rounded-lg border px-3 py-2 text-sm"><option value="">Todos os estados</option>{Object.entries(statuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label>
                <label className="text-sm font-semibold">Caderno<select name="type" defaultValue={filters.type ?? ''} className="mt-1 w-full rounded-lg border px-3 py-2 text-sm"><option value="">Todos os cadernos</option>{notebooks.map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label>
                <label className="text-sm font-semibold">Localidade<input name="place" defaultValue={filters.place ?? ''} placeholder="Município ou nome cadastrado" className="mt-1 w-full rounded-lg border px-3 py-2 text-sm" /></label>
                <label className="text-sm font-semibold">Abrangência<select name="origin_scope" defaultValue={filters.origin_scope ?? ''} className="mt-1 w-full rounded-lg border px-3 py-2 text-sm"><option value="">Todas</option><option value="municipality">Municipal</option><option value="state">Estadual</option><option value="regional">Regional / várias localidades</option></select></label>
                <label className="text-sm font-semibold">UF de origem<select name="origin_state" defaultValue={filters.origin_state ?? ''} className="mt-1 w-full rounded-lg border px-3 py-2 text-sm"><option value="">Todas</option>{states.map(state => <option key={state.code} value={state.code}>{state.code} · {state.name}</option>)}</select></label>
                <label className="text-sm font-semibold">Assunto<select name="category" defaultValue={filters.category ?? ''} className="mt-1 w-full rounded-lg border px-3 py-2 text-sm"><option value="">Todos os assuntos</option>{categories.map(category => <option key={category.id} value={category.id}>{category.name}</option>)}</select></label>
                <label className="text-sm font-semibold">Autor<select name="author" defaultValue={filters.author ?? ''} className="mt-1 w-full rounded-lg border px-3 py-2 text-sm"><option value="">Todos os autores</option>{authors.map(author => <option key={author.id} value={author.id}>{author.name}</option>)}</select></label>
                <label className="text-sm font-semibold">Criadas a partir de<input type="date" name="from" defaultValue={filters.from} className="mt-1 w-full rounded-lg border px-3 py-2 text-sm" /></label>
                <label className="text-sm font-semibold">Criadas até<input type="date" name="to" defaultValue={filters.to} className="mt-1 w-full rounded-lg border px-3 py-2 text-sm" /></label>
                <div className="flex items-end gap-2"><button className="admin-button">Filtrar</button><Link href="/admin/publications" className="admin-button-secondary">Limpar</Link></div>
            </form>
            <form onSubmit={applyBulk} className="flex flex-wrap items-center gap-3 rounded-xl border border-primary/30 bg-primary/5 p-3">
                <span className="text-sm font-semibold text-primary">{selected.length} selecionada(s)</span>
                <label className="sr-only" htmlFor="bulk-action">Operação em lote</label><select id="bulk-action" value={action} onChange={event => setAction(event.target.value)} className="rounded-lg border px-3 py-2 text-sm"><option value="archive">Arquivar</option><option value="unfeature">Retirar de destaque</option><option value="set_notebook">Alterar caderno</option></select>
                {action === 'set_notebook' && <label className="sr-only" htmlFor="bulk-notebook">Novo caderno</label>}{action === 'set_notebook' && <select id="bulk-notebook" value={notebook} onChange={event => setNotebook(event.target.value)} className="rounded-lg border px-3 py-2 text-sm">{notebooks.map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select>}
                <button disabled={!selected.length} className="admin-button">Aplicar à seleção</button>
            </form>
            <div className="overflow-x-auto rounded-xl border bg-card">
                <table className="w-full min-w-[880px] text-left text-sm">
                    <thead className="border-b bg-muted text-muted-foreground"><tr><th className="w-12 px-4 py-3"><input aria-label="Selecionar todas as publicações desta página" type="checkbox" checked={allSelected} onChange={toggleAll} /></th><th className="px-4 py-3">Título</th><th className="px-4 py-3">Autor</th><th className="px-4 py-3">Estado</th><th className="px-4 py-3">Atualizada</th><th className="px-4 py-3"><span className="sr-only">Ações</span></th></tr></thead>
                    <tbody>{publications.data.map(item => <tr key={item.id} className="border-b last:border-0 hover:bg-muted"><td className="px-4 py-3"><input aria-label={`Selecionar ${item.title}`} type="checkbox" checked={selected.includes(item.id)} onChange={event => setSelected(current => event.target.checked ? [...current, item.id] : current.filter(id => id !== item.id))} /></td><td className="px-4 py-3"><Link href={editHref(item.id)} className="font-semibold text-primary hover:underline">{item.title}</Link><p className="mt-1 text-xs text-muted-foreground">{item.category?.name ?? 'Sem assunto'} · {notebooks.find(([value]) => value === item.type)?.[1] ?? item.type} · {item.origin_scope === 'municipality' ? `Municipal · ${item.municipality?.name ?? 'localidade'}` : item.origin_scope === 'state' ? `Estadual · ${item.origin_state ?? ''}` : 'Regional / várias localidades'}{item.status === 'scheduled' && item.published_at ? ` · Agendada para ${new Date(item.published_at).toLocaleString('pt-BR')}` : ''}</p></td><td className="px-4 py-3">{item.author.name}</td><td className="px-4 py-3"><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${item.status === 'published' ? 'bg-primary/10 text-primary' : item.status === 'scheduled' ? 'bg-primary/10 text-primary' : item.status === 'archived' ? 'bg-muted text-muted-foreground' : 'bg-secondary text-secondary-foreground'}`}>{statuses[item.status] ?? item.status}</span></td><td className="px-4 py-3 text-xs text-muted-foreground">{new Date(item.updated_at).toLocaleDateString('pt-BR')}</td><td className="px-4 py-3 text-right"><div className="flex justify-end gap-3"><Link href={editHref(item.id)} className="underline">Editar</Link><Link href={`/admin/publications/${item.id}/revisions${query ? `?${query}` : ''}`} className="underline">Revisões</Link><button type="button" onClick={() => router.post(`/admin/publications/${item.id}/duplicate${query ? `?${query}` : ''}`)} className="text-primary underline">Duplicar</button><button type="button" onClick={() => { if (confirm(`Excluir “${item.title}”? Esta ação não pode ser desfeita.`)) router.delete(`/admin/publications/${item.id}`); }} className="text-destructive underline">Excluir</button></div></td></tr>)}
                    {publications.data.length === 0 && <tr><td colSpan={6} className="p-10 text-center text-muted-foreground">Nenhuma publicação corresponde aos filtros.</td></tr>}</tbody>
                </table>
            </div>
            <AdminPagination links={publications.links} label="Paginação de publicações" />
        </div>
    </>;
}
