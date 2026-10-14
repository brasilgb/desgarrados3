import { Check, Copy, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { calculateRegionalTool, regionalToolResultKind } from '@/lib/regional-calculations.js';
import { toolDefaults, type RegionalTool } from '@/lib/regional-tools';

/** Form and live result of one Central Campeira tool, used by the quick dialog and by the tool's own page. */
export default function RegionalToolCalculator({ tool }: { tool: RegionalTool }) {
    const [values, setValues] = useState(toolDefaults[tool.id]);
    const [copied, setCopied] = useState(false);
    const [copyError, setCopyError] = useState('');
    const result = useMemo(() => {
        try { return { value: calculateRegionalTool(tool.id, values), error: '' }; }
        catch (error) { return { value: null, error: error instanceof Error ? error.message : 'Revise os valores informados.' }; }
    }, [tool.id, values]);

    function update(key: string, value: string) {
        setCopied(false);
        setValues(current => ({ ...current, [key]: value, ...(key === 'basis' && value === 'live' ? { weight_unit: 'kg' } : {}) }));
    }
    async function copyResult() {
        if (!result.value) return;
        try {
            await navigator.clipboard.writeText(`${result.value.title}\n${result.value.lines.join('\n')}\n${result.value.note}`);
            setCopied(true);
            setCopyError('');
        } catch {
            setCopyError('Não foi possível copiar neste navegador. Selecione e copie os valores manualmente.');
        }
    }

    return <div className="grid gap-6 md:grid-cols-[1fr_.9fr]">
        <div className="space-y-4">{tool.fields.map(field => <label key={field.key} className="block text-sm font-semibold text-[#3c3a31]">{field.label}{field.choices ? <select value={values[field.key]} onChange={event => update(field.key, event.target.value)} className="mt-1 block h-11 w-full rounded-md border border-[#cfc4b2] bg-white px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#bb623e]">{(field.key === 'weight_unit' && values.basis === 'live' ? field.choices.filter(choice => choice.value === 'kg') : field.choices).map(choice => <option key={choice.value} value={choice.value}>{choice.label}</option>)}</select> : <span className="mt-1 flex items-center rounded-md border border-[#cfc4b2] bg-white pr-3 focus-within:ring-2 focus-within:ring-[#bb623e]"><Input aria-label={`${field.label}${field.unit ? ` (${field.unit})` : ''}`} type="number" min={field.min ?? 0} step={field.step ?? 'any'} value={values[field.key]} onChange={event => update(field.key, event.target.value)} className="h-11 border-0 bg-transparent focus-visible:ring-0"/>{field.unit && <span className="shrink-0 text-xs text-stone-500">{field.unit}</span>}</span>}</label>)}<Button type="button" variant="outline" onClick={() => { setValues(toolDefaults[tool.id]); setCopied(false); }} className="border-[#cfc4b2]"><X className="h-4 w-4"/>Limpar valores</Button></div>
        <div aria-live="polite" aria-atomic="true" className="rounded-2xl bg-[#31453b] p-5 text-[#f5f0e6]"><p className="text-xs font-bold uppercase tracking-widest text-[#d4a476]">{regionalToolResultKind(tool.id) === 'conversion' ? 'Conversão matemática' : 'Estimativa com seus dados'}</p>{result.error ? <p className="mt-4 rounded-lg bg-white/10 p-3 text-sm" role="alert">{result.error}</p> : result.value && <><h3 className="mt-2 font-serif text-xl font-bold">{result.value.title}</h3><ul className="mt-4 space-y-2">{result.value.lines.map((line, index) => <li key={index} className={`rounded-lg px-3 py-2 ${index === 0 ? 'bg-white/10 text-lg font-bold' : 'text-sm text-[#e7e9e2]'}`}>{line}</li>)}</ul><p className="mt-4 text-xs leading-5 text-[#dbe0d8]">{result.value.note}</p><Button type="button" variant="outline" onClick={copyResult} className="mt-4 border-white/40 bg-transparent text-white hover:bg-white/10 hover:text-white"><Copy className="h-4 w-4"/>{copied ? <><Check className="h-4 w-4"/>Copiado</> : 'Copiar resultado'}</Button>{copyError && <p className="mt-2 text-xs text-[#f1d6c1]" role="status">{copyError}</p>}</>}</div>
    </div>;
}
