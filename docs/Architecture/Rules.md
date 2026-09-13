# Игровые правила

Канон правил — `rulesets` (name, edition, language) и `rule_documents` с immutable `rule_document_versions`. Статус документа: `draft` / `approved` / `archived`. Archive вместо DELETE (Eloquent + PG trigger). House rules — `chronicle_rule_overrides`, базовый текст не меняют.

Документ связывается с дисциплинами, силами, ключами характеристик и типами эффектов через отдельные pivot-таблицы. Строка `disciplines.ruleset` пока не FK.

Производный индекс — `game_rule_chunks`, отдельный корпус. `RuleSearcher` сначала фильтрует по ruleset/edition и исключает archived, затем подменяет base approved-override хроники. Поиск NPC — `searchForCharacter` по `character_rule_knowledge`. Context Assembler резолвит ruleset из грантов (у хроники нет `ruleset_id`) и кладёт чанки в блок `## Rules`.

HTTP CRUD нет.

См. [[Architecture/Backend]], [[Archive/Architecture Migration]].
