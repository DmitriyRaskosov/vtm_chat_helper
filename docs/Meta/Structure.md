# Structure

Карта репозитория game-chat (Laravel 13 + Vue 3).

Два приложения: JSON API в Docker (Sail) и Vue SPA в `frontend/`. Браузер: http://localhost:5173 (Vite на хосте). API: http://localhost:8080/api.

## Корень

| Файл / каталог | Назначение |
|----------------|------------|
| `composer.json`, `compose.yaml` | PHP 8.4 API + queue worker, PostgreSQL+pgvector, Redis, Mailpit, Ollama |
| `.env`, `.env.example` | порты, RAG, Ollama |
| `sail.cmd` | Sail под Windows |
| `artisan` | CLI (через `docker compose exec laravel.test`) |
| `README.md` | быстрый старт |
| `docs/` | Obsidian vault ([[Home]]) |

## frontend/ — Vue 3 SPA

| Путь | Назначение |
|------|------------|
| `src/views/ChatView.vue` | чат + панель рассказчика (copilot) |
| `src/views/CharacterListView.vue` | список листов, создание персонажа |
| `src/views/CharacterSheetView.vue` | лист V20 + compact гуль |
| `src/auth.js` | axios + Sanctum token |
| `vite.config.js` | прокси `/api` → localhost:8080 |

Запуск: `cd frontend && npm run dev` (порт 5173, не в Docker). Подробнее: [[Architecture/Frontend]].

## app/ — backend

### Http/Controllers/

| Файл | Маршрут |
|------|---------|
| `ChatController.php` | GET/POST `/api/messages`, scoped по сцене |
| `GameSessionController.php` | active/create игровых сессий |
| `SceneController.php` | create/activate/close сцен |
| `SceneContextController.php` | GET/PUT канона сцены |
| `SceneParticipantController.php` | list/enter/leave участников |
| `CopilotController.php` | POST `/api/copilot/drafts` (storyteller) |
| `RagSearchController.php` | GET `/api/rag/search` (storyteller) |
| `CharacterSheetController.php` | catalog, CRUD листа V20 |
| `Auth/*` | register, login, logout, `/api/user` |

### Http/Middleware

- `EnsureStoryteller.php` — middleware `storyteller`

### Models/

| Модель | Поля / роль |
|--------|-------------|
| `User.php` | login, role (storyteller \| player) |
| `Chronicle.php` | корневой scope мира и игровых встреч |
| `WorldEntity.php` | стабильный ID сущности мира |
| `WorldEntityAlias.php` | каноническое имя и aka внутри хроники |
| `Location.php`, `Faction.php`, `Item.php`, `Concept.php`, `Character.php` | typed-строки с shared PK |
| `WorldEvent.php`, `WorldEventParticipant.php`, `WorldEventSource.php` | события мира |
| `CharacterStat.php`, `CharacterStatSpecialization.php` | реляционный лист |
| `CharacterMeritFlaw.php` | merits/flaws; таблица `character_merits_flaws` |
| `CharacterHealthBox.php` | 7 клеток V20 |
| `Discipline.php`, `DisciplinePower.php`, `CharacterDiscipline.php`, `CharacterPower.php` | каталог и изученные силы |
| `CharacterStatus.php`, `CharacterStatusEffect.php`, `CharacterStatusChange.php` | текущее состояние и журнал |
| `CharacterBiography.php`, `CharacterBiographyVersion.php` | канон биографии и immutable-версии |
| `CharacterBioChunk.php` | производный векторный индекс биографии |
| `WorldRelationType.php` | каталог типов рёбер мира |
| `WorldRelation.php` | направленное ребро `world_relations` |
| `CharacterRelationship.php` | typed extension character→character без метрик |
| `CharacterAffiliation.php`, `CharacterAffiliationChange.php` | affiliation и журнал |
| `LoreEntry.php`, `LoreEntryVersion.php`, `LoreEntryEntity.php`, `LoreChunk.php` | канон лора и индекс |
| `Ruleset.php`, `RuleDocument.php`, `RuleDocumentVersion.php`, `ChronicleRuleOverride.php`, `GameRuleChunk.php` | правила |
| `CharacterLoreKnowledge.php`, `CharacterRuleKnowledge.php` | grants знания |
| `CharacterMemoryNode.php`, `CharacterMemoryEdge.php` | субъективная память |
| `MemoryNodeEntity.php`, `MemoryNodeMessage.php`, `MemoryNodeEvent.php`, `MemoryNodeLoreEntry.php`, `MemoryNodeScene.php` | мосты памяти к канону |
| `MessageEmbedding.php` | корпус эмбеддингов сообщений |
| `GameSession.php` | игровая встреча; `chronicle_id` обязателен |
| `Scene.php` | сцена и её lifecycle |
| `SceneContext.php` | канон активной сцены: location, atmosphere, situation, notes, revision |
| `SceneParticipant.php` | входы персонажей на сцену |
| `Message.php` | scene_id, body, npc_name snapshot, nullable author_character_id, token estimate, nullable copilot_request_id, nullable user_id |
| `RagChunk.php` | векторный индекс для RAG |
| `CopilotRequest.php` | prompt, drafts, context metadata, character_id для новых строк, snapshot npc_name |

