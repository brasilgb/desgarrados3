import type { ImgHTMLAttributes } from 'react';

export default function AppLogoIcon(props: ImgHTMLAttributes<HTMLImageElement>) {
    return <img {...props} src="/images/brand/logo-symbol-96.png" alt="" aria-hidden="true" />;
}
