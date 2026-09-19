<?php

namespace App\Http\Requests\Api\Driver;

use App\enum\CabinClass;
use App\enum\DocumentType;
use App\enum\VehicleModel;
use App\Http\Requests\Concerns\BuildsDocumentFileRules;
use App\Models\User;
use App\Repositories\Contracts\VehicleRepository;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * The vehicle details a driver registers, and optionally their licence.
 *
 * @phpstan-import-type VehicleAttributes from VehicleRepository
 */
class StoreVehicleRequest extends FormRequest
{
    use BuildsDocumentFileRules;

    /**
     * The repository answers "which vehicle is already mine", so the unique
     * rule can skip it without the request running a query of its own.
     */
    public function __construct(private VehicleRepository $vehicles)
    {
        parent::__construct();
    }

    /**
     * Fold the plate down to one spelling before anything looks at it.
     *
     * Plates are read off a metal sign and typed by hand, so "dhaka
     * metro-ga-11-2233" and "DHAKA METRO-GA-11-2233" arrive for the same
     * vehicle and must collide on the unique index.
     */
    protected function prepareForValidation(): void
    {
        $number = $this->input('registration_number');

        if (is_string($number)) {
            $this->merge([
                'registration_number' => mb_strtoupper(
                    (string) preg_replace('/\s+/u', ' ', trim($number)),
                ),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        $unique = Rule::unique('vehicles', 'registration_number');
        $existing = $this->vehicles->forUser($user);

        if ($existing !== null) {
            // Re-submitting your own plate is an edit, not a clash.
            $unique->ignoreModel($existing);
        }

        return [
            'registration_number' => [
                'required',
                'string',
                'max:'.(int) config('vehicles.registration_number.max'),
                $unique,
            ],
            'model' => ['required', 'string', Rule::in(VehicleModel::names())],
            'cabin_class' => ['required', 'string', Rule::in(CabinClass::names())],
            'seats' => [
                'required',
                'integer',
                'min:'.(int) config('vehicles.seats.min'),
                'max:'.(int) config('vehicles.seats.max'),
            ],
            'driving_licence' => ['nullable', $this->fileRuleFor(DocumentType::DrivingLicence)],
        ];
    }

    /**
     * The vehicle details, with both enums already resolved.
     *
     * @return VehicleAttributes
     */
    public function attributesForVehicle(): array
    {
        return [
            'registration_number' => $this->string('registration_number')->toString(),
            'model' => VehicleModel::fromName($this->string('model')->toString()),
            'cabin_class' => CabinClass::fromName($this->string('cabin_class')->toString()),
            'seats' => $this->integer('seats'),
        ];
    }

    /**
     * The licence scan, when the driver attached one.
     */
    public function licence(): ?UploadedFile
    {
        $file = $this->file('driving_licence');

        return $file instanceof UploadedFile ? $file : null;
    }

    /**
     * Human readable field names for the validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'registration_number' => 'vehicle number',
            'model' => 'vehicle type',
            'cabin_class' => 'air conditioning',
            'seats' => 'passenger seats',
            'driving_licence' => DocumentType::DrivingLicence->label(),
        ];
    }

    /**
     * Custom messages for the rules whose defaults read poorly here.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'registration_number.unique' => 'This vehicle number is already registered.',
            'model.in' => 'Choose one of: '.implode(', ', VehicleModel::names()).'.',
            'cabin_class.in' => 'Choose one of: '.implode(', ', CabinClass::names()).'.',
        ];
    }
}
