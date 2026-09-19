<?php

namespace App\Services;

use App\enum\DocumentType;
use App\Models\User;
use App\Models\UserDocument;
use App\Models\Vehicle;
use App\Repositories\Contracts\UserDocumentRepository;
use App\Repositories\Contracts\VehicleRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Keeps a driver's vehicle details and the licence that goes with them.
 *
 * The licence file is not stored on the vehicle: it is handed to
 * VerificationService like any other document, so it lands on the private
 * disk, replaces whatever it stood in for, and goes into the same admin
 * review queue. That is why the vehicle row itself carries no status.
 *
 * @phpstan-import-type VehicleAttributes from VehicleRepository
 */
class VehicleService
{
    public function __construct(
        private VehicleRepository $vehicles,
        private UserDocumentRepository $documents,
        private VerificationService $verification,
    ) {}

    /**
     * The driver's vehicle, if they have registered one.
     */
    public function forUser(User $user): ?Vehicle
    {
        return $this->vehicles->forUser($user);
    }

    /**
     * The driving licence the driver has on file, if any.
     */
    public function licenceFor(User $user): ?UserDocument
    {
        return $this->documents->findForUser($user, DocumentType::DrivingLicence);
    }

    /**
     * Save the vehicle details, together with a licence if one came along.
     *
     * @param  VehicleAttributes  $attributes
     */
    public function save(User $user, array $attributes, ?UploadedFile $licence = null): Vehicle
    {
        return DB::transaction(function () use ($user, $attributes, $licence): Vehicle {
            $vehicle = $this->vehicles->put($user, $attributes);

            if ($licence !== null) {
                // storeMany owns the replacement, the discarded file and the
                // badge refresh, so a licence uploaded here behaves exactly
                // like one uploaded to the verification endpoint.
                $this->verification->storeMany($user, [
                    DocumentType::DrivingLicence->name => $licence,
                ]);
            }

            return $vehicle;
        });
    }
}
