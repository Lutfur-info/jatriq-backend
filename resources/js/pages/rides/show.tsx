import { Head, Link } from '@inertiajs/react';
import PublicLayout from '@/layouts/public-layout';
import { formatDate, formatFare, formatTime } from '@/lib/format';
import { home } from '@/routes';
import type { Ride, RideEnd } from '@/types';

/**
 * One ride in full.
 *
 * Reachable while the ride has not left, whether or not it still has a seat:
 * a full ride has dropped off the board, but somebody following a link is
 * owed "fully booked" rather than being told the trip never existed. A ride
 * that has departed is a 404 and never reaches this page.
 *
 * There is nothing here about the driver, and that is the rule rather than an
 * omission - the page is public, and a name and a number are not.
 */
export default function RideDetail({ ride }: { ride: Ride }) {
    const full = ride.seats_available < 1;

    return (
        <PublicLayout
            masthead={
                <div>
                    <Link
                        href={home.url()}
                        className="text-sm text-brand-100 underline underline-offset-4 hover:text-white"
                    >
                        ← All upcoming rides
                    </Link>

                    <h1 className="mt-4 text-2xl font-semibold tracking-tight sm:text-3xl">
                        {ride.origin.label}{' '}
                        <span className="text-brand-200">→</span>{' '}
                        {ride.destination.label}
                    </h1>

                    <p className="mt-1 text-sm text-brand-100">
                        {ride.route?.name ?? 'Off the corridor network'} ·
                        leaves {formatDate(ride.departs_at)} at{' '}
                        {formatTime(ride.departs_at)}
                    </p>
                </div>
            }
        >
            <Head title={`${ride.origin.label} to ${ride.destination.label}`} />

            {full ? (
                <p className="mb-6 rounded-xl bg-amber-50 p-4 text-sm font-medium text-amber-900 ring-1 ring-amber-200 dark:bg-amber-950 dark:text-amber-100 dark:ring-amber-900">
                    Every seat on this ride is taken.
                </p>
            ) : null}

            <div className="grid gap-6 sm:grid-cols-2">
                <Panel title="The trip">
                    <Fact term="Departs">
                        {formatDate(ride.departs_at)},{' '}
                        {formatTime(ride.departs_at)}
                    </Fact>
                    <Fact term="Fare per seat">
                        {formatFare(ride.seat_price)}
                        <span className="block text-sm font-normal text-slate-500 dark:text-slate-400">
                            The same whatever distance you ride.
                        </span>
                    </Fact>
                    <Fact term="Seats">
                        {ride.seats_available} of {ride.seats_offered} free
                        <span className="block text-sm font-normal text-slate-500 dark:text-slate-400">
                            {ride.seats_booked}{' '}
                            {ride.seats_booked === 1 ? 'seat is' : 'seats are'}{' '}
                            already booked.
                        </span>
                    </Fact>
                </Panel>

                <Panel title="The vehicle">
                    {ride.vehicle ? (
                        <>
                            <Fact term="Model">{ride.vehicle.model_label}</Fact>
                            <Fact term="Cabin">
                                {ride.vehicle.cabin_class_label}
                            </Fact>
                            <Fact term="Registration">
                                {ride.vehicle.registration_number}
                            </Fact>
                        </>
                    ) : (
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            Not published for this ride.
                        </p>
                    )}
                </Panel>

                <Panel title="Pick-up">
                    <Place end={ride.origin} />
                </Panel>

                <Panel title="Drop-off">
                    <Place end={ride.destination} />
                </Panel>
            </div>

            <p className="mt-6 text-sm text-slate-600 dark:text-slate-400">
                Seats are booked in the Jatriq app, where your identity is
                verified before you ride.
            </p>
        </PublicLayout>
    );
}

/**
 * One end of the trip, as the ride recorded it when it was published.
 *
 * The town's name and nothing else. There was a link out to Google Maps here
 * until 2026-09-18, built from coordinates the stop carried; the coordinates
 * were dropped from the network, so the link went with them.
 */
function Place({ end }: { end: RideEnd }) {
    return <Fact term="Stop">{end.label}</Fact>;
}

function Panel({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <section className="rounded-xl bg-white p-5 shadow-lg ring-1 shadow-slate-900/10 ring-slate-900/5 dark:bg-slate-900 dark:shadow-slate-950/40 dark:ring-white/10">
            <h2 className="text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400">
                {title}
            </h2>
            <dl className="mt-3 space-y-3">{children}</dl>
        </section>
    );
}

function Fact({ term, children }: { term: string; children: React.ReactNode }) {
    return (
        <div>
            <dt className="text-xs tracking-wide text-slate-500 uppercase dark:text-slate-400">
                {term}
            </dt>
            <dd className="mt-0.5 font-medium text-slate-900 dark:text-slate-100">
                {children}
            </dd>
        </div>
    );
}
