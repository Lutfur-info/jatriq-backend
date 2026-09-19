import { Head } from '@inertiajs/react';
import Paginator from '@/components/paginator';
import RideCard from '@/components/ride-card';
import RideSearchForm from '@/components/ride-search-form';
import PublicLayout from '@/layouts/public-layout';
import type { Paginated, Ride, RideSearch, TravelRoute } from '@/types';

type RideBoardProps = {
    rides: Paginated<Ride>;
    routes: TravelRoute[];
    filters: RideSearch;
    errors: Partial<Record<'from_stop_id' | 'to_stop_id', string>>;
};

/**
 * The front page: every upcoming ride somebody can still book.
 *
 * Soonest first, a page at a time, and narrowed to one journey when the
 * passenger names both ends of it. A ride qualifies for that search when it
 * runs the same corridor the same way and its own trip contains theirs - so
 * somebody boarding at Laksam for Dhaka is shown the vehicle that set out
 * from Sonaimuri, which still has Laksam ahead of it.
 */
export default function RideBoard({
    rides,
    routes,
    filters,
    errors,
}: RideBoardProps) {
    const searching = filters.from_stop_id !== null;
    const { from, to, total } = rides.meta;

    return (
        <PublicLayout
            masthead={
                <div className="space-y-5">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                            Upcoming rides
                        </h1>
                        <p className="mt-1 text-sm text-brand-100">
                            Seats going spare on intercity trips. Pick where you
                            are boarding and where you are getting off, and we
                            will show the vehicles already coming your way.
                        </p>
                    </div>

                    <RideSearchForm
                        key={`${filters.from_stop_id}-${filters.to_stop_id}`}
                        routes={routes}
                        filters={filters}
                        errors={errors}
                    />
                </div>
            }
        >
            <Head title="Upcoming rides" />

            {total === 0 ? (
                <Empty searching={searching} />
            ) : (
                <>
                    <p className="text-sm text-slate-600 dark:text-slate-400">
                        Showing {from}–{to} of {total}{' '}
                        {total === 1 ? 'ride' : 'rides'}
                        {searching ? ' on that journey' : ''}.
                    </p>

                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                        {rides.data.map((ride) => (
                            <RideCard key={ride.id} ride={ride} />
                        ))}
                    </div>

                    <Paginator page={rides} />
                </>
            )}
        </PublicLayout>
    );
}

/**
 * Nothing to show, and the two reasons for it read differently: an empty
 * search is a road nobody is running today, an empty board is a quiet day.
 */
function Empty({ searching }: { searching: boolean }) {
    return (
        <div className="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center dark:border-slate-700 dark:bg-slate-900">
            <p className="font-medium text-slate-900 dark:text-slate-100">
                {searching
                    ? 'No rides running that way right now.'
                    : 'No rides available right now.'}
            </p>
            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {searching
                    ? 'Try the journey the other way round, or a town further along the road.'
                    : 'Drivers publish seats a few days ahead. Check back shortly.'}
            </p>
        </div>
    );
}
