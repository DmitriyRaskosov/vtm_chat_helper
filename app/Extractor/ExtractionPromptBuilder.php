<?php

namespace App\Extractor;

use App\Enums\CharacterMemoryNodeType;
use App\Enums\WorldEntityType;

class ExtractionPromptBuilder
{
    /**
     * @param  list<string>  $catalogLines
     * @param  list<string>  $relationKeys
     * @return list<array{role: string, content: string}>
     */
    public function build(string $sliceText, array $catalogLines, array $relationKeys): array
    {
        $entityKinds = implode(', ', array_map(
            fn (WorldEntityType $type): string => $type->value,
            WorldEntityType::cases(),
        ));
        $relationKeyList = $relationKeys === [] ? '(none)' : implode(', ', $relationKeys);
        $catalog = $catalogLines === []
            ? '(empty — no known entities yet)'
            : implode("\n", $catalogLines);

        $system = <<<TEXT
/no_think
You extract structured graph candidates from chronicle text. Reply with JSON only — no markdown fences, no commentary.

Schema:
{
  "mentions": [{ "name": "string", "kind": "one of: {$entityKinds}" }],
  "relations": [{ "source": "string", "target": "string", "key": "one of enabled relation keys" }],
  "events": [{ "title": "string", "summary": "string", "participants": ["string"] }],
  "memories": [{ "character": "string", "text": "string", "node_type": "event|fact|belief|rumor" }]
}

Rules:
- Use entity names as plain text only. Never output numeric entity IDs.
- "kind" must be one of: {$entityKinds}.
- "key" must be one of: {$relationKeyList}.
- Prefer names that appear in the source text or catalog aliases.
- Write mention names in nominative case (именительный падеж).
- Do not propose job titles or offices without a personal name (e.g. not "Примоген" alone).
- Do not create nodes for bloodlines, generations, or textbook facts unrelated to this chronicle.
- Traditions and customs are concepts, not dated events.
- Omit empty arrays when nothing applies.
TEXT;

        $user = <<<TEXT
## Source text

{$sliceText}

## Known entities (id | type | name | aliases)

{$catalog}

Extract mentions, relations, events, and memories from the source text.
TEXT;

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    public function buildForBiography(string $sliceText, string $characterName): array
    {
        $memoryTypes = implode(', ', array_map(
            fn (CharacterMemoryNodeType $type): string => $type->value,
            CharacterMemoryNodeType::cases(),
        ));

        $system = <<<TEXT
/no_think
You extract subjective memory candidates from a character biography. Reply with JSON only — no markdown fences, no commentary.

This is the character's own story, not objective world canon. Do not invent world events or directory entities.

Schema:
{
  "memories": [{ "text": "string", "node_type": "one of: {$memoryTypes}" }]
}

Rules:
- Each memory is a short first-person or character-centric fact the character would remember.
- "node_type" must be one of: {$memoryTypes}.
- Do not output mentions, relations, or world events.
- Omit empty arrays when nothing applies.
TEXT;

        $user = <<<TEXT
## Character

{$characterName}

## Biography

{$sliceText}

Extract personal memory candidates from this biography.
TEXT;

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];
    }

    /**
     * @param  list<string>  $catalogLines
     * @param  list<string>  $relationKeys
     * @return list<array{role: string, content: string}>
     */
    public function buildForScene(
        string $sliceText,
        array $catalogLines,
        array $relationKeys,
        bool $truncated = false,
    ): array {
        $entityKinds = implode(', ', array_map(
            fn (WorldEntityType $type): string => $type->value,
            WorldEntityType::cases(),
        ));
        $relationKeyList = $relationKeys === [] ? '(none)' : implode(', ', $relationKeys);
        $catalog = $catalogLines === []
            ? '(empty — no known entities yet)'
            : implode("\n", $catalogLines);
        $truncationNote = $truncated
            ? "\n- The message log was truncated to the newest lines; cite only message ids present in the slice."
            : '';

        $system = <<<TEXT
/no_think
You extract structured graph candidates from a scene chat log. Reply with JSON only — no markdown fences, no commentary.

Focus on world events and relations between known entities. Do not invent lore articles or character memories.

Schema:
{
  "mentions": [{ "name": "string", "kind": "one of: {$entityKinds}" }],
  "relations": [{ "source": "string", "target": "string", "key": "one of enabled relation keys" }],
  "events": [{
    "title": "string",
    "summary": "string",
    "participants": [{ "name": "string", "role": "actor|victim|witness|organizer|mentioned|other" }],
    "source_message_ids": [123]
  }],
  "memories": []
}

Rules:
- Use entity names as plain text only. Never output numeric entity IDs.
- "kind" must be one of: {$entityKinds}.
- "key" must be one of: {$relationKeyList}.
- "source_message_ids" must reference message ids from the scene log (id column).
- Prefer names from participants, the catalog, or the message authors.
- Do not output lore articles or memories — leave "memories" empty.
- Omit empty arrays when nothing applies.{$truncationNote}
TEXT;

        $user = <<<TEXT
{$sliceText}

## Known entities (id | type | name | aliases)

{$catalog}

Extract events, relations, and supporting mentions from this scene.
TEXT;

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];
    }
}
