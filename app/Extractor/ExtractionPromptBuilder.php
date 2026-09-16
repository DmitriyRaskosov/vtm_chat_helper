<?php

namespace App\Extractor;

use App\Enums\CharacterMemoryNodeType;
use App\Enums\WorldEntityType;

class ExtractionPromptBuilder
{
    /**
     * @return list<string>
     */
    private function directoryKindValues(): array
    {
        return array_map(
            fn (WorldEntityType $type): string => $type->value,
            [
                WorldEntityType::Faction,
                WorldEntityType::Clan,
                WorldEntityType::Coterie,
                WorldEntityType::Circle,
                WorldEntityType::Other,
                WorldEntityType::Location,
                WorldEntityType::Item,
                WorldEntityType::Concept,
            ],
        );
    }

    /**
     * @param  list<string>  $relationKeys
     */
    private function graphExtractionRules(string $relationKeyList, bool $forScene): string
    {
        $entityKinds = implode(', ', $this->directoryKindValues());
        $memoriesLine = $forScene
            ? '- Do not output memories — leave "memories" empty.'
            : '- "memories" only when the source is character-centric subjective text; for lore articles leave "memories" empty.';

        return <<<TEXT
Rules:
- Use entity names as plain text only. Never output numeric entity IDs.
- "kind" must be one of: {$entityKinds} (directory types only — not character or event).
- Entity kinds (VtM directory):
  - Sects and political blocs (Камарилья, Шабаш, Анархи, Инквизиция) → faction.
  - Vampire clans (Бруха, Вентру, Гангрел, …) → clan — never faction.
  - Player coteries → coterie; primogen councils / inner circles → circle.
  - Traditions, Masquerade, customs, codes (Традиции Каина, Маскарад) → concept — not faction, not event.
  - Unclear mortal or mixed groups → other.
- "key" must be one of: {$relationKeyList}.
- Relations:
  - member_of: the member is source, the sect/political faction is target. Example: Бруха → Камарилья (clan joins sect). NEVER Камарилья → Бруха.
  - member_of target must be faction only — never clan, coterie, circle, location, or concept.
  - Do not use member_of to mean "sect contains clan"; use clan member_of sect instead.
  - hostile_to / allied_with: usually between two factions (or characters).
  - created: source must be a character only — never faction, clan, or concept. Do not propose "Камарилья created Маскарад"; mention Маскарад as kind concept without a created relation.
- Prefer names that appear in the source text or catalog aliases.
- Every entity name used in relations must appear exactly once in mentions.
- Write mention names in nominative case (именительный падеж).
- Do not propose job titles or offices without a personal name (e.g. not "Примоген" alone).
- Do not create nodes for bloodlines, generations, or textbook facts unrelated to this chronicle.
- Traditions and customs are concepts, not dated events.
{$memoriesLine}
- Omit empty arrays when nothing applies.

Good example:
{
  "mentions": [
    { "name": "Камарилья", "kind": "faction" },
    { "name": "Бруха", "kind": "clan" },
    { "name": "Маскарад", "kind": "concept" }
  ],
  "relations": [
    { "source": "Бруха", "target": "Камарилья", "key": "member_of" }
  ]
}

Bad (never output):
{ "source": "Камарилья", "target": "Бруха", "key": "member_of" }
{ "source": "Камарилья", "target": "Маскарад", "key": "created" }
TEXT;
    }

