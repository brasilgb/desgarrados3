import { Head, Link, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AdminPagination from '@/components/admin-pagination';

type MediaItem = { id: number; path: string; preview_url: string; original_name: string; alt_text: string | null; credit: string | null; mime_type: string; size: number; width: number | null; height: number | null; used_in_count: number; is_public: boolean; source_submission: { id: number; title: string } | null };
type PaginationLink = { url: string | null; label: string; active: boolean };
type Props = { media: { data: MediaItem[]; links: PaginationLink[]; total: number }; filters: { q?: string; type?: string } };
const sizeLabel = (bytes: number) => bytes < 1024 * 1024 ? `${Math.max(1, Math.round(bytes / 1024))} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`;

export default function MediaPage({ media, filters }: Props) {
    const form = useForm<{ file: File | null; alt_text: string; credit: string }>({ file: null, alt_text: '', credit: '' });
    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/admin/media', { forceFormData: true, onSuccess: () => form.reset() });
    }

    return <>
        <Head title="Biblioteca de mídia" />
        <div className="space-y-6">
            <div><p className="text-sm font-medium uppercase tracking-wider text-muted-foreground">Acervo editorial</p><h1 className="mt-1 font-serif text-3xl font-bold">Imagens e arquivos</h1><p className="mt-2 text-muted-foreground">Envie imagens e PDFs; arquivos usados em publicações ficam protegidos contra exclusão.</p></div>
            <form onSubmit={submit} className="grid gap-4 rounded-xl border bg-card p-5 md:grid-cols-2">
                <label className="text-sm font-semibold">Arquivo (JPG, PNG, WebP, GIF ou PDF · até 5 MB)<input className="mt-2 block w-full text-sm" type="file" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf" onChange={event => form.setData('file', event.target.files?.[0] ?? null)} required />{form.errors.file && <span className="mt-1 block text-destructive">{form.errors.file}</span>}</label>
                <label className="text-sm font-semibold">Texto alternativo<input className="mt-2 w-full rounded-lg border-input" value={form.data.alt_text} onChange={event => form.setData('alt_text', event.target.value)} maxLength={180} placeholder="Descreva a imagem para leitores de tela" />{form.errors.alt_text && <span className="mt-1 block text-destructive">{form.errors.alt_text}</span>}</label>
                <label className="text-sm font-semibold">Crédito<input className="mt-2 w-full rounded-lg border-input" value={form.data.credit} onChange={event => form.setData('credit', event.target.value)} maxLength={180} placeholder="Autoria ou origem do arquivo" />{form.errors.credit && <span className="mt-1 block text-destructive">{form.errors.credit}</span>}</label>
                <div className="flex items-end"><button disabled={form.processing} className="admin-button">{form.processing ? 'Enviando…' : 'Enviar arquivo'}</button></div>
            </form>
            <form method="get" action="/admin/media" className="grid gap-3 rounded-xl border bg-card p-4 sm:grid-cols-[1fr_12rem_auto_auto]">
                <label className="sr-only" htmlFor="media-search">Buscar arquivo, texto alternativo ou crédito</label><input id="media-search" name="q" defaultValue={filters.q} placeholder="Nome, descrição ou crédito" className="rounded-lg border px-3 py-2" />
                <label className="sr-only" htmlFor="media-type">Filtrar por tipo</label><select id="media-type" name="type" defaultValue={filters.type ?? ''} className="rounded-lg border px-3 py-2"><option value="">Todos os tipos</option><option value="image">Imagens</option><option value="document">Documentos</option></select>
                <button className="admin-button">Buscar</button><Link href="/admin/media" className="admin-button-secondary">Limpar</Link>
            </form>
            <p className="text-sm text-muted-foreground">{media.total} arquivo(s)</p>
            {media.data.length ? <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">{media.data.map(item => {
                const isImage = item.mime_type.startsWith('image/');
                return <article key={item.id} className="overflow-hidden rounded-xl border bg-card">
                    {isImage ? <img src={item.preview_url} alt={item.alt_text || item.original_name} className="aspect-[4/3] w-full bg-muted object-cover" /> : <a href={item.preview_url} target="_blank" rel="noreferrer" className="flex aspect-[4/3] items-center justify-center bg-muted p-6 text-center font-semibold text-primary underline">Abrir documento PDF ↗</a>}
                    <div className="space-y-1 p-4"><p className="truncate text-sm font-semibold" title={item.original_name}>{item.original_name}</p><p className="text-xs text-muted-foreground">{item.mime_type} · {sizeLabel(item.size)}{item.width && item.height ? ` · ${item.width} × ${item.height} px` : ''}</p>{!item.is_public && <p className="text-xs font-semibold text-secondary-foreground">Arquivo preservado no acervo privado · não acessível ao público</p>}<p className="text-xs text-muted-foreground">Alternativo: {item.alt_text || 'não informado'}</p><p className="text-xs text-muted-foreground">Crédito: {item.credit || 'não informado'}</p>{item.source_submission && <p className="text-xs text-muted-foreground">Contribuição: <Link className="font-semibold underline" href={`/admin/submissions/${item.source_submission.id}`}>{item.source_submission.title} · ver autorizações</Link></p>}<p className="text-xs font-medium text-muted-foreground">{item.used_in_count ? `Usado em ${item.used_in_count} conteúdo(s)` : 'Ainda não utilizado'}</p><button type="button" disabled={item.used_in_count > 0} onClick={() => { if (confirm(`Excluir “${item.original_name}”?`)) router.delete(`/admin/media/${item.id}`); }} className="mt-2 rounded-lg border border-destructive/30 px-3 py-1.5 text-xs font-semibold text-destructive disabled:cursor-not-allowed disabled:opacity-40" title={item.used_in_count ? 'Remova as referências antes de excluir' : undefined}>{item.used_in_count ? 'Arquivo em uso' : 'Excluir arquivo'}</button></div>
                </article>;
            })}</div> : <div className="rounded-xl border bg-card p-10 text-center text-muted-foreground">Nenhum arquivo corresponde a essa busca.</div>}
            <AdminPagination links={media.links} label="Paginação da biblioteca" />
        </div>
    </>;
}
