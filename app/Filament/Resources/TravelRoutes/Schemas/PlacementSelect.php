<?php

namespace App\Filament\Resources\TravelRoutes\Schemas;

use App\Models\Stop;
use App\Models\TravelRoute;
use App\Services\TravelRouteService;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

/**
 * "Where on the road?" - how a position is chosen, in both directions.
 *
 * The default answer is a pair of neighbours, never a number, because a
 * sequence typed into every form is how a corridor gets renumbered by
 * accident and a renumber mis-positions every ride already published on it.
 * `TravelRouteService` turns the gap into a number, and the number is shown
 * in each label so an admin can still read the corridor's tens.
 *
 * "Set the number myself" is the escape hatch for somebody who knows the
 * road. It is checked the same way - inside the column, free on this corridor
 * - so it can skip the explanation without skipping the safety.
 */
class PlacementSelect
{
    /**
     * The two fields together: where on the road, and the number if the admin
     * would rather say it outright.
     *
     * @param  Closure(Get): ?TravelRoute  $corridor  Which road is being placed
     *                                                on. A closure because on
     *                                                the stop's side it is not
     *                                                chosen until the modal is
     *                                                half filled in.
     * @param  Stop|null  $moving  A town already on that road, when this is a
     *                             move rather than a first placement.
     * @return array<int, Select|TextInput>
     */
    public static function fields(Closure $corridor, ?Stop $moving = null): array
    {
        return [
            self::make($corridor, $moving),
            self::exactField($corridor, $moving),
        ];
    }

    /**
     * The placement a submitted modal means, in the form the service takes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function resolve(array $data): string
    {
        return $data['placement'] === 'exact'
            ? 'exact:'.$data['sequence']
            : (string) $data['placement'];
    }

    /**
     * @param  Closure(Get): ?TravelRoute  $corridor
     */
    public static function make(Closure $corridor, ?Stop $moving = null): Select
    {
        return Select::make('placement')
            ->label('Where on the road?')
            ->helperText('Counted outbound from Dhaka. Pick the two towns it sits between - the position is worked out from the gap.')
            ->required()
            ->native(false)
            // Live so the number field below can appear when it is asked for.
            ->live()
            ->options(function (Get $get) use ($corridor, $moving): array {
                $route = $corridor($get);

                if (! $route instanceof TravelRoute) {
                    return [];
                }

                $options = array_map(
                    fn (array $placement): string => $placement['sequence'] === null
                        ? "{$placement['label']} — no gap left"
                        : "{$placement['label']} — position {$placement['sequence']}",
                    self::placements($route, $moving),
                );

                $options['exact'] = 'Set the number myself…';

                return $options;
            })
            // Listed but unpickable, so a full gap is visible as something to
            // re-plan rather than quietly missing from the list.
            ->disableOptionWhen(function (string $value, Get $get) use ($corridor, $moving): bool {
                $route = $corridor($get);

                if (! $route instanceof TravelRoute) {
                    return true;
                }

                if ($value === 'exact') {
                    return false;
                }

                return (self::placements($route, $moving)[$value]['sequence'] ?? null) === null;
            })
            ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($corridor, $moving, $get): void {
                $route = $corridor($get);

                if (! $route instanceof TravelRoute || $value === 'exact') {
                    return;
                }

                if ((self::placements($route, $moving)[$value]['sequence'] ?? null) === null) {
                    $fail('There is no room left between those two towns. Widening the gap means renumbering the corridor, which would mis-position the rides already published on it. Renumber the road instead.');
                }
            });
    }

    /**
     * The number itself, for an admin who would rather not be asked.
     *
     * @param  Closure(Get): ?TravelRoute  $corridor
     */
    private static function exactField(Closure $corridor, ?Stop $moving = null): TextInput
    {
        return TextInput::make('sequence')
            ->label('Position')
            ->helperText('Outbound from Dhaka, so a lower number is nearer Dhaka. Leave room either side - the seeded roads go up in tens.')
            ->numeric()
            ->integer()
            ->minValue(1)
            // `travel_route_stop.sequence` is an unsigned smallint.
            ->maxValue(65535)
            ->visible(fn (Get $get): bool => $get('placement') === 'exact')
            ->required(fn (Get $get): bool => $get('placement') === 'exact')
            ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($corridor, $moving, $get): void {
                $route = $corridor($get);

                if (! $route instanceof TravelRoute || $get('placement') !== 'exact') {
                    return;
                }

                if (app(TravelRouteService::class)->sequenceFor($route, "exact:{$value}", $moving) === null) {
                    $fail('Another town on this corridor already sits at that position.');
                }
            });
    }

    /**
     * @return array<string, array{label: string, sequence: int|null}>
     */
    private static function placements(TravelRoute $route, ?Stop $moving): array
    {
        return app(TravelRouteService::class)->placementsOn($route, $moving);
    }
}
