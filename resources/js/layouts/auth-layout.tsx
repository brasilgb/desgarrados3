import { Head } from '@inertiajs/react';
import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';
import { Toaster } from '@/components/ui/sonner';

export default function AuthLayout({
    title = '',
    description = '',
    children,
}: {
    title?: string;
    description?: string;
    children: React.ReactNode;
}) {
    return (
        <AuthLayoutTemplate title={title} description={description}>
            <Head><meta head-key="robots" name="robots" content="noindex, nofollow" /></Head>
            {children}
            <Toaster />
        </AuthLayoutTemplate>
    );
}
