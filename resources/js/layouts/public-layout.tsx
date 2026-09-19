import { Link } from '@inertiajs/react';
import type { PropsWithChildren, ReactNode } from 'react';
import { home } from '@/routes';

type PublicLayoutProps = PropsWithChildren<{
    /** The band under the header, where a page states what it is. */
    masthead?: ReactNode;
}>;

/**
 * The chrome around every page a visitor with no account can read.
 *
 * There is no account menu in it, on purpose: nothing on this side of the
 * site is signed in, and booking a seat happens in the app. So the header
 * carries the product and one link home, and the footer says what the board
 * is for rather than listing pages that do not exist.
 *
 * The masthead band keeps the brand navy behind it, because a page's
 * masthead is written against it - `text-brand-100` on a dark ground.
 */
export default function PublicLayout({
    masthead,
    children,
}: PublicLayoutProps) {
    return (
        <div className="flex min-h-screen flex-col bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
            <a
                href="#main"
                className="sr-only rounded-lg bg-white px-4 py-2 text-sm font-semibold text-brand-700 focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50"
            >
                Skip to the rides
            </a>

            {/*
             * Solid, not translucent: it is the same navy the masthead band
             * starts on, so the two read as one piece of chrome rather than
             * a bar sitting on a slightly different blue.
             */}
            <header className="sticky top-0 z-40 bg-brand-700 text-white shadow-sm dark:bg-brand-950">
                <div className="mx-auto flex h-16 w-full max-w-5xl items-center justify-between gap-4 px-4 sm:px-6">
                    <Link
                        href={home.url()}
                        className="group flex items-center gap-3 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-white/70"
                    >
                        <BrandLogo className="px-3 py-1.5 shadow-sm transition group-hover:shadow-md" />

                        <span
                            aria-hidden="true"
                            className="hidden h-7 w-px bg-white/20 sm:block"
                        />

                        <span className="hidden text-sm text-brand-100 sm:block">
                            Intercity rides across Bangladesh
                        </span>
                    </Link>

                    <div className="flex items-center gap-2">
                        <span className="hidden items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs font-medium text-brand-50 ring-1 ring-white/15 md:inline-flex">
                            <span
                                aria-hidden="true"
                                className="size-1.5 rounded-full bg-emerald-400"
                            />
                            Live timetable
                        </span>

                        <Link
                            href={home.url()}
                            className="rounded-full px-3 py-1.5 text-sm font-medium text-brand-100 transition hover:bg-white/10 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-white/70"
                        >
                            All rides
                        </Link>
                    </div>
                </div>
            </header>

            {masthead ? (
                <div className="relative isolate overflow-hidden bg-gradient-to-b from-brand-700 to-brand-800 text-white dark:from-brand-950 dark:to-slate-950">
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-0 -z-10"
                    >
                        <div className="absolute -top-28 -right-20 size-72 rounded-full bg-brand-400/20 blur-3xl" />
                        <div className="absolute -bottom-32 left-1/4 size-72 rounded-full bg-sky-400/10 blur-3xl" />
                    </div>

                    <div className="mx-auto w-full max-w-5xl px-4 pt-8 pb-10 sm:px-6">
                        {masthead}
                    </div>
                </div>
            ) : null}

            <main
                id="main"
                className="mx-auto w-full max-w-5xl flex-1 px-4 py-8 sm:px-6"
            >
                {children}
            </main>

            <SiteFooter />
        </div>
    );
}

/**
 * What the board is, and the three things worth knowing before riding.
 *
 * Deliberately no page list: `/` and a ride's own page are the whole public
 * site, so the only link here is home. Everything else is a statement of
 * fact, not a promise of a page.
 */
function SiteFooter() {
    return (
        <footer className="mt-auto bg-white dark:bg-slate-900">
            <div
                aria-hidden="true"
                className="h-px bg-gradient-to-r from-transparent via-brand-300 to-transparent dark:via-brand-700"
            />

            <div className="mx-auto w-full max-w-5xl px-4 py-10 sm:px-6">
                <div className="grid gap-10 sm:grid-cols-2">
                    <div>
                        <BrandLogo className="-ml-2 p-2" height="h-11" />

                        <p className="mt-4 max-w-sm text-sm text-slate-600 dark:text-slate-400">
                            Seats going spare on intercity trips across
                            Bangladesh. This page is the timetable — seats are
                            booked in the Jatriq app, where your identity is
                            verified before you ride.
                        </p>
                    </div>

                    <div>
                        <h2 className="text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400">
                            Good to know
                        </h2>

                        <ul className="mt-4 space-y-3 text-sm text-slate-600 dark:text-slate-400">
                            <Note icon={<ShieldIcon />}>
                                Drivers and passengers are identity checked
                                before a seat changes hands.
                            </Note>
                            <Note icon={<TagIcon />}>
                                One flat fare per seat, whatever distance along
                                the road you ride.
                            </Note>
                            <Note icon={<ClockIcon />}>
                                Every departure is shown in Dhaka time (GMT+6).
                            </Note>
                        </ul>
                    </div>
                </div>

                <div className="mt-10 flex flex-col gap-2 border-t border-slate-200 pt-6 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:text-slate-400">
                    <p>© {new Date().getFullYear()} Jatriq</p>

                    <Link
                        href={home.url()}
                        className="font-medium text-brand-600 underline-offset-4 hover:underline dark:text-brand-300"
                    >
                        All upcoming rides
                    </Link>
                </div>
            </div>
        </footer>
    );
}

function Note({ icon, children }: PropsWithChildren<{ icon: ReactNode }>) {
    return (
        <li className="flex gap-3">
            <span
                aria-hidden="true"
                className="mt-0.5 text-brand-600 dark:text-brand-300"
            >
                {icon}
            </span>
            <span>{children}</span>
        </li>
    );
}

/**
 * The app's own logo, the same `logo_transparent.png` the Flutter client
 * shows, so the two surfaces are recognisably one product.
 *
 * It is drawn in navy and green on nothing, so it needs a light ground to
 * read: hence the white plate, which is invisible against the white footer
 * and does the work over the navy header and in dark mode.
 */
function BrandLogo({
    className,
    height = 'h-9',
}: {
    /** The white plate: its padding, and anything it sits inside. */
    className: string;
    /** How tall the lockup is drawn. */
    height?: string;
}) {
    return (
        <span
            className={`inline-flex shrink-0 items-center rounded-xl bg-white ${className}`}
        >
            <img
                src="/images/jatriq-logo.png"
                alt="Jatriq — ride, share, travel"
                width={480}
                height={203}
                className={`${height} w-auto`}
            />
        </span>
    );
}

function ShieldIcon() {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.75}
            strokeLinecap="round"
            strokeLinejoin="round"
            className="size-4"
        >
            <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67 0C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" />
            <path d="m9 12 2 2 4-4" />
        </svg>
    );
}

function TagIcon() {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.75}
            strokeLinecap="round"
            strokeLinejoin="round"
            className="size-4"
        >
            <path d="M12.59 2.59A2 2 0 0 0 11.17 2H4a2 2 0 0 0-2 2v7.17a2 2 0 0 0 .59 1.41l8.7 8.71a2.43 2.43 0 0 0 3.42 0l6.58-6.59a2.43 2.43 0 0 0 0-3.41z" />
            <path d="M7.5 7.5h.01" />
        </svg>
    );
}

function ClockIcon() {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.75}
            strokeLinecap="round"
            strokeLinejoin="round"
            className="size-4"
        >
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5l3 2" />
        </svg>
    );
}
