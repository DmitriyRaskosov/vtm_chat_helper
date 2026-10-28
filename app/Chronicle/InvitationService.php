<?php

namespace App\Chronicle;

use App\Chronicle\Exceptions\InvitationNotFoundException;
use App\Chronicle\Exceptions\InvitationUnavailableException;
use App\Enums\ChronicleRole;
use App\Models\Chronicle;
use App\Models\ChronicleInvitation;
use App\Models\ChronicleMember;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

final class InvitationService
{
    public function create(
        User $user,
        Chronicle $chronicle,
        ChronicleRole $role,
        DateTimeInterface $expiresAt,
        ?int $maxUses,
    ): CreatedInvitation {
        $token = $this->plainToken();

        $invitation = ChronicleInvitation::query()->create([
            'chronicle_id' => $chronicle->id,
            'token_hash' => hash('sha256', $token),
            'role' => $role,
            'created_by' => $user->id,
            'expires_at' => $expiresAt,
            'max_uses' => $maxUses,
            'uses' => 0,
            'revoked_at' => null,
        ]);

        return new CreatedInvitation($invitation, $token);
    }

    public function revoke(Chronicle $chronicle, ChronicleInvitation $invitation): void
    {
        if ((int) $invitation->chronicle_id !== (int) $chronicle->id) {
            throw new InvitationNotFoundException;
        }

        $invitation->revoked_at = now();
        $invitation->save();
    }

    public function accept(User $user, string $token): ChronicleMember
    {
        return DB::transaction(function () use ($user, $token): ChronicleMember {
            $invitation = ChronicleInvitation::query()
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if ($invitation === null) {
                throw new InvitationNotFoundException;
            }

            $membership = ChronicleMember::query()
                ->where('chronicle_id', $invitation->chronicle_id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            $memberChronicleId = $membership !== null && $membership->left_at === null
                ? (int) $membership->chronicle_id
                : null;

            if ($invitation->expires_at->lt(now())) {
                throw new InvitationUnavailableException('Invitation has expired.');
            }

            if ($invitation->revoked_at !== null) {
                throw new InvitationUnavailableException(
                    'Invitation has been revoked.',
                    $memberChronicleId,
                );
            }

            if ($invitation->max_uses !== null && $invitation->uses >= $invitation->max_uses) {
                throw new InvitationUnavailableException('Invitation has reached its use limit.');
            }

            if ($memberChronicleId !== null) {
                throw new InvitationUnavailableException(
                    'You are already a member of this chronicle.',
                    $memberChronicleId,
                );
            }

            $invitation->uses = $invitation->uses + 1;
            $invitation->save();

            $joinedAt = now();

            if ($membership === null) {
                return ChronicleMember::query()->create([
                    'chronicle_id' => $invitation->chronicle_id,
                    'user_id' => $user->id,
                    'role' => $invitation->role,
                    'joined_at' => $joinedAt,
                    'left_at' => null,
                ]);
            }

            $membership->role = $invitation->role;
            $membership->joined_at = $joinedAt;
            $membership->left_at = null;
            $membership->save();

            return $membership;
        });
    }

    private function plainToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
