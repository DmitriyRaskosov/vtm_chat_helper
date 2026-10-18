<?php

namespace App\Http\Controllers;

use App\Chronicle\Exceptions\InvitationUnavailableException;
use App\Chronicle\InvitationService;
use App\Enums\ChronicleRole;
use App\Http\Requests\StoreChronicleInvitationRequest;
use App\Http\Resources\ChronicleInvitationResource;
use App\Models\Chronicle;
use App\Models\ChronicleInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class ChronicleInvitationController extends Controller
{
    public function __construct(
        private InvitationService $invitations,
    ) {}

    public function store(StoreChronicleInvitationRequest $request, Chronicle $chronicle): JsonResponse
    {
        $this->authorize('createInvitation', $chronicle);

        $maxUses = $request->validated('max_uses');

        $created = $this->invitations->create(
            $request->user(),
            $chronicle,
            ChronicleRole::from((string) $request->validated('role')),
            Carbon::parse((string) $request->validated('expires_at')),
            $maxUses === null ? null : (int) $maxUses,
        );

        $invitation = (new ChronicleInvitationResource($created->invitation))->toArray($request);

        return response()->json([
            'invitation' => [
                'id' => $invitation['id'],
                'token' => $created->token,
                'expires_at' => $invitation['expires_at'],
                'max_uses' => $invitation['max_uses'],
                'role' => $invitation['role'],
            ],
        ], 201);
    }

    public function destroy(Request $request, Chronicle $chronicle, ChronicleInvitation $invitation): Response
    {
        $this->authorize('revokeInvitation', $chronicle);
        $this->invitations->revoke($chronicle, $invitation);

        return response()->noContent();
    }

    public function accept(Request $request, string $token): JsonResponse
    {
        try {
            $membership = $this->invitations->accept($request->user(), $token);
        } catch (InvitationUnavailableException $exception) {
            $payload = ['message' => $exception->getMessage()];

            if ($exception->chronicleId() !== null) {
                $payload = [
                    'chronicle_id' => $exception->chronicleId(),
                    'message' => $exception->getMessage(),
                ];
            }

            return response()->json($payload, 409);
        }

        return response()->json([
            'chronicle_id' => (int) $membership->chronicle_id,
            'role' => $membership->role->value,
            'joined_at' => $membership->joined_at->toIso8601String(),
        ]);
    }
}