### Context/

- `TokenEstimator.php` — версионируемая локальная оценка токенов
- `ContextAssembler.php`, `ContextRequest.php`, `ContextAssembly.php`, `ContextSection.php`, `LineTrimmer.php` — композиция слоёв prompt
- `Context/Providers/*` — system, identity, scene, status, prompt, recent messages, relations, bio, memory graph, world/lore, rules, closing
- `ContextBuilder.php`, `ContextBuild.php` — фасад Copilot и результат (LLM messages + provenance)

### Scene/

- `SceneContextService.php` — optimistic revision, freeze при закрытии
- `SceneParticipantService.php` — enter/leave, один current на персонажа

### Retrieval/

- `RetrievalOrchestrator.php`, `RetrievalScope.php` — chronicle- и session-scoped tool calls
- `CteGuard.php` — `SET LOCAL statement_timeout` вокруг GraphRAG CTE
- `Tools/SearchMessagesTool.php`, `GetMessageRangeTool.php`
- `Hybrid/HybridRetrievalCoordinator.php` — корпуса; Copilot loop не использует
- GraphRAG в prompt: `MemoryGraphRag` / `WorldGraphRag` через Context Assembler, не через tools

### World/

- `WorldEntityService.php` — атомарное создание identity, typed-строки (включая character) и алиасов
- `AliasNormalizer.php` — нормализация имён и slug
- `WorldRelationTypeCatalog.php`, `WorldRelationTypeValidator.php` — каталог типов связей и проверка направления
- `WorldRelationService.php` — запись рёбер и neighbors
- `WorldEventService.php` — participants, sources и связи события
- `WorldGraphRag.php` — bounded CTE мира

### Lore/

- `LoreEntryService.php` — канон лора и immutable-версии
- `LoreIndexer.php`, `LoreSearcher.php` — корпус `lore_chunks`

### Rulebook/

- `RulesetService.php`, `RuleDocumentService.php` — edition, документы, overrides
- `RuleIndexer.php`, `RuleSearcher.php` — корпус `game_rule_chunks`

### Memory/

- `CharacterMemoryService.php` — nodes и edges
- `CharacterMemorySearcher.php` — поиск с обязательным `character_id`
- `MemoryBridgeService.php` — мосты к канону
- `MemoryGraphRag.php` — bounded CTE личной памяти

### Character/

- `CharacterStatService.php` — запись характеристик и специализаций
- `CharacterSheetReader.php`, `CharacterSheet.php` — полный лист, агрегат HTTP и урезанный набор для prompt
- `SheetCatalog.php`, `CharacterAccess.php` — каталог V20 и права листа/речи
- `CharacterIdentityService.php`, `CharacterHealthService.php`, `CharacterMeritService.php` — шапка/XP, клетки здоровья, merits/flaws
- `DisciplineService.php` — каталог дисциплин/сил и изученное персонажем
- `CharacterStatusService.php` — current status, эффекты, журнал, optimistic revision
- `CharacterBiographyService.php` — канон биографии и immutable-версии
- `CharacterBioIndexer.php`, `CharacterBioSearcher.php` — отдельный корпус биографии
- `CharacterRelationshipService.php` — направленные отношения персонажей без метрик
- `CharacterAffiliationService.php` — affiliations и журнал
- `CharacterLoreKnowledgeService.php`, `CharacterRuleKnowledgeService.php` — grants знания

