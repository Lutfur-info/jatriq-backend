<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\EmergencyContact\StoreEmergencyContactRequest;
use App\Http\Requests\Api\EmergencyContact\UpdateEmergencyContactRequest;
use App\Http\Resources\EmergencyContactResource;
use App\Models\EmergencyContact;
use App\Models\User;
use App\Services\EmergencyContactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The people a driver or passenger wants called if a ride goes wrong.
 *
 * Exactly one contact is flagged primary while the list is not empty; the
 * service maintains that, so a client never has to.
 */
class EmergencyContactController extends Controller
{
    public function __construct(private EmergencyContactService $contacts) {}

    /**
     * List the user's emergency contacts, primary first.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'message' => 'Emergency contacts.',
            'data' => [
                'contacts' => EmergencyContactResource::collection($this->contacts->list($user)),
                'remaining_slots' => $this->contacts->remainingSlots($user),
            ],
        ]);
    }

    /**
     * Save a new emergency contact.
     */
    public function store(StoreEmergencyContactRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $contact = $this->contacts->create(
            $user,
            $request->attributesForContact(),
            $request->isPrimary(),
        );

        return response()->json([
            'message' => 'Emergency contact saved.',
            'data' => new EmergencyContactResource($contact),
        ], Response::HTTP_CREATED);
    }

    /**
     * Change an existing emergency contact.
     */
    public function update(UpdateEmergencyContactRequest $request, EmergencyContact $emergencyContact): JsonResponse
    {
        $this->authorizeContact($request, $emergencyContact);

        $contact = $this->contacts->update(
            $emergencyContact,
            $request->attributesForContact(),
            $request->isPrimary(),
        );

        return response()->json([
            'message' => 'Emergency contact updated.',
            'data' => new EmergencyContactResource($contact),
        ]);
    }

    /**
     * Remove an emergency contact.
     */
    public function destroy(Request $request, EmergencyContact $emergencyContact): JsonResponse
    {
        $this->authorizeContact($request, $emergencyContact);

        $this->contacts->delete($emergencyContact);

        return response()->json(['message' => 'Emergency contact removed.']);
    }

    /**
     * Refuse to touch somebody else's contact.
     *
     * A 404 rather than a 403: whether a given id exists is not the caller's
     * business when the row is not theirs.
     */
    private function authorizeContact(Request $request, EmergencyContact $contact): void
    {
        abort_unless($contact->user_id === $request->user()?->getAuthIdentifier(), 404);
    }
}
