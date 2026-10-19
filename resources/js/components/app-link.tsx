import { Link } from '@inertiajs/react';
import { REACT_ROUTES } from '@/lib/react-pages';

/**
 * A link to a named route: within the app when its page is React, a
 * normal page load when it's still Blade (see lib/react-pages).
 */
export function AppLink({
    to,
    params,
    prefetch,
    ...props
}: Omit<React.AnchorHTMLAttributes<HTMLAnchorElement>, 'href'> & { to: string; params?: unknown; prefetch?: boolean }) {
    const href = route(to, params as never);
    if (REACT_ROUTES.has(to)) {
        return <Link href={href} prefetch={prefetch} {...(props as object)} />;
    }
    return <a href={href} {...props} />;
}
