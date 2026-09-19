<?php

namespace App\Filament\Resources\Districts;

use App\Filament\Resources\Districts\Pages\ListDistricts;
use App\Filament\Resources\Districts\Schemas\DistrictForm;
use App\Filament\Resources\Districts\Tables\DistrictsTable;
use App\Models\District;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The country's 64 districts, so a stop points at one instead of spelling it.
 *
 * It exists because the district was free text until 2026-09-19: "Cumilla",
 * "cumilla" and "Comilla" were three different places as far as anything
 * could tell, and an admin had to remember how the last town was filed.
 *
 * Nothing about a journey touches this. A ride carries no district, no
 * search reads one, and the label exists only so two same-named towns read
 * apart in a picker - so unlike a stop or a corridor, nothing here can
 * quietly break a search.
 *
 * Every page is on this one screen: there are 64 rows, they are named and
 * nothing else, and a district has no relations worth their own page.
 */
class DistrictResource extends Resource
{
    protected static ?string $model = District::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'district';

    protected static ?string $pluralModelLabel = 'districts';

    protected static ?string $navigationLabel = 'Districts';

    protected static string|UnitEnum|null $navigationGroup = 'Network';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return DistrictForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DistrictsTable::configure($table);
    }

    /**
     * Whether any town is filed under this district.
     *
     * `stops.district_id` is `nullOnDelete`, so deleting one would not fail -
     * it would silently blank the district on every town under it, which is
     * worse. Retiring with `is_active` is what an unwanted district gets.
     */
    public static function holdsStops(District $district): bool
    {
        return $district->stops()->exists();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDistricts::route('/'),
        ];
    }
}
