import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

type Page = { id: number; title: string; slug: string; summary: string | null; body: string; status: string };
type Template = Omit<Page, 'id'>;

const TERMS_SLUG = 'termos-de-contribuicao';
const readableHtml = (html: string) => html
    .replace(/<\/(p|h[1-6]|li|br|blockquote)>/gi, '\n')
    .replace(/<[^>]*>/g, ' ')
    .replace(/&nbsp;/gi, ' ')
    .replace(/&amp;/gi, '&')
    .replace(/&lt;/gi, '<')
    .replace(/&gt;/gi, '>')
    .replace(/&quot;/gi, '"')
    .replace(/&#39;/gi, "'")
    .replace(/[ \t]+/g, ' ')
    .replace(/ *\n */g, '\n')
    .trim();

export default function SitePageForm({ page, template = null, termsDefaultBody = null }: { page: Page | null; template?: Template | null; termsDefaultBody?: string | null }) {
    const initial = page ?? template;
    const form = useForm({
        title: initial?.title ?? '',
        slug: initial?.slug ?? '',
        summary: initial?.summary ?? '',
        body: initial?.body ?? '<p></p>',
        status: initial?.status ?? 'draft',
        terms_comparison_confirmed: false,
    });
    function submit(event: FormEvent) {
        event.preventDefault();
        page ? form.put(`/admin/site-pages/${page.id}`) : form.post('/admin/site-pages');
    }

    const input = 'mt-1 w-full rounded-lg border-input';
    const isTerms = page?.slug === TERMS_SLUG || form.data.slug === TERMS_SLUG;
    const publishTerms = isTerms && form.data.status === 'published';
    const fieldError = (field: keyof typeof form.errors) => form.errors[field] && <small id={`site-page-${field}-error`} role="alert" className="mt-1 block font-normal text-destructive">{form.errors[field]}</small>;

    return <>
        <Head title={page ? 'Editar página' : 'Criar página'} />
        <div className="w-full min-w-0">
            <Link href="/admin/site-pages" className="text-sm underline">← Páginas institucionais</Link>
            <h1 className="mt-4 font-serif text-3xl font-bold">{page ? `Editar ${page.title}` : 'Nova página institucional'}</h1>
            <form onSubmit={submit} className="mt-6 space-y-5 rounded-2xl border bg-card p-5 md:p-8">
                {Object.entries(form.errors).length > 0 && <div role="alert" aria-live="assertive" className="rounded-lg border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive"><p className="font-semibold">Confira os campos indicados antes de salvar.</p><ul className="mt-2 list-inside list-disc">{Object.values(form.errors).map((error, i) => <li key={i}>{error}</li>)}</ul></div>}
                <label className="block text-sm font-semibold">Título<input aria-invalid={Boolean(form.errors.title)} aria-describedby={form.errors.title ? 'site-page-title-error' : undefined} className={input} value={form.data.title} onChange={e => form.setData('title', e.target.value)} required />{fieldError('title')}</label>
                <label className="block text-sm font-semibold">Endereço curto<input aria-invalid={Boolean(form.errors.slug)} aria-describedby={form.errors.slug ? 'site-page-slug-error' : undefined} className={input} value={form.data.slug} onChange={e => form.setData('slug', e.target.value)} placeholder="gerado a partir do título" />{fieldError('slug')}</label>
                <label className="block text-sm font-semibold">Descrição para busca<textarea aria-invalid={Boolean(form.errors.summary)} aria-describedby={form.errors.summary ? 'site-page-summary-error' : undefined} className={input} rows={2} value={form.data.summary} onChange={e => form.setData('summary', e.target.value)} />{fieldError('summary')}</label>
                <label className="block text-sm font-semibold">Conteúdo editorial<textarea aria-invalid={Boolean(form.errors.body)} aria-describedby={`site-page-body-help${form.errors.body ? ' site-page-body-error' : ''}`} className={`${input} font-mono text-sm`} rows={16} value={form.data.body} onChange={e => form.setData('body', e.target.value)} required /><span id="site-page-body-help" className="mt-1 block font-normal text-muted-foreground">HTML básico é sanitizado no servidor ao salvar.</span>{fieldError('body')}</label>
                {isTerms && termsDefaultBody && <section aria-labelledby="terms-review-heading" className="space-y-4 rounded-xl border border-amber-500/50 bg-amber-50 p-4 text-amber-950">
                    <div><h2 id="terms-review-heading" className="font-semibold">Comparar os Termos de Envio</h2><p className="mt-1 text-sm leading-6">O texto à esquerda acompanha o conteúdo atual no editor, incluindo alterações ainda não salvas. À direita está o padrão revisado do projeto. O padrão detalha licença limitada ao portal, cópias técnicas, usos comerciais e em redes que exigem autorização separada, autorização de imagem independente, proteção de menores e pedidos de correção ou retirada. Nada será substituído até você escolher a ação abaixo; salvar uma versão publicada exige confirmação desta revisão.</p></div>
                    <div className="grid gap-4 lg:grid-cols-2">
                        <article className="min-w-0 rounded-lg border border-amber-900/20 bg-white p-3"><h3 className="text-sm font-bold">Conteúdo atual no editor</h3><p className="mt-2 max-h-80 overflow-auto whitespace-pre-wrap break-words text-sm leading-6">{readableHtml(form.data.body) || 'Sem texto.'}</p></article>
                        <article className="min-w-0 rounded-lg border border-amber-900/20 bg-white p-3"><h3 className="text-sm font-bold">Padrão revisado</h3><p className="mt-2 max-h-80 overflow-auto whitespace-pre-wrap break-words text-sm leading-6">{readableHtml(termsDefaultBody) || 'Sem texto padrão.'}</p></article>
                    </div>
                    <button type="button" className="admin-button-secondary" onClick={() => {
                        if (window.confirm('Carregar o padrão revisado no editor? O texto que está no editor será substituído, mas nada será salvo ou publicado até você enviar o formulário.')) {
                            form.setData('body', termsDefaultBody);
                            form.setData('terms_comparison_confirmed', false);
                        }
                    }}>Usar o padrão revisado no editor</button>
                    {publishTerms && <label className="flex items-start gap-3 border-t border-amber-900/20 pt-4 text-sm leading-6"><input type="checkbox" required aria-invalid={Boolean(form.errors.terms_comparison_confirmed)} aria-describedby={form.errors.terms_comparison_confirmed ? 'site-page-terms_comparison_confirmed-error' : undefined} checked={form.data.terms_comparison_confirmed} onChange={e => form.setData('terms_comparison_confirmed', e.target.checked)} className="mt-1 h-4 w-4 shrink-0" /><span>Comparei o texto personalizado com o padrão revisado e confirmo que esta versão está aprovada para publicação.</span>{fieldError('terms_comparison_confirmed')}</label>}
                </section>}
                <label className="block text-sm font-semibold">Estado<select aria-invalid={Boolean(form.errors.status)} aria-describedby={form.errors.status ? 'site-page-status-error' : undefined} className={input} value={form.data.status} onChange={e => form.setData('status', e.target.value)}><option value="draft">Rascunho</option><option value="published">Publicada</option></select>{fieldError('status')}</label>
                <div className="flex flex-wrap gap-3"><button disabled={form.processing} className="admin-button">{form.processing ? 'Salvando…' : 'Salvar página'}</button>{page && <Link href={`/${page.slug}`} className="self-center text-sm underline">Prévia pública</Link>}</div>
            </form>
        </div>
    </>;
}
