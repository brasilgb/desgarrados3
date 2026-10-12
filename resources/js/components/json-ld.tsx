import { Head } from '@inertiajs/react';

type Schema = Record<string, unknown>;

/** Drops empty values so schemas never carry placeholders for unknown data. */
function compact(value: unknown): unknown {
    if (Array.isArray(value)) return value.map(compact).filter(item => item !== undefined);
    if (value && typeof value === 'object') {
        const entries = Object.entries(value).map(([key, item]) => [key, compact(item)] as const).filter(([, item]) => item !== undefined && item !== null && item !== '' && !(Array.isArray(item) && item.length === 0));
        return entries.length ? Object.fromEntries(entries) : undefined;
    }
    return value;
}

/** Structured data (schema.org) rendered in the document head, also during SSR. */
export default function JsonLd({ id, schema }: { id: string; schema: Schema }) {
    // Escaping "<" keeps the JSON from closing the script element early.
    const json = JSON.stringify(compact({ '@context': 'https://schema.org', ...schema })).replace(/</g, '\\u003c');

    return <Head><script head-key={`ld-${id}`} type="application/ld+json">{json}</script></Head>;
}
