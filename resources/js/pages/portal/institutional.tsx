import { Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import PortalBreadcrumb from '@/components/portal-breadcrumb';
import PortalPageHero from '@/components/portal-page-hero';
import PortalSeo from '@/components/portal-seo';

type Props = { page: { title: string; slug: string; summary: string | null; body: string }; indexable: boolean; portalSettings: Record<string, string | null> };

const eyebrows: Record<string, string> = {
    'proposta-cultural': 'Desgarrados · Proposta',
    contato: 'Desgarrados · Fale com a equipe',
    'termos-de-contribuicao': 'Desgarrados · Contribuições',
};

export default function InstitutionalPage({ page, indexable, portalSettings }: Props) {
    return <>
        <PortalSeo title={page.title} description={page.summary} path={`/${page.slug}`} noindex={!indexable} />
        <PortalPageHero image="sobre" width="narrow" eyebrow={eyebrows[page.slug] ?? 'Desgarrados · Nossa casa'} title={page.title} intro={page.summary} />
        <PortalBreadcrumb items={[{ label: 'Portal', href: '/' }, { label: page.title }]} />
        <article className="editorial-body mx-auto max-w-3xl px-5 py-12" dangerouslySetInnerHTML={{ __html: page.body }} />
        {page.slug === 'contato'
            ? <ContactForm contactEmail={portalSettings.contact_email} />
            : <div className="mx-auto max-w-3xl px-5 pb-12"><Link href="/contribua" className="portal-button rounded-full bg-[#9c4e31] hover:bg-[#7e402b]">Compartilhe uma história ↗</Link></div>}
    </>;
}

function ContactForm({ contactEmail }: { contactEmail: string | null }) {
    const { flash } = usePage<{ flash?: { success?: string | null } }>().props;
    const form = useForm({ name: '', email: '', subject: '', message: '', privacy_acknowledged: false, website: '' });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/contato', { preserveScroll: true, onSuccess: () => form.reset() });
    }

    const error = (field: string, message?: string) => message && <small id={`contact-${field}-error`} role="alert" className="mt-1 block font-normal text-red-700">{message}</small>;

    return <section aria-labelledby="contact-form-title" className="mx-auto max-w-3xl px-5 pb-12">
        {flash?.success && <p role="status" className="mb-5 rounded-xl border border-emerald-300 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900">{flash.success}</p>}
        <form onSubmit={submit} className="space-y-5 rounded-2xl border border-[#ded5c5] bg-[#fbf8f1] p-4 sm:p-6 md:p-8">
            <h2 id="contact-form-title" className="font-serif text-2xl font-bold text-[#31453b]">Envie uma mensagem</h2>
            <div className="grid gap-4 sm:grid-cols-2">
                <label className="portal-label">Nome<input id="contact-name" aria-invalid={Boolean(form.errors.name)} aria-describedby={form.errors.name ? 'contact-name-error' : undefined} className="portal-field mt-1.5" value={form.data.name} onChange={event => form.setData('name', event.target.value)} required minLength={2} maxLength={120} autoComplete="name" />{error('name', form.errors.name)}</label>
                <label className="portal-label">E-mail<input id="contact-email" aria-invalid={Boolean(form.errors.email)} aria-describedby={form.errors.email ? 'contact-email-error' : undefined} type="email" className="portal-field mt-1.5" value={form.data.email} onChange={event => form.setData('email', event.target.value)} required maxLength={255} autoComplete="email" />{error('email', form.errors.email)}</label>
            </div>
            <label className="portal-label">Assunto<input id="contact-subject" aria-invalid={Boolean(form.errors.subject)} aria-describedby={form.errors.subject ? 'contact-subject-error' : undefined} className="portal-field mt-1.5" value={form.data.subject} onChange={event => form.setData('subject', event.target.value)} required minLength={3} maxLength={160} />{error('subject', form.errors.subject)}</label>
            <label className="portal-label">Mensagem<textarea id="contact-message" aria-invalid={Boolean(form.errors.message)} aria-describedby={form.errors.message ? 'contact-message-error' : undefined} className="portal-field mt-1.5" rows={7} value={form.data.message} onChange={event => form.setData('message', event.target.value)} required minLength={10} maxLength={5000} /><small className="mt-1 block font-normal text-stone-600">{form.data.message.length}/5.000 caracteres</small>{error('message', form.errors.message)}</label>
            <div aria-hidden="true" className="absolute -left-[10000px] h-px w-px overflow-hidden"><label>Deixe este campo vazio<input tabIndex={-1} autoComplete="off" name="website" value={form.data.website} onChange={event => form.setData('website', event.target.value)} /></label></div>
            <label className="flex items-start gap-3 text-sm leading-6"><input id="contact-privacy" aria-invalid={Boolean(form.errors.privacy_acknowledged)} aria-describedby={form.errors.privacy_acknowledged ? 'contact-privacy-error' : undefined} type="checkbox" required checked={form.data.privacy_acknowledged} onChange={event => form.setData('privacy_acknowledged', event.target.checked)} className="mt-1 h-5 w-5 shrink-0 accent-[#31453b]" /><span>Li a <Link href="/politica-de-privacidade" className="font-semibold underline">Política de Privacidade</Link> e concordo com o uso destes dados somente para responder a esta mensagem.</span></label>{error('privacy', form.errors.privacy_acknowledged)}
            <p className="text-xs leading-5 text-stone-600">Sua mensagem fica registrada para a equipe editorial e não é publicada no portal.{contactEmail ? <> Se preferir, escreva para <a className="font-semibold text-[#9c4e31] underline" href={`mailto:${contactEmail}`}>{contactEmail}</a>.</> : null}</p>
            <button disabled={form.processing} className="portal-button w-full sm:w-auto">{form.processing ? 'Enviando…' : 'Enviar mensagem'}</button>
        </form>
    </section>;
}
