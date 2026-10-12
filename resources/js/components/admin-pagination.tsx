import { Link } from '@inertiajs/react';

type PaginationLink = { url: string | null; label: string; active: boolean };

const isPrevious = (label: string) => /previous|anterior/i.test(label);
const isNext = (label: string) => /next|próxima/i.test(label);

function labelText(label: string): string {
    const plain = label.replace(/<[^>]*>/g, '').replace(/&amp;/g, '&').trim();
    if (isPrevious(plain)) return 'Anterior';
    if (isNext(plain)) return 'Próxima';
    return plain.replace('&laquo;', '‹').replace('&raquo;', '›');
}

export default function AdminPagination({ links, label = 'Paginação' }: { links: PaginationLink[]; label?: string }) {
    if (links.length < 2) return null;

    return <div className="mt-6 w-full overflow-hidden px-2 pb-6 sm:px-4"><nav aria-label={label} className="flex flex-wrap items-center justify-center gap-2">
        {links.map((link, index) => link.url ? <Link key={`${link.label}-${index}`} href={link.url} preserveScroll aria-label={isPrevious(link.label) ? 'Página anterior' : isNext(link.label) ? 'Próxima página' : `Página ${labelText(link.label)}`} aria-current={link.active ? 'page' : undefined} className={`inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border px-3 py-2 text-sm ${link.active ? 'border-primary bg-primary font-semibold text-primary-foreground' : 'border-border bg-card text-foreground hover:bg-accent hover:text-accent-foreground'}`}>{labelText(link.label)}</Link> : <span key={`${link.label}-${index}`} aria-disabled="true" className="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-border bg-muted px-3 py-2 text-sm text-muted-foreground">{labelText(link.label)}</span>)}
    </nav></div>;
}