### Rag/

- `RagIndexer.php` — фасад индексации сообщений в отдельный корпус
- `MessageIndexer.php`, `MessageSearcher.php` — корпус `message_embeddings`
- `TextChunker.php` — нарезка для lore/rule индексов
- `OllamaEmbeddingProvider.php` — эмбеддинги через Ollama

### Llm/

- `OllamaChatProvider.php` — `qwen3:8b`, `chatTurn` и tools
- `NpcCopilotService.php`, `CopilotDraftResult.php` — вызов/парсинг черновиков и результат для аудита

### Jobs

- `IndexRagMessageJob.php` — индексация после сообщения

### Enums

- `ChronicleStatus.php`, `GameSessionStatus.php`, `SceneStatus.php`
- `WorldEntityType.php`, `WorldEntityStatus.php`, `WorldEntityAliasType.php`
- `LocationType.php`, `FactionType.php`, `FactionStatus.php`, `ItemType.php`, `ItemStatus.php`, `ConceptType.php`, `CharacterType.php`, `CharacterStatCategory.php`, `CharacterHealthState.php`, `CharacterHealthDamage.php`, `CharacterMeritKind.php`, `CharacterStatusEffectType.php`, `CharacterBiographyStatus.php`, `CharacterBiographySection.php`, `CharacterAffiliationType.php`, `CharacterAffiliationStance.php`, `WorldEventType.php`, `WorldEventStatus.php`, `WorldEventVisibility.php`, `WorldEventParticipantRole.php`, `LoreEntryKind.php`, `LoreEntryStatus.php`, `LoreVisibility.php`, `LoreChunkSection.php`, `RulesetStatus.php`, `RuleDocumentStatus.php`, `CharacterKnowledgeLevel.php`, `CharacterMemoryNodeType.php`, `CharacterMemoryStatus.php`, `CharacterMemoryEdgeType.php`, `MemoryEntityRole.php`, `RetrievalCorpus.php`

Слои: [[Architecture/Backend]].

## database/

### migrations/

- `chronicles` — корневой scope; backfill служебной «Основной хроники»
- `world_entities`, `world_entity_aliases` — идентичность мира; архивирование вместо DELETE
- `locations`, `factions`, `items`, `concepts`, `characters`, `world_events` — typed-строки, PK = `world_entities.id`; у `characters` тип `player`/`npc`/`ghoul`, `domitor_character_id`, `experience`
- `character_stats`, `character_stat_specializations` — реляционный лист; unique `(character_id, category, stat_key)`
- `character_health_boxes` — 7 клеток V20; `character_merits_flaws` — merits/flaws
- `disciplines`, `discipline_powers`, `character_disciplines`, `character_powers` — каталог и изученные силы
- `character_status`, `character_status_effects`, `character_status_changes` — текущее состояние и журнал
- `character_biographies`, `character_biography_versions` — канон и immutable snapshot
- `character_bio_chunks` — производный индекс биографии, отдельный HNSW, не `rag_chunks`
- `world_relation_types` — каталог семантики рёбер
- `world_relations` — направленный граф; симметрия в query layer
- `character_relationships` — extension character→character; без метрик и `relationship_changes`
- `character_affiliations`, `character_affiliation_changes` — отношение к миру и журнал
- `world_event_participants`, `world_event_sources` — участники и provenance события; `chronicle_timeline` нет
- `lore_entries`, `lore_entry_versions`, `lore_entry_entities`, `lore_chunks` — канон лора и индекс
- `rulesets`, `rule_documents`, `rule_document_versions`, `chronicle_rule_overrides`, `game_rule_chunks` — правила
- `character_lore_knowledge`, `character_rule_knowledge` — grants
- `character_memory_nodes`, `character_memory_edges` — память; без timeline
- `memory_node_entities`, `memory_node_messages`, `memory_node_events`, `memory_node_lore_entries`, `memory_node_scenes` — мосты памяти
- `message_embeddings` — отдельный scoped-корпус сообщений
- `game_sessions`, `scenes`, `scene_contexts`, `scene_participants` — иерархия чата и канон сцены; `game_sessions.chronicle_id` обязателен, одна active-сессия на хронику
- `messages` — nullable user_id (оператор, anonymize при DELETE), scene_id, body, npc_name snapshot, nullable author_character_id, token estimate
- `copilot_requests` — успешные генерации, drafts, версии и context metadata; новые строки имеют `character_id` и snapshot `npc_name`; `messages.copilot_request_id` — одноразовая связь

