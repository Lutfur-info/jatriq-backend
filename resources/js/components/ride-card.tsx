import { Link } from '@inertiajs/react';
import { formatDate, formatFare, formatSeats, formatTime } from '@/lib/format';
import { show } from '@/routes/rides';
import type { Ride } from '@/types';

/**
 * One bookable ride, as it appears on the board.
 *
 * It says nothing about the driver, because the server sends nothing about
 * the driver: this is a page anybody can read. The vehicle's plate is here
 * and that is a deliberate judgement - a plate is visible on the street.
 */
export default function RideCard({ ride }: { ride: Ride }) {
    return (
        <Link
            href={show(ride.id)}
            className="block rounded-xl bg-white p-5 shadow-lg ring-1 shadow-slate-900/10 ring-slate-900/5 transition duration-200 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-slate-900/15 hover:ring-brand-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 dark:bg-slate-900 dark:shadow-slate-950/40 dark:ring-white/10 dark:hover:shadow-slate-950/60 dark:hover:ring-brand-500"
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-950 dark:text-brand-200">
                    {ride.route?.name ?? 'Off the corridor network'}
                </span>

                <span
                    className={
                        ride.seats_available > 0
                            ? 'text-xs font-medium text-emerald-700 dark:text-emerald-400'
                            : 'text-xs font-medium text-slate-500 dark:text-slate-400'
                    }
                >
                    {formatSeats(ride.seats_available)}
                </span>
            </div>

            <div className="mt-3 flex items-baseline gap-2 text-lg font-semibold text-slate-900 dark:text-slate-50">
                <span>{ride.origin.label}</span>
                <span aria-label="to" className="text-slate-400">
                    →
                </span>
                <span>{ride.destination.label}</span>
            </div>

            <dl className="mt-4 grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                <Fact term="Leaves">
                    {formatDate(ride.departs_at)}
                    <span className="block text-slate-500 dark:text-slate-400">
                        {formatTime(ride.departs_at)}
                    </span>
                </Fact>

                <Fact term="Per seat">{formatFare(ride.seat_price)}</Fact>

                {ride.vehicle ? (
                    <Fact term="Vehicle">
                        {ride.vehicle.model_label}
                        <span className="block text-slate-500 dark:text-slate-400">
                            {ride.vehicle.cabin_class_label}
                        </span>
                    </Fact>
                ) : null}
            </dl>
        </Link>
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
