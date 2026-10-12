import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

type MediaItem = { id: number; path: string; alt_text: string | null; credit: string | null };
type Publication = { id: number; title: string; slug: string; status: string; published_at: string | null };
type Submission = {
    id: number; title: string; story: string; contributor_name: string | null; origin_city: string | null; origin_state: string | null;
    contact_email: string | null; contact_consent: boolean; rights_confirmed: boolean; publication_consent: boolean; terms_accepted_at: string | null; terms_version: string | null; accepted_terms: { id: number; label: string; source: string; created_at: string } | null;
    display_name_consent: boolean; photo_path: string | null; photo_original_name: string | null; photo_credit: string | null;
    photo_publication_consent: boolean; status: string; moderation_note: string | null; moderation_approved_at: string | null;
    public_media: MediaItem | null; publication: Publication | null;
};
type Props = { submission: Submission; categories: { id: number; name: string }[]; currentTermsLabel?: string | null };

export default function ReviewSubmission({ submission: item, categories, currentTermsLabel = null }: Props) {
    const note = useForm({ moderation_note: item.moderation_note ?? '' });
    const convert = useForm({ type: 'historia', category_id: '', excerpt: '' });
    const incorporate = useForm({ alt_text: item.title });
    const input = 'mt-1 w-full rounded-lg border border-input bg-card px-3 py-2';
    function post(path: string, event: FormEvent) {
        event.preventDefault();
        note.post(`/admin/submissions/${item.id}/${path}`);
    }

    return <>
        <Head title={`Relato · ${item.title}`} />
        <div className="w-full min-w-0 space-y-6">
            <Link href={`/admin/submissions?status=${item.status}`} className="text-sm text-muted-foreground">← Fila de contribuições</Link>
            <article className="rounded-xl border bg-card p-5 md:p-8">
                <p className="text-xs font-bold uppercase tracking-widest text-secondary-foreground">{item.status === 'pending' ? 'Aguardando moderação' : item.status}</p>
                <h1 className="mt-3 font-serif text-3xl font-bold">{item.title}</h1>
                <p className="mt-3 text-sm text-muted-foreground">Crédito informado: {item.contributor_name || 'não informado'} · Exibição pública do nome: {item.display_name_consent ? 'autorizada' : 'não autorizada'}{item.origin_city ? ` · ${item.origin_city}${item.origin_state ? `, ${item.origin_state}` : ''}` : ''}</p>
                <div className="mt-7 whitespace-pre-wrap border-y py-6 text-base leading-8 text-foreground">{item.story}</div>
                <p className="mt-4 text-xs text-muted-foreground">Autoria/direitos confirmados: {item.rights_confirmed ? 'sim' : 'não'} · Autorização para publicar relato: {item.publication_consent ? 'sim' : 'não'} · Termos de envio: {item.terms_accepted_at ? <>aceitos em {new Date(item.terms_accepted_at).toLocaleString('pt-BR')}, versão {item.accepted_terms ? <Link href={`/admin/terms-versions/${item.accepted_terms.id}`} className="underline">{item.accepted_terms.label}</Link> : item.terms_version}{currentTermsLabel && (item.accepted_terms?.label ?? item.terms_version) !== currentTermsLabel ? ' (anterior aos termos atuais; o aceite não se estende às novas condições)' : ''}</> : 'sem registro (envio anterior ao aceite)'}</p>

                {item.photo_path && <section className="mt-5 rounded-lg bg-muted p-4" aria-label="Fotografia enviada">
                    <h2 className="text-sm font-semibold">Fotografia privada · {item.photo_original_name || 'arquivo enviado'}</h2>
                    <p className="mt-1 text-sm">Crédito: {item.photo_credit || 'não informado'}</p>
                    <p className="mt-1 text-xs text-muted-foreground">Autorização para publicar a imagem: {item.photo_publication_consent ? 'sim' : 'não'}</p>
                    <a className="mt-2 inline-block text-sm font-semibold underline" href={`/admin/submissions/${item.id}/photo`}>Abrir ou baixar fotografia privada</a>
                    {item.public_media ? <p className="mt-3 text-sm">Fotografia incorporada. <Link href={`/admin/media?q=${encodeURIComponent(item.public_media.credit ?? '')}`} className="font-semibold underline">Ver na biblioteca de mídia</Link>; contribuição e autorizações permanecem vinculadas.</p>
                        : item.status === 'converted' && item.moderation_approved_at && item.publication?.status === 'published' && item.photo_publication_consent && item.rights_confirmed && item.publication_consent
                            ? <form onSubmit={event => { event.preventDefault(); incorporate.post(`/admin/submissions/${item.id}/photo/incorporate`); }} className="mt-4 space-y-3 border-t pt-4">
                                <p className="text-sm">A história passou por moderação e está publicada. A incorporação torna esta foto autorizada visível como capa e no acervo público.</p>
                                <label className="block text-sm font-semibold">Texto alternativo<input className={input} value={incorporate.data.alt_text} maxLength={180} onChange={event => incorporate.setData('alt_text', event.target.value)} required />{incorporate.errors.alt_text && <small className="text-destructive">{incorporate.errors.alt_text}</small>}</label>
                                <button disabled={incorporate.processing} className="admin-button">{incorporate.processing ? 'Incorporando…' : 'Incorporar à biblioteca e à publicação'}</button>
                            </form>
                            : <p className="mt-3 text-xs text-muted-foreground">A foto só poderá entrar no acervo público após autorização da imagem, aprovação editorial, conversão e publicação da história.</p>}
                    <p className="mt-2 text-xs text-muted-foreground">O arquivo original permanece protegido durante a moderação.</p>
                </section>}

                {item.contact_email && item.contact_consent && <p className="mt-3 rounded-lg bg-muted p-3 text-sm text-foreground">E-mail privado, autorizado apenas para contato sobre este relato: <a className="font-semibold underline" href={`mailto:${item.contact_email}`}>{item.contact_email}</a></p>}
                {item.moderation_note && <div className="mt-5 rounded-lg bg-muted p-4 text-sm"><strong>Nota editorial:</strong> {item.moderation_note}</div>}
                {item.publication && <p className="mt-4 text-sm">Publicação associada: <Link className="underline" href={`/admin/publications/${item.publication.id}/edit`}>{item.publication.title}</Link> · {item.publication.status}</p>}
            </article>

            {(item.status === 'pending' || (item.status === 'approved' && !item.moderation_approved_at)) && <form onSubmit={event => post('approve', event)} className="rounded-xl border bg-card p-5">
                <h2 className="font-semibold">Aprovar para trabalho editorial</h2><p className="mt-1 text-sm text-muted-foreground">A aprovação registra a decisão, mas mantém o relato fora do portal.</p>
                <textarea className={input} rows={2} placeholder="Nota para a equipe (opcional)" value={note.data.moderation_note} onChange={event => note.setData('moderation_note', event.target.value)} />
                <button className="admin-button mt-3">Aprovar relato</button>
            </form>}
            {(item.status === 'pending' || item.status === 'approved') && <form onSubmit={event => post('reject', event)} className="rounded-xl border bg-card p-5">
                <h2 className="font-semibold">Recusar contribuição</h2><textarea className={input} rows={2} placeholder="Motivo editorial obrigatório" value={note.data.moderation_note} onChange={event => note.setData('moderation_note', event.target.value)} required />
                <button className="mt-3 rounded-lg border border-destructive/50 px-5 py-2 font-semibold text-destructive">Recusar relato</button>
            </form>}
            {item.status === 'approved' && item.moderation_approved_at && <form onSubmit={event => { event.preventDefault(); convert.post(`/admin/submissions/${item.id}/convert`); }} className="rounded-xl border bg-card p-5">
                <h2 className="font-semibold">Converter em publicação</h2><p className="mt-1 text-sm text-muted-foreground">A conversão cria um rascunho privado, atribuído ao editor que o revisará.</p>
                <div className="mt-4 grid gap-3 sm:grid-cols-3">
                    <select aria-label="Caderno" className={input} value={convert.data.type} onChange={event => convert.setData('type', event.target.value)}>{[['historia', 'História'], ['causo', 'Causo'], ['anedota', 'Anedota'], ['poema', 'Poesia'], ['literatura', 'Literatura'], ['musica', 'Música'], ['tradição', 'Tradição']].map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select>
                    <select aria-label="Assunto" className={input} value={convert.data.category_id} onChange={event => convert.setData('category_id', event.target.value)}><option value="">Sem assunto</option>{categories.map(category => <option key={category.id} value={category.id}>{category.name}</option>)}</select>
                    <input className={input} placeholder="Resumo editorial (opcional)" value={convert.data.excerpt} onChange={event => convert.setData('excerpt', event.target.value)} />
                </div>
                <button className="admin-button mt-3">Criar rascunho</button>
            </form>}
        </div>
    </>;
}