### factories/

- `UserFactory` — `storyteller()`, default password `password`
- `ChronicleFactory`
- `WorldEntityFactory`, `WorldEntityAliasFactory`
- `LocationFactory`, `FactionFactory`, `ItemFactory`, `ConceptFactory`, `CharacterFactory`, `WorldEventFactory`, `WorldEventParticipantFactory`, `WorldEventSourceFactory`
- `CharacterStatFactory`, `CharacterStatSpecializationFactory`
- `DisciplineFactory`, `DisciplinePowerFactory`, `CharacterDisciplineFactory`, `CharacterPowerFactory`
- `CharacterStatusFactory`, `CharacterStatusEffectFactory`, `CharacterStatusChangeFactory`
- `CharacterBiographyFactory`, `CharacterBiographyVersionFactory`, `CharacterBioChunkFactory`
- `WorldRelationTypeFactory`, `WorldRelationFactory`, `CharacterRelationshipFactory`
- `CharacterAffiliationFactory`, `CharacterAffiliationChangeFactory`
- `LoreEntryFactory`, `LoreEntryVersionFactory`, `LoreEntryEntityFactory`, `LoreChunkFactory`
- `RulesetFactory`, `RuleDocumentFactory`, `RuleDocumentVersionFactory`, `ChronicleRuleOverrideFactory`, `GameRuleChunkFactory`
- `CharacterLoreKnowledgeFactory`, `CharacterRuleKnowledgeFactory`
- `CharacterMemoryNodeFactory`, `CharacterMemoryEdgeFactory`
- `MemoryNodeEntityFactory`, `MemoryNodeMessageFactory`, `MemoryNodeEventFactory`, `MemoryNodeLoreEntryFactory`, `MemoryNodeSceneFactory`
- `GameSessionFactory`, `SceneFactory`, `SceneContextFactory`, `SceneParticipantFactory`, `MessageFactory`

## routes/

- `api.php` — единственные HTTP-маршруты (без `web.php` / Blade)

## config/

- `rag.php`, `ollama.php`, `copilot.php`, `context.php`, `retrieval.php`

## tests/Feature/

- `AuthenticationTest`, `ChatTest`, `CharacterTest`, `CharacterSheetTest`, `CharacterStatTest`, `CharacterDisciplineTest`, `CharacterStatusTest`, `CharacterBiographyTest`, `CharacterBioIndexTest`, `CharacterRelationshipTest`, `CharacterAffiliationTest`, `WorldEventTest`, `LoreEntryTest`, `LoreIndexTest`, `RuleDocumentTest`, `RuleIndexTest`, `CharacterKnowledgeTest`, `CharacterMemoryNodeTest`, `CharacterMemoryEdgeTest`, `MemoryBridgeTest`, `RetrievalCorpusTest`, `HybridRetrievalTest`, `MemoryGraphRagTest`, `WorldGraphRagTest`, `WorldRelationTypeTest`, `WorldRelationTest`, `ChronicleIsolationTest`, `GameSessionSceneTest`, `SceneContextTest`, `SceneParticipantTest`, `WorldEntityTest`, `TypedWorldEntityTest`, `RagSearchTest`, `CopilotTest`, `ContextAssemblerTest`, `RetrievalToolsTest`, `RetrievalGuardrailTest`

См. [[Development/Testing]].

## Copilot flow

Рассказчик → `POST /api/copilot/drafts` → `ContextAssembler` (слои канона, бюджет 12000) → Ollama `qwen3:8b` (tools только для истории сессии, `num_ctx=16384`) → сохранённый request + JSON с черновиками.

Рассказчик правит → `POST /api/messages` `{ body, character_id, copilot_request_id, copilot_draft_index }` → одноразовая связь → чат для всех и индекс сообщения.

Подробнее: [[Features/Copilot]], [[API/Copilot]].

Ollama только внутри Docker: `http://ollama:11434` (порт 11434 не на хосте).
