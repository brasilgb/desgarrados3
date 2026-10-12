import PortalBreadcrumb from '@/components/portal-breadcrumb';
import { CalendarDays, ExternalLink, MapPin } from 'lucide-react';
import PortalSeo from '@/components/portal-seo';
import JsonLd from '@/components/json-ld';

type Event = { name: string; description: string | null; category: string; municipality: string; state: string; venue: string | null; starts_on: string; ends_on: string | null; organizer: string | null; source_name: string; source_url: string; verified_at: string; additional_url: string | null };
const date = (value: string) => new Date(`${value}T12:00:00`).toLocaleDateString('pt-BR');

export default function RegionalEvent({ event }: { event: Event }) {
    return <><PortalSeo title={event.name} description={event.description ?? `${event.category} em ${event.municipality}/${event.state}.`} />
        <JsonLd id="event" schema={{
            '@type': 'Event',
            name: event.name,
            description: event.description,
            startDate: event.starts_on,
            endDate: event.ends_on,
            location: { '@type': 'Place', name: event.venue ?? `${event.municipality}/${event.state}`, address: { '@type': 'PostalAddress', addressLocality: event.municipality, addressRegion: event.state, addressCountry: 'BR' } },
            organizer: event.organizer ? { '@type': 'Organization', name: event.organizer } : null,
        }} /><article className="mx-auto max-w-4xl px-5 py-12 md:py-16"><PortalBreadcrumb items={[{ label: 'Portal', href: '/' }, { label: 'Nossa Terra', href: '/nossa-terra' }, { label: 'Agenda regional', href: '/agenda-regional' }, { label: event.name }]} /><p className="mt-7 text-xs font-bold uppercase tracking-[.2em] text-[#bb623e]">{event.category}</p><h1 className="mt-2 font-serif text-4xl font-bold text-[#31453b] md:text-5xl">{event.name}</h1><p className="mt-5 flex flex-wrap gap-4 text-sm text-stone-600"><span className="inline-flex items-center gap-2"><CalendarDays className="h-4 w-4"/>{date(event.starts_on)}{event.ends_on && ` – ${date(event.ends_on)}`}</span><span className="inline-flex items-center gap-2"><MapPin className="h-4 w-4"/>{event.municipality}, {event.state}{event.venue && ` · ${event.venue}`}</span></p>{event.description && <p className="mt-8 whitespace-pre-line text-lg leading-8 text-stone-700">{event.description}</p>}{event.organizer && <p className="mt-5 text-sm"><strong>Organização:</strong> {event.organizer}</p>}<section className="mt-10 rounded-2xl bg-[#e8dfcf] p-5"><h2 className="font-serif text-xl font-bold">Confirmação e fonte</h2><p className="mt-2 text-sm text-stone-700">Fonte: {event.source_name} · verificada em {date(event.verified_at)}.</p><a href={event.source_url} target="_blank" rel="noreferrer" className="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-[#31453b] underline">Consultar a fonte <ExternalLink className="h-4 w-4"/></a>{event.additional_url && <a href={event.additional_url} target="_blank" rel="noreferrer" className="ml-5 inline-flex items-center gap-2 text-sm font-semibold text-[#31453b] underline">Mais informações <ExternalLink className="h-4 w-4"/></a>}</section></article></>;
}
