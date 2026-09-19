/**
 * The shapes the public ride board is served, mirroring the Eloquent
 * resources behind it: `RideResource`, `TravelRouteResource`, `StopResource`
 * and `VehicleResource`.
 *
 * Nothing here describes a driver, and that is deliberate - the board is a
 * page anybody on the internet can read, so the server never sends one.
 */

export type Stop = {
    id: number;
    name: string;
    /** The district's name. A row on the server since 2026-09-19, a string here. */
    district: string | null;
    /** Where the town falls on the corridor it was read through. */
    sequence: number | null;
};

export type TravelRoute = {
    id: number;
    name: string;
    slug: string;
    /** Present only where the corridor was loaded with its stops. */
    stops?: Stop[];
};

/**
 * One end of a trip: the ride's own snapshot of the stop it was published
 * against, so a renamed town never rewrites a trip already agreed to.
 *
 * Every field but the label is null on a ride published before corridors
 * existed; such a ride still lists, and can never match a search.
 */
export type RideEnd = {
    stop_id: number | null;
    label: string;
    sequence: number | null;
};

export type Vehicle = {
    id: number;
    registration_number: string;
    model: string;
    model_label: string;
    cabin_class: string;
    cabin_class_label: string;
    seats: number;
};

export type Ride = {
    id: number;
    route: TravelRoute | null;
    origin: RideEnd;
    destination: RideEnd;
    /** ISO 8601, in UTC. Rendered in Asia/Dhaka - see `lib/format`. */
    departs_at: string;
    /** Fixed point, as a string: a taka amount is never a binary float. */
    seat_price: string;
    seats_offered: number;
    seats_booked: number;
    seats_available: number;
    vehicle: Vehicle | null;
    created_at: string;
    updated_at: string;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

/**
 * A Laravel resource collection wrapping a length-aware paginator.
 */
export type Paginated<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        links: PaginationLink[];
        path: string;
        per_page: number;
        to: number | null;
        total: number;
    };
};

/**
 * The journey the board was asked for, echoed back so the pickers come back
 * showing it. Both ends or neither: one end alone does not say which way.
 */
export type RideSearch = {
    from_stop_id: number | null;
    to_stop_id: number | null;
};
