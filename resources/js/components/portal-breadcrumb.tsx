import { Link, usePage } from '@inertiajs/react';
import JsonLd from '@/components/json-ld';
import { absoluteUrl, canonicalPath } from '@/lib/seo';

export type PortalBreadcrumbItem = { label: string; href?: string };

export default function PortalBreadcrumb({ items }: { items: PortalBreadcrumbItem[] }) {
    const { props, url } = usePage<{ appUrl?: string }>();
    const schema = {
        '@type': 'BreadcrumbList',
        // Intermediate labels without their own page are left out; the last item is the current page.
        itemListElement: items
            .map((item, index) => ({ name: item.label, href: index === items.length - 1 ? canonicalPath(url).path : item.href }))
            .filter((item): item is { name: string; href: string } => Boolean(item.href))
            .map((item, index) => ({ '@type': 'ListItem', position: index + 1, name: item.name, item: absoluteUrl(props.appUrl, item.href) })),
    };

    return (
        <>
        <JsonLd id="breadcrumb" schema={schema} />
        <nav aria-label="Navegação estrutural" className="mx-auto max-w-7xl px-5 py-4 text-sm text-[#625f55]">
            <ol className="flex flex-wrap items-center gap-2">
                {items.map((item, index) => {
                    const isCurrent = index === items.length - 1;

                    return (
                        <li key={`${item.label}-${index}`} className="flex items-center gap-2">
                            {index > 0 && <span aria-hidden="true" className="text-[#9c4e31]">/</span>}
                            {item.href && !isCurrent
                                ? <Link href={item.href} className="underline decoration-[#bb623e]/50 underline-offset-4 hover:text-[#9c4e31]">{item.label}</Link>
                                : <span aria-current={isCurrent ? 'page' : undefined} className={isCurrent ? 'font-semibold text-[#31453b]' : undefined}>{item.label}</span>}
                        </li>
                    );
                })}
            </ol>
        </nav>
        </>
    );
}
