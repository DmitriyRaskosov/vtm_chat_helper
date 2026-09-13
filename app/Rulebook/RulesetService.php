<?php

namespace App\Rulebook;

use App\Enums\RulesetStatus;
use App\Models\Ruleset;
use InvalidArgumentException;

class RulesetService
{
    public function create(
        string $name,
        string $edition,
        string $language,
        RulesetStatus $status = RulesetStatus::Published,
        ?string $description = null,
    ): Ruleset {
        $name = trim($name);
        $edition = trim($edition);
        $language = trim($language);
        $description = $description !== null ? trim($description) : null;

        if ($name === '' || $edition === '' || $language === '') {
            throw new InvalidArgumentException('Ruleset name, edition and language are required.');
        }

        return Ruleset::query()->create([
            'name' => $name,
            'edition' => $edition,
            'language' => $language,
            'status' => $status,
            'description' => $description === '' ? null : $description,
        ]);
    }
}
