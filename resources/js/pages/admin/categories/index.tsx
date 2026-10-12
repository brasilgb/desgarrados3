import { Head, Link, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AdminPagination from '@/components/admin-pagination';

type Category = { id: number; name: string; slug: string; publications_count: number };
type Props = { categories: { data: Category[]; links: { url: string | null; label: string; active: boolean }[] }; filters: { q?: string } };

export default function Categories({ categories, filters }: Props) {
    const form = useForm({ name: '', description: '' });
    function submit(event: FormEvent) { event.preventDefault(); form.post('/admin/categories', { onSuccess: () => form.reset() }); }
    function search(event: FormEvent<HTMLFormElement>) { event.preventDefault(); const query = new FormData(event.currentTarget).get('q'); router.get('/admin/categories', { q: query || undefined }, { preserveState: true }); }

    return <>
        <Head title="Assuntos" />
        <div className="w-full min-w-0 space-y-6"><div><p className="text-sm font-medium uppercase tracking-wider text-muted-foreground">Conteúdo editorial</p><h1 className="mt-1 font-serif text-3xl font-bold">Assuntos</h1><p className="mt-2 text-muted-foreground">Organize as publicações por tema.</p></div>
            <form onSubmit={submit} className="grid gap-3 rounded-xl border bg-card p-5 md:grid-cols-[1fr_1.5fr_auto]"><label className="text-sm font-semibold">Nome<input className="mt-1 w-full rounded-lg border-input" value={form.data.name} onChange={event => form.setData('name', event.target.value)} required maxLength={100} />{form.errors.name && <span className="text-destructive">{form.errors.name}</span>}</label><label className="text-sm font-semibold">Descrição<input className="mt-1 w-full rounded-lg border-input" value={form.data.description} onChange={event => form.setData('description', event.target.value)} maxLength={250} />{form.errors.description && <span className="text-destructive">{form.errors.description}</span>}</label><button disabled={form.processing} className="admin-button self-end">{form.processing ? 'Salvando…' : 'Adicionar assunto'}</button></form>
            <form onSubmit={search} className="flex flex-wrap items-end gap-3 rounded-xl border border-border bg-card p-4"><label htmlFor="category-search" className="sr-only">Buscar assuntos</label><input id="category-search" name="q" defaultValue={filters.q} placeholder="Buscar assunto" className="min-w-0 flex-1 rounded-lg border px-3 py-2"/><button className="admin-button">Buscar</button><Link href="/admin/categories" className="admin-button-secondary">Limpar</Link></form>
            <div className="max-w-full overflow-x-auto rounded-xl border border-border bg-card"><table className="w-full min-w-[36rem] text-left text-sm"><thead className="bg-muted text-muted-foreground"><tr><th className="px-4 py-3">Assunto</th><th className="px-4 py-3">Publicações</th><th className="px-4 py-3"><span className="sr-only">Ações</span></th></tr></thead><tbody>{categories.data.map(category => <tr key={category.id} className="border-t border-border"><td className="px-4 py-3 font-semibold">{category.name}<span className="mt-1 block text-xs font-normal text-muted-foreground">/{category.slug}</span></td><td className="px-4 py-3">{category.publications_count}</td><td className="px-4 py-3 text-right"><button type="button" className="text-destructive underline" onClick={() => { if (confirm(`Remover o assunto “${category.name}”? As publicações associadas ficarão sem assunto.`)) router.delete(`/admin/categories/${category.id}`); }}>Excluir</button></td></tr>)}{categories.data.length === 0 && <tr><td colSpan={3} className="p-8 text-center text-muted-foreground">Nenhum assunto encontrado.</td></tr>}</tbody></table></div>
            <AdminPagination links={categories.links} label="Paginação de assuntos" />
        </div>
    </>;
}
