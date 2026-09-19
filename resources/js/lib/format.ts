/**
 * How a ride reads on the page.
 *
 * The application stores and serves every timestamp in UTC, but the product
 * is Bangladesh only and a departure time means nothing to a passenger in any
 * other zone - so the board always renders in Asia/Dhaka rather than in
 * whatever zone the browser happens to sit in.
 */
const TIMEZONE = 'Asia/Dhaka';

const departureDate = new Intl.DateTimeFormat('en-GB', {
    timeZone: TIMEZONE,
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});

const departureTime = new Intl.DateTimeFormat('en-GB', {
    timeZone: TIMEZONE,
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
});

const amount = new Intl.NumberFormat('en-US', {
    maximumFractionDigits: 2,
});

/**
 * "Mon, 15 Sep 2026" for a departure.
 */
export function formatDate(iso: string): string {
    return departureDate.format(new Date(iso));
}

/**
 * "7:30 am" for a departure.
 */
export function formatTime(iso: string): string {
    return departureTime.format(new Date(iso));
}

/**
 * A fare, as taka.
 *
 * The server sends a fixed-point string precisely so the amount is never
 * handled as a binary float; it is parsed here only to group the thousands,
 * and the trailing `.00` most fares carry is dropped.
 */
export function formatFare(seatPrice: string): string {
    return `৳${amount.format(Number(seatPrice))}`;
}

/**
 * What the card says about the seats still going.
 */
export function formatSeats(available: number): string {
    if (available < 1) {
        return 'Fully booked';
    }

    return available === 1 ? '1 seat left' : `${available} seats left`;
}
