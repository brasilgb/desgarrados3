import { Head, router } from '@inertiajs/react';
import AdminPagination from '@/components/admin-pagination';

type Message = { id: number; name: string; email: string; subject: string; message: string; forwarded_at: string | null; read_at: string | null; created_at: string };
type Props = { messages: { data: Message[]; links: { url: string | null; label: string; active: boolean }[] }; unread: number };

const dateTime = (value: string) => new Date(value).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });

export default function ContactMessages({ messages, unread }: Props) {
    function toggleRead(message: Message) { router.patch(`/admin/contact-messages/${message.id}/read`, {}, { preserveScroll: true }); }
    function remove(message: Message) { if (confirm(`Excluir a mensagem de ${message.name}? Esta ação não pode ser desfeita.`)) router.delete(`/admin/contact-messages/${message.id}`, { preserveScroll: true }); }

    return <><Head title="Mensagens de contato" /><div className="w-full min-w-0 space-y-6">
        <div><p className="text-sm font-medium uppercase tracking-wider text-muted-foreground">Administração</p><h1 className="mt-1 font-serif text-3xl font-bold">Mensagens de contato</h1><p className="mt-2 text-muted-foreground">Enviadas pela página Contato. {unread === 1 ? '1 mensagem não lida.' : `${unread} mensagens não lidas.`} Os dados pessoais devem ser usados somente para responder ao pedido.</p></div>
        {messages.data.length ? <div className="space-y-4">{messages.data.map(message => <article key={message.id} className={`rounded-xl border bg-card p-5 ${message.read_at ? '' : 'border-primary/40'}`}>
            <header className="flex flex-wrap items-start justify-between gap-3"><div className="min-w-0"><h2 className="font-semibold">{!message.read_at && <span className="mr-2 rounded-full bg-primary px-2 py-0.5 text-xs text-primary-foreground">Nova</span>}{message.subject}</h2><p className="mt-1 text-sm text-muted-foreground">{message.name} · <a className="underline" href={`mailto:${message.email}?subject=${encodeURIComponent(`Re: ${message.subject}`)}`}>{message.email}</a> · {dateTime(message.created_at)}{message.forwarded_at ? ' · encaminhada por e-mail' : ''}</p></div>
                <div className="flex gap-2"><button type="button" onClick={() => toggleRead(message)} className="admin-button-secondary">{message.read_at ? 'Marcar como não lida' : 'Marcar como lida'}</button><button type="button" onClick={() => remove(message)} className="admin-button-secondary text-destructive">Excluir</button></div></header>
            <p className="mt-4 whitespace-pre-line break-words text-sm leading-6">{message.message}</p>
        </article>)}</div> : <p className="rounded-xl border bg-card p-8 text-center text-muted-foreground">Nenhuma mensagem recebida.</p>}
        <AdminPagination links={messages.links} label="Paginação das mensagens de contato" />
    </div></>;
}
