import { router } from '@inertiajs/react';
import { useState } from 'react';
import { home } from '@/routes';
import type { RideSearch, Stop, TravelRoute } from '@/types';

type RideSearchFormProps = {
    /** The corridors, each with its stops - what the pickers are built from. */
    routes: TravelRoute[];
    filters: RideSearch;
    errors: Partial<Record<'from_stop_id' | 'to_stop_id', string>>;
};

/**
 * Every town on the network, once, in alphabetical order.
 *
 * A stop belongs to every corridor it is on - Cumilla is on three of them,
 * because the road out of Dhaka is the same one for all three - so the
 * corridors are flattened and deduplicated by id rather than listed as
 * groups, which would offer the same town three times over.
 *
 * Alphabetical is right here and wrong inside a corridor: the sequence a
 * corridor lists its stops in is what makes one town "before" another on the
 * road, and the search relies on it. Nothing in this picker does.
 */
function townsOn(routes: TravelRoute[]): Stop[] {
    const towns = new Map<number, Stop>();

    for (const route of routes) {
        for (const stop of route.stops ?? []) {
            towns.set(stop.id, stop);
        }
    }

    return [...towns.values()].sort((one, other) =>
        one.name.localeCompare(other.name),
    );
}

function labelFor(stop: Stop): string {
    return stop.district && stop.district !== stop.name
        ? `${stop.name}, ${stop.district}`
        : stop.name;
}

/**
 * "Where are you boarding, and where are you getting off?"
 *
 * Both ends or neither - one end alone does not say which way the passenger
 * is going, and a corridor read backwards is a different set of rides. The
 * server insists on the same thing, so a half-filled form comes back with an
 * error rather than a silently wrong list.
 */
export default function RideSearchForm({
    routes,
    filters,
    errors,
}: RideSearchFormProps) {
    const [from, setFrom] = useState(filters.from_stop_id?.toString() ?? '');
    const [to, setTo] = useState(filters.to_stop_id?.toString() ?? '');

    const towns = townsOn(routes);
    const searching = filters.from_stop_id !== null;

    function search(event: React.FormEvent) {
        event.preventDefault();

        router.get(
            home.url(),
            {
                ...(from === '' ? {} : { from_stop_id: from }),
                ...(to === '' ? {} : { to_stop_id: to }),
            },
            { preserveScroll: true },
        );
    }

    return (
        <form
            onSubmit={search}
            className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5 sm:p-5 dark:bg-slate-900 dark:ring-white/10"
        >
            <div className="grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                <Picker
                    id="from_stop_id"
                    label="Boarding at"
                    value={from}
                    onChange={setFrom}
                    towns={towns}
                    error={errors.from_stop_id}
                />

                <Picker
                    id="to_stop_id"
                    label="Getting off at"
                    value={to}
                    onChange={setTo}
                    towns={towns}
                    error={errors.to_stop_id}
                />

                <button
                    type="submit"
                    className="h-10 rounded-lg bg-brand-600 px-5 text-sm font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900"
                >
                    Search rides
                </button>
            </div>

            {searching ? (
                <p className="mt-3 text-sm">
                    <button
                        type="button"
                        onClick={() => router.get(home.url())}
                        className="text-brand-600 underline underline-offset-4 hover:text-brand-700 dark:text-brand-300"
                    >
                        Show every upcoming ride instead
                    </button>
                </p>
            ) : null}
        </form>
    );
}

type PickerProps = {
    id: 'from_stop_id' | 'to_stop_id';
    label: string;
    value: string;
    onChange: (value: string) => void;
    towns: Stop[];
    error?: string;
};

function Picker({ id, label, value, onChange, towns, error }: PickerProps) {
    return (
        <div>
            <label
                htmlFor={id}
                className="block text-sm font-medium text-slate-700 dark:text-slate-300"
            >
                {label}
            </label>

            <select
                id={id}
                name={id}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                aria-invalid={error ? true : undefined}
                aria-describedby={error ? `${id}-error` : undefined}
                className="mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none aria-invalid:border-red-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
            >
                <option value="">Anywhere</option>
                {towns.map((town) => (
                    <option key={town.id} value={town.id}>
                        {labelFor(town)}
                    </option>
                ))}
            </select>

            {error ? (
                <p
                    id={`${id}-error`}
                    className="mt-1 text-sm text-red-600 dark:text-red-400"
                >
                    {error}
                </p>
            ) : null}
        </div>
    );
}
