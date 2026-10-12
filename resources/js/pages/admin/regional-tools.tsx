import { Head, Link, router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Eye, EyeOff, Save, Star } from 'lucide-react';
import { useState } from 'react';

type Tool = { id: string; title: string; category: string };
type Props = { tools: Tool[]; enabled: string[]; featured: string[]; titles: Record<string, string>; order: string[] };

export default function RegionalTools({ tools, enabled: initialEnabled, featured: initialFeatured, titles: initialTitles, order: initialOrder }: Props) {
    const [enabled, setEnabled] = useState(initialEnabled);
    const [featured, setFeatured] = useState(initialFeatured);
    const [titles, setTitles] = useState(initialTitles);
    const [order, setOrder] = useState(initialOrder);
    const sorted = [...tools].sort((a, b) => order.indexOf(a.id) - order.indexOf(b.id));
    const toggle = (items: string[], id: string, set: (value: string[]) => void) => set(items.includes(id) ? items.filter(item => item !== id) : [...items, id]);
    const move = (index: number, offset: -1 | 1) => {
        const target = index + offset;
        if (target < 0 || target >= sorted.length) return;
        const next = [...sorted.map(tool => tool.id)];
        [next[index], next[target]] = [next[target], next[index]];
        setOrder(next);
    };

    function save() {
        router.put('/admin/campeira', { enabled, featured, titles, order }, { preserveScroll: true });
    }

    return <>
        <Head title="Central Campeira" />
        <div className="w-full min-w-0 space-y-6">
            <div className="flex flex-wrap items-end justify-between gap-4"><div><p className="text-sm font-medium uppercase tracking-wider text-muted-foreground">Administração editorial</p><h1 className="mt-1 font-serif text-3xl font-bold">Central Campeira</h1><p className="mt-2 max-w-2xl text-muted-foreground">Ative ferramentas, escolha destaques e ajuste seus títulos. As fórmulas permanecem versionadas no código.</p></div><Link href="/nossa-terra" className="text-sm font-semibold text-primary underline">Ver a central pública ↗</Link></div>
            <div className="space-y-3">{sorted.map((tool, index) => <section key={tool.id} className="grid gap-4 rounded-xl border border-border bg-card p-4 md:grid-cols-[1fr_auto] md:items-center"><div><p className="text-xs font-bold uppercase tracking-wider text-muted-foreground">{tool.category}</p><label className="mt-2 block text-sm font-semibold">Título público<input value={titles[tool.id] ?? ''} placeholder={tool.title} maxLength={80} onChange={event => setTitles(current => ({ ...current, [tool.id]: event.target.value }))} className="mt-1 block w-full rounded-lg border-input text-base" /></label><div className="mt-3 flex flex-wrap gap-4"><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={enabled.includes(tool.id)} onChange={() => toggle(enabled, tool.id, setEnabled)} />{enabled.includes(tool.id) ? <Eye className="h-4 w-4" /> : <EyeOff className="h-4 w-4" />} Disponível ao público</label><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={featured.includes(tool.id)} onChange={() => toggle(featured, tool.id, setFeatured)} /><Star className="h-4 w-4" /> Destaque na central</label></div></div><div className="flex items-center gap-2"><span className="mr-2 text-xs text-muted-foreground">Posição {index + 1}</span><button type="button" aria-label={`Mover ${tool.title} para cima`} disabled={index === 0} onClick={() => move(index, -1)} className="rounded border p-2 disabled:opacity-40"><ArrowUp className="h-4 w-4" /></button><button type="button" aria-label={`Mover ${tool.title} para baixo`} disabled={index === tools.length - 1} onClick={() => move(index, 1)} className="rounded border p-2 disabled:opacity-40"><ArrowDown className="h-4 w-4" /></button></div></section>)}</div>
            <div className="sticky bottom-4 flex justify-end"><button type="button" onClick={save} className="admin-button flex items-center gap-2 shadow-lg"><Save className="h-4 w-4" />Salvar configuração</button></div>
        </div>
    </>;
}
