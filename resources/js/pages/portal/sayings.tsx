import PortalBreadcrumb from '@/components/portal-breadcrumb';
import PortalPageHero from '@/components/portal-page-hero';
import PortalSeo from '@/components/portal-seo';

type Saying = { id: number; phrase: string; meaning: string; context: string | null; source_note: string | null };
export default function SayingsPage({ sayings }: { sayings: Saying[] }) {
    return <><PortalSeo title="Ditados e expressões" description="Ditados e expressões populares do Sul com significado e contexto, sem atribuições inventadas." /><PortalPageHero image="cultura" width="medium" eyebrow="Palavras que ficam" title="Ditados e expressões" intro="Frases populares com explicações e contexto, sem atribuições inventadas." /><PortalBreadcrumb items={[{ label: 'Portal', href: '/' }, { label: 'Ditados e expressões' }]} /><main className="mx-auto grid max-w-5xl gap-4 px-5 py-10 md:grid-cols-2">{sayings.map((item, i) => <article key={item.id} id={`ditado-${item.id}`} className={`portal-card scroll-mt-24 rounded-2xl p-6 ${i === 0 ? 'bg-[#e8dfcf]' : 'border border-[#ded5c5] bg-[#fbf8f1]'}`}><blockquote className="font-serif text-2xl font-bold text-[#31453b]">“{item.phrase}”</blockquote><p className="mt-4 leading-7">{item.meaning}</p>{item.context && <p className="mt-3 text-sm text-stone-600">{item.context}</p>}{item.source_note && <p className="mt-4 border-t border-stone-300 pt-3 text-xs text-stone-500">{item.source_note}</p>}</article>)}</main></>;
}
