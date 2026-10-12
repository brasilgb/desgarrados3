type Props = {
    webpSrc: string;
    fallbackSrc?: string;
};

export default function PortalHeroImage({ webpSrc, fallbackSrc }: Props) {
    return (
        <img
            aria-hidden="true"
            className="portal-hero-image"
            src={webpSrc}
            data-fallback-src={fallbackSrc}
            onError={event => {
                const image = event.currentTarget;
                const fallback = image.dataset.fallbackSrc;

                if (fallback && image.dataset.fallbackAttempted !== 'true') {
                    image.dataset.fallbackAttempted = 'true';
                    image.src = fallback;
                }
            }}
            alt=""
            fetchPriority="high"
            decoding="async"
        />
    );
}
