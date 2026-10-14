import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight } from 'lucide-react';
import JsonLd from '@/components/json-ld';
import PortalBreadcrumb from '@/components/portal-breadcrumb';
import PortalPageHero from '@/components/portal-page-hero';
import PortalSeo from '@/components/portal-seo';
import RegionalToolCalculator from '@/components/regional-tool-calculator';
import { regionalToolResultKind } from '@/lib/regional-calculations.js';
import { toolPath, type ToolId } from '@/lib/regional-tools-catalog';
import { regionalTools } from '@/lib/regional-tools';

type Props = { toolId: ToolId; enabledTools: ToolId[]; toolTitles: Partial<Record<ToolId, string>> };

export default function RegionalToolPage({ toolId, enabledTools, toolTitles }: Props) {
    const { appUrl } = usePage<{ appUrl?: string }>().props;
    const base = regionalTools.find(item => item.id === toolId)!;
    const tool = { ...base, title: toolTitles[toolId] || base.title };
    const related = regionalTools.filter(item => item.id !== toolId && item.category === tool.category && enabledTools.includes(item.id)).map(item => ({ ...item, title: toolTitles[item.id] || item.title }));
    const conversion = regionalToolResultKind(toolId) === 'conversion';
    const url = `${appUrl ?? ''}${toolPath(toolId)}`;

    return <>
        <PortalSeo title={`${tool.title} · Calculadora da Central Campeira`} description={`${tool.description} Calculadora gratuita, sem cadastro e sem guardar seus dados.`} />
        <JsonLd id="tool" schema={{ '@type': 'WebApplication', name: tool.title, url, description: `${tool.description} ${tool.about[0]}`, applicationCategory: 'UtilitiesApplication', operatingSystem: 'Qualquer navegador', inLanguage: 'pt-BR', isAccessibleForFree: true, offers: { '@type': 'Offer', price: '0', priceCurrency: 'BRL' } }} />
        <PortalPageHero image="nossa-terra" icon={tool.icon} eyebrow={`Central Campeira · ${tool.category}`} title={tool.title} intro={tool.description} width="medium" />
        <PortalBreadcrumb items={[{ label: 'Portal', href: '/' }, { label: 'Nossa Terra', href: '/nossa-terra' }, { label: tool.title }]} />
        <section aria-labelledby="tool-calculator-title" className="mx-auto max-w-5xl px-5 pb-10 pt-4">
            <div className="rounded-3xl border border-[#ded5c5] bg-[#fbf8f1] p-5 sm:p-7">
                <h2 id="tool-calculator-title" className="font-serif text-2xl font-bold text-[#31453b]">Faça a conta</h2>
                <p className="mb-6 mt-1 text-sm leading-6 text-[#625f55]">O resultado aparece na hora. Seus valores ficam só neste aparelho; nada é salvo nem enviado.</p>
                <RegionalToolCalculator tool={tool} />
            </div>
        </section>
        <section className="mx-auto grid max-w-5xl gap-10 px-5 pb-12 md:grid-cols-[1.4fr_1fr] md:pb-16">
            <div>
                <h2 className="font-serif text-2xl font-bold text-[#31453b]">Para que serve</h2>
                {tool.about.map(paragraph => <p key={paragraph} className="mt-3 leading-7 text-[#3c3a31]">{paragraph}</p>)}
                <h2 className="mt-9 font-serif text-2xl font-bold text-[#31453b]">Como usar</h2>
                <ol className="mt-3 list-decimal space-y-2 pl-5 leading-7 text-[#3c3a31]">
                    <li>Preencha os campos: {tool.fields.map(field => `${field.label.toLocaleLowerCase('pt-BR')}${field.unit ? ` (${field.unit})` : ''}`).join(', ')}.</li>
                    <li>Confira o resultado no quadro verde, que se atualiza a cada valor alterado.</li>
                    <li>Use “Copiar resultado” para guardar ou enviar a conta, ou “Limpar valores” para recomeçar.</li>
                </ol>
                <h2 className="mt-9 font-serif text-2xl font-bold text-[#31453b]">Sobre o resultado</h2>
                <p className="mt-3 leading-7 text-[#3c3a31]">{conversion
                    ? 'É uma conversão matemática entre unidades, com valores arredondados para facilitar a leitura.'
                    : 'É uma estimativa feita só com os números que você informou. A calculadora não consulta cotações nem dados de mercado e não substitui a orientação de um técnico.'}</p>
            </div>
            <aside className="space-y-4">
                {related.length > 0 && <nav aria-labelledby="related-tools-title" className="rounded-2xl bg-[#e8dfcf] p-5">
                    <h2 id="related-tools-title" className="text-xs font-bold uppercase tracking-widest text-[#bb623e]">Outras ferramentas de {tool.category.toLocaleLowerCase('pt-BR')}</h2>
                    <ul className="mt-3 space-y-1">{related.map(item => <li key={item.id}><Link href={toolPath(item.id)} className="group flex items-start gap-3 rounded-xl px-2 py-2 hover:bg-[#fbf8f1]"><item.icon aria-hidden="true" className="mt-1 h-4 w-4 shrink-0 text-[#31453b]" /><span><span className="block font-semibold text-[#31453b] group-hover:text-[#bb623e]">{item.title}</span><span className="block text-xs leading-5 text-[#625f55]">{item.description}</span></span></Link></li>)}</ul>
                </nav>}
                <Link href="/nossa-terra#ferramentas" className="inline-flex items-center gap-2 text-sm font-semibold text-[#31453b] underline"><ArrowLeft aria-hidden="true" className="h-4 w-4" />Todas as ferramentas da Central Campeira</Link>
                <Link href="/calendario-rural" className="flex items-center gap-2 text-sm font-semibold text-[#31453b] underline">Calendário rural <ArrowRight aria-hidden="true" className="h-4 w-4" /></Link>
            </aside>
        </section>
    </>;
}