    /**
     * @param  list<string>  $catalogLines
     * @param  list<string>  $relationKeys
     * @return list<array{role: string, content: string}>
     */
    public function build(string $sliceText, array $catalogLines, array $relationKeys): array
    {
        $entityKinds = implode('|', $this->directoryKindValues());
        $relationKeyList = $relationKeys === [] ? '(none)' : implode(', ', $relationKeys);
        $catalog = $catalogLines === []
            ? '(empty — no known entities yet)'
            : implode("\n", $catalogLines);

        $system = <<<TEXT
/no_think
You extract a chronicle lore graph. The source article is the only truth. Goal: complete coverage of named entities and explicit relations — not a summary. Reply with JSON only: no markdown, no commentary.

Schema:
{
  "mentions": [{ "name": "string", "kind": "{$entityKinds}" }],
  "relations": [{ "source": "string", "target": "string", "key": "{$relationKeyList}" }]
}

Coverage:
- Extract every named proper noun and every item of a list, table, or numbered code.
- If the text says a count ("16 статей", "кланы:"), output that many mention objects. Do not collapse a list into one node.
- Empty "mentions" is wrong when the article contains names. Fill mentions first; relations only between those names.
- Nominative case. No numeric IDs.

Catalog:
- Catalog lines are hints for an exact name/alias match (same spelling after case/space normalize).
- Substring is not identity: "Отступники Бруха" is not "Бруха". A longer or different name in the article is a new node even if a shorter catalog name sits inside it.
- Do not replace article names with catalog Camarilla clans.

Kinds:
- Sects and blocs (Камарилья, Шабаш, Анархи, Инквизиция) → faction.
- Clans and bloodlines named as clans, including antitribu / «Отступники X» → clan, never faction.
- Coteries → coterie; inner circles / primogen councils → circle.
- Cities, domains, temples, havens → location.
- Codes, traditions, named laws, offices without a personal name (Регент, Лексталионис, Кодекс Милана, статья кодекса) → concept.
- Mixed/unclear mortal groups → other.
- Skip: generation numbers, nameless «вампир» / «каинит» / «примоген».
- Do not output character or event.

Relations (key must be in the enabled list). Allowed directions:
- member_of: clan|coterie|circle|faction → faction. Source is the member. NEVER faction → clan.
- hostile_to / allied_with: faction ↔ faction only. A clan must not hostile_to a sect; use the sect (Шабаш hostile_to Камарилья).
- controls: faction → location|faction, only if the text states dominion over a place or bloc.
- owns: faction → item, only if stated.
- Do not emit located_at (not valid for faction/clan), created, knows, affiliated_with, or event keys.
- Do not fake part-of: Code articles are sibling concepts, not member_of the code.

Good:
{
  "mentions": [
    { "name": "Шабаш", "kind": "faction" },
    { "name": "Отступники Бруха", "kind": "clan" },
    { "name": "Мехико", "kind": "location" },
    { "name": "Кодекс Милана", "kind": "concept" },
    { "name": "Регент", "kind": "concept" }
  ],
  "relations": [
    { "source": "Отступники Бруха", "target": "Шабаш", "key": "member_of" },
    { "source": "Шабаш", "target": "Камарилья", "key": "hostile_to" },
    { "source": "Шабаш", "target": "Мехико", "key": "controls" }
  ]
}

Bad (never):
{ "name": "Бруха", "kind": "clan" }   // when the article says Отступники Бруха
{ "source": "Шабаш", "target": "Ласомбра", "key": "member_of" }
{ "source": "Отступники Бруха", "target": "Камарилья", "key": "hostile_to" }
{ "source": "Шабаш", "target": "Мехико", "key": "located_at" }
{ "source": "Статья I", "target": "Кодекс Милана", "key": "member_of" }
TEXT;

        $user = <<<TEXT
## Source text

{$sliceText}

## Known entities (id | type | name | aliases)

{$catalog}

Extract every named entity and explicit relation from the source text. Do not summarize lists.
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
        $entityKinds = implode(', ', $this->directoryKindValues());
        $relationKeyList = $relationKeys === [] ? '(none)' : implode(', ', $relationKeys);
        $catalog = $catalogLines === []
            ? '(empty — no known entities yet)'
            : implode("\n", $catalogLines);
        $truncationNote = $truncated
            ? "\n- The message log was truncated to the newest lines; cite only message ids present in the slice."
            : '';
        $rules = $this->graphExtractionRules($relationKeyList, true);

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

{$rules}
- "source_message_ids" must reference message ids from the scene log (id column).
- Prefer names from participants, the catalog, or the message authors.{$truncationNote}
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
