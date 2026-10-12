import { Head, usePage } from '@inertiajs/react';
import { DEFAULT_SOCIAL_IMAGE, absoluteUrl, canonicalPath, metaDescription } from '@/lib/seo';

type Props = {
    title: string;
    description?: string | null;
    /** Overrides the canonical path derived from the current URL. */
    path?: string;
    image?: string | null;
    imageAlt?: string | null;
    type?: 'website' | 'article';
    article?: { publishedTime?: string | null; modifiedTime?: string | null; section?: string | null };
    noindex?: boolean;
};

/** Page-level metadata: title, description, canonical address, robots, Open Graph and Twitter tags. */
export default function PortalSeo({ title, description, path, image, imageAlt, type = 'website', article, noindex = false }: Props) {
    const { props, url: pageUrl } = usePage<{ appUrl?: string; portalSettings?: Record<string, string | null> }>();
    const { appUrl, portalSettings } = props;
    const canonical = canonicalPath(path ?? pageUrl);
    const url = absoluteUrl(appUrl, canonical.path);
    const siteName = portalSettings?.portal_name || 'Desgarrados';
    const summary = metaDescription(description) ?? metaDescription(portalSettings?.portal_description);
    const socialImage = absoluteUrl(appUrl, image || (portalSettings?.share_image_path ? `/storage/${portalSettings.share_image_path}` : DEFAULT_SOCIAL_IMAGE));
    const hideFromSearch = noindex || canonical.filtered;

    return <Head title={title}>
        {summary && <meta head-key="description" name="description" content={summary} />}
        <link head-key="canonical" rel="canonical" href={url} />
        {hideFromSearch && <meta head-key="robots" name="robots" content="noindex, follow" />}
        <meta head-key="og:type" property="og:type" content={type} />
        <meta head-key="og:site_name" property="og:site_name" content={siteName} />
        <meta head-key="og:title" property="og:title" content={title} />
        {summary && <meta head-key="og:description" property="og:description" content={summary} />}
        <meta head-key="og:url" property="og:url" content={url} />
        <meta head-key="og:locale" property="og:locale" content="pt_BR" />
        <meta head-key="og:image" property="og:image" content={socialImage} />
        {imageAlt && <meta head-key="og:image:alt" property="og:image:alt" content={imageAlt} />}
        {type === 'article' && article?.publishedTime && <meta head-key="article:published_time" property="article:published_time" content={article.publishedTime} />}
        {type === 'article' && article?.modifiedTime && <meta head-key="article:modified_time" property="article:modified_time" content={article.modifiedTime} />}
        {type === 'article' && article?.section && <meta head-key="article:section" property="article:section" content={article.section} />}
        <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
        <meta head-key="twitter:title" name="twitter:title" content={title} />
        {summary && <meta head-key="twitter:description" name="twitter:description" content={summary} />}
        <meta head-key="twitter:image" name="twitter:image" content={socialImage} />
    </Head>;
}
