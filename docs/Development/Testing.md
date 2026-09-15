# Тестирование

## PHPUnit

```bash
docker compose exec laravel.test php artisan test
```

Финальная приёмка этапа 35: **223 passed / 912 assertions** (2026-09-11), `migrate:fresh` и frontend build зелёные. Лист V20: `tests/Feature/CharacterSheetTest.php`.

## RAG без Ollama

В `phpunit.xml`: `RAG_EMBEDDING_DRIVER=stub` — тесты не требуют контейнер Ollama.

## Feature-тесты API

| Тест | Покрытие |
|------|----------|
| `tests/Feature/AuthenticationTest.php` | register, login, logout, `/api/user` |
| `tests/Feature/ChatTest.php` | GET/POST messages, author, mine, изоляция лент по сцене, закрытая сцена read-only, anonymize user |
| `tests/Feature/GameSessionSceneTest.php` | lifecycle сессий/сцен, роли и статусы |
| `tests/Feature/SceneContextTest.php` | канон сцены, revision, freeze, assembler, HTTP |
| `tests/Feature/SceneParticipantTest.php` | enter/leave, mixed chronicle, HTTP |
| `tests/Feature/ChronicleIsolationTest.php` | независимые active-сессии двух хроник; сцены и сообщения не смешиваются |
| `tests/Feature/WorldEntityTest.php` | атомарное создание identity/алиасов, изоляция хроник, архив вместо DELETE |
| `tests/Feature/TypedWorldEntityTest.php` | shared PK, entity_type constraint, parent/owner той же хроники |
| `tests/Feature/CharacterTest.php` | PC/NPC/гули, `character_id` cutover, snapshot/rename-safe author и Copilot, нет `character_users` |
| `tests/Feature/CharacterSheetTest.php` | catalog V20, создание/список, права игрока, round-trip stats/status/health/merits/XP/disciplines, биография HTTP, archive/restore |
| `tests/Feature/WorldDirectoryTest.php` | ST-справочник мира, политика фракций, aka, `parent_faction_id`, place HTTP |
| `tests/Feature/CharacterStatTest.php` | реляционный лист, unique key, SQL по category/value, read model |
| `tests/Feature/CharacterDisciplineTest.php` | каталог дисциплин/сил, уровень, совместимость, SQL-поиск |
| `tests/Feature/CharacterStatusTest.php` | current status, эффекты, журнал, optimistic revision, location chronicle |
| `tests/Feature/CharacterBiographyTest.php` | канон биографии, версии, immutability; нет `character_goals` |
| `tests/Feature/CharacterBioIndexTest.php` | отдельный HNSW биографии, rebuild, фильтр `character_id`, не `rag_chunks` |
| `tests/Feature/WorldRelationTypeTest.php` | каталог типов связей, validator направления |
| `tests/Feature/WorldRelationTest.php` | направленный граф, symmetric query, self-loop/дубли включая обратный symmetric |
| `tests/Feature/CharacterRelationshipTest.php` | A→B ≠ B→A; нет метрик и `relationship_changes` |
| `tests/Feature/CharacterAffiliationTest.php` | faction/location/item/concept, журнал, revision, setSect/setHaven, не character/event |
| `tests/Feature/WorldEventTest.php` | typed event, participants/sources, граф occurred_at/caused; нет timeline |
| `tests/Feature/LoreEntryTest.php` | канон лора, версии, связи с миром, archive/restore; не `rag_chunks` |
| `tests/Feature/LoreDirectoryTest.php` | ST HTTP статей, гриф, исключения, стол без галочек, индекс, archive |
| `tests/Feature/LoreIndexTest.php` | chronicle-scoped lore search, visibility, rebuild |
| `tests/Feature/RuleDocumentTest.php` | ruleset/edition, overrides, pivots, archive |
| `tests/Feature/RuleIndexTest.php` | отдельный корпус правил, override хроники |
| `tests/Feature/CharacterKnowledgeTest.php` | допуск/гриф лора; grant/deny; bio без grant; sync/revoke; правила по grant |
| `tests/Feature/CharacterMemoryNodeTest.php` | false-belief, alias search, нет auto из messages |
| `tests/Feature/CharacterMemoryEdgeTest.php` | self-loop/дубли, authored vs traversal |
| `tests/Feature/MemoryBridgeTest.php` | мосты к канону, mixed chronicle, knowledge filter |
| `tests/Feature/RetrievalCorpusTest.php` | раздельные корпуса, scoped message search, отсутствие `rag_chunks` |
| `tests/Feature/HybridRetrievalTest.php` | coordinator, дедуп, NPC lore grants |
| `tests/Feature/MemoryGraphRagTest.php` | depth, циклы, слабые рёбра, изоляция, bound |
| `tests/Feature/WorldGraphRagTest.php` | character→faction→location/event, leakage |
| `tests/Feature/CopilotTest.php` | два вызова Ollama (топики + реплика), лимит assembler, tool-loop, одноразовая привязка, HTTP E2E provenance Memory/World GraphRAG |
| `tests/Feature/ExtractorTest.php` | POST/GET `/api/extract` (лор + `character_id`), PATCH mention (имя/тип/подтип/aka), accept/discard mention/relation/memory, 502 без run; Ollama через `Http::fake` |
| `tests/Feature/ExtractorSceneTest.php` | окна сцены, auto-job, failed не re-dispatch, inbox, reparse, events/relations, accept event → `world_events`, close enqueue, Copilot без графа, `POST /api/world/events` |
| `tests/Feature/ContextAssemblerTest.php` | секции reply/topics, provenance, knowledge filter, GraphRAG не вытесняет newest messages |
| `tests/Feature/RagSearchTest.php` | message index, обязательный chronicle scope, embedding failure |
| `tests/Feature/RetrievalToolsTest.php` | session scope, лимиты range, отказ неизвестного `search_summaries` |
| `tests/Feature/RetrievalGuardrailTest.php` | наличие индексов, CTE LIMIT/scope/timeout |

## Unit-тесты контекста

- `TokenEstimatorTest` — Unicode-оценка и валидация коэффициента.
- `AliasNormalizerTest` — нормализация алиасов и slug.
- `ExtractionResponseParserTest` — thinking-блок, newlines, лишняя кавычка перед ключом (`" "name"` / `""name"`).

Context Assembler проверяется через `ContextAssemblerTest` и payload fake Ollama в `CopilotTest`: вход реплики не превышает 12000, топики — 8000, newest raw history имеет приоритет, обязательные секции не вытесняются GraphRAG, лор выше допуска NPC в prompt не попадает.

## Ollama в feature-тестах

Не вызывать реальный Ollama. Для copilot — `Http::fake` на `config('ollama.url').'/api/chat'`. Первый запрос — JSON топиков (`num_predict` ≤ 512), следующие — drafts (и tool calls на проходе реплики):

```php
Http::fake(function (Request $request) {
    $predict = (int) ($request['options']['num_predict'] ?? 3000);
    if ($predict <= 512) {
        return Http::response([
            'message' => ['content' => json_encode(['topics' => ['Элизиум']])],
        ]);
    }

    return Http::response([
        'message' => ['content' => json_encode(['drafts' => ['...']])],
    ]);
});
```

## Sanctum в тестах

`Sanctum::actingAs($user)` или `withToken($token)` для защищённых маршрутов.

## Smoke Ollama (вручную, из контейнера Laravel)

- `php artisan rag:embed-ping`
- `php artisan llm:ping`
