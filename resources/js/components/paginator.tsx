import { Link } from '@inertiajs/react';
import type { Paginated } from '@/types';

/**
 * The page links Laravel's paginator hands over.
 *
 * Each link's `url` already carries the search that produced it - the
 * paginator is built `withQueryString()` - so moving to page two keeps the
 * journey the passenger asked for.
 *
 * Laravel writes the previous and next labels as HTML entities, which is a
 * detail of its own Blade views; they are swapped for the characters here
 * rather than injected as markup.
 */
export default function Paginator<T>({ page }: { page: Paginated<T> }) {
    if (page.meta.last_page < 2) {
        return null;
    }

    return (
        <nav
            aria-label="Pagination"
            className="mt-8 flex flex-wrap items-center justify-center gap-1"
        >
            {page.meta.links.map((link) => {
                const label = link.label
                    .replace('&laquo;', '«')
                    .replace('&raquo;', '»')
                    .trim();

                if (link.url === null) {
                    return (
                        <span
                            key={label}
                            aria-hidden="true"
                            className="rounded-lg px-3 py-2 text-sm text-slate-400 dark:text-slate-600"
                        >
                            {label}
                        </span>
                    );
                }

                return (
                    <Link
                        key={label}
                        href={link.url}
                        aria-current={link.active ? 'page' : undefined}
                        className={
                            link.active
                                ? 'rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white'
                                : 'rounded-lg bg-white px-3 py-2 text-sm text-slate-700 ring-1 ring-slate-900/5 transition hover:bg-brand-50 dark:bg-slate-900 dark:text-slate-200 dark:ring-white/10 dark:hover:bg-slate-800'
                        }
                    >
                        {label}
                    </Link>
                );
            })}
        </nav>
    );
}
