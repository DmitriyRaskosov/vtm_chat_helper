<?php

namespace App\Chronicle;

use App\Models\ChronicleInvitation;

final readonly class CreatedInvitation
{
    public function __construct(
        public ChronicleInvitation $invitation,
        public string $token,
    ) {}
}
