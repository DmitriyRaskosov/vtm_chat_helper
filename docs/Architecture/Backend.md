# Backend

Laravel JSON API. Маршруты только в `routes/api.php`.

## Слои

### `app/Rag/`

| Компонент | Назначение |
|-----------|------------|
| `RagIndexer` | Совместимый фасад индексации сообщений в `message_embeddings` |
| `MessageIndexer` / `MessageSearcher` | Отдельный корпус сообщений; обязательный `chronicle_id`, optional session/scene scope |
| `EmbeddingProvider` | `ollama` (Ollama) или `stub` (тесты) |
| `TextChunker` | нарезка текста для lore/rule индексов |

### `app/Llm/`

| Компонент | Назначение |
|-----------|------------|
| `ChatProvider` | Интерфейс чат-модели, включая `chatTurn` для tool calls |
| `OllamaChatProvider` | Ollama `/api/chat` для `qwen3:8b`, runtime-лимиты и JSON tools |
| `NpcCopilotService` | Два вызова LLM: топики, затем tool-loop и парсинг JSON drafts |
| `CopilotDraftResult` | Drafts и данные для аудита успешной генерации |

### `app/Context/`

| Компонент | Назначение |
|-----------|------------|
| `TokenEstimator` | Версионируемая локальная оценка токенов |
| `ContextAssembler` | Два прохода: топики без графа; реплика с GraphRAG по топикам |
| `ContextBuilder` | Фасад Copilot → assembler (`context-assembler-v2`) |
| `ContextBuild` | LLM messages и metadata включённых источников |

### `app/Scene/`

| Компонент | Назначение |
|-----------|------------|
| `SceneContextService` | канон сцены, optimistic revision, freeze |
| `SceneParticipantService` | enter/leave, один current `(scene, character)` |

### `app/Retrieval/`

| Компонент | Назначение |
|-----------|------------|
| `RetrievalOrchestrator` | Вызов tools с лимитами и оценкой токенов |
| `RetrievalToolRegistry` | `search_messages`, `get_message_range` |
| `RetrievalScope` | Изоляция текущей хроники и игровой сессии |
| `HybridRetrievalCoordinator` | Маршрутизация по корпусам; Copilot loop не использует |
| `CteGuard` | statement timeout вокруг recursive CTE |

Подробности: [[Architecture/Retrieval]].

### `app/World/`

| Компонент | Назначение |
|-----------|------------|
| `WorldEntityService` | identity + typed-строка + алиасы; archive/restore вместо DELETE; клан только `faction_type=clan` |
| `AliasNormalizer` | нормализация имён и slug |
| `WorldRelationTypeCatalog` / `WorldRelationTypeValidator` | семантика типов рёбер; направление и типы узлов |
| `WorldRelationService` | направленный граф, запрет self-loop/дублей (включая обратный symmetric), `replaceAmong`, neighbors |
| `WorldEventService` | participants, sources, occurred_at/caused/witnessed/participated_in |
| `WorldGraphRag` | bounded CTE по `world_relations`; knowledge filter; statement timeout |

Подробности: [[Architecture/World]].

### `app/Lore/`

| Компонент | Назначение |
|-----------|------------|
| `LoreEntryService` | канон лора + immutable-версии; archive/restore; syncEntities; не `rag_chunks` |
| `LoreIndexer` / `LoreSearcher` | корпус `lore_chunks`; обязательный `chronicle_id`; NPC — допуск + исключения |

### `app/Rulebook/`

| Компонент | Назначение |
|-----------|------------|
| `RulesetService` / `RuleDocumentService` | edition, документы, pivots, chronicle overrides, archive |
| `RuleIndexer` / `RuleSearcher` | корпус `game_rule_chunks`; ruleset/edition, затем override |

### `app/Memory/`

| Компонент | Назначение |
|-----------|------------|
| `CharacterMemoryService` | nodes и directed edges; чтение не меняет authored weight |
| `CharacterMemorySearcher` | vector + FTS + alias; обязательный `character_id` |
| `MemoryBridgeService` | явные мосты к канону; не копирует текст памяти |
| `MemoryGraphRag` | bounded CTE по `character_memory_edges`; statement timeout |

Подробности: [[Architecture/Lore]], [[Architecture/Rules]], [[Architecture/Memory]].

### `app/Character/`

| Компонент | Назначение |
|-----------|------------|
| `CharacterStatService` | запись характеристик и специализаций |
| `CharacterSheetReader` / `CharacterSheet` | полный лист, HTTP-агрегат и урезанный набор для prompt |
| `SheetCatalog` / `CharacterAccess` | ключи V20 и права листа/речи |
| `CharacterIdentityService` | rename, typed-поля, experience |
| `CharacterPlaceService` | секта / клан / гавань / допуск к лору листа |
| `CharacterHealthService` | 7 клеток; sync `health_state` без bump revision |
| `CharacterMeritService` | замена merits/flaws |
| `DisciplineService` | каталог дисциплин/сил и изученное персонажем |
| `CharacterStatusService` | current row + журнал в одной транзакции, optimistic revision |
| `CharacterBiographyService` | канон биографии + immutable-версии; индекс не трогает |
| `CharacterBioIndexer` / `CharacterBioSearcher` | отдельный корпус `character_bio_chunks`; фильтр `character_id`; vector+FTS |
| `CharacterRelationshipService` | направленное character→character расширение графа без метрик |
| `CharacterAffiliationService` | affiliation + журнал, optimistic revision; setSect/setHaven; цель не character/event |
| `CharacterLoreKnowledgeService` / `CharacterRuleKnowledgeService` | лор: допуск + grant/deny; правила: grants |

### Jobs

- `IndexRagMessageJob` — после `POST /api/messages` (`RAG_INDEX_SYNC` sync vs queue); unique по `message_id`

## Модели

- `Chronicle` — корневой scope мира и игровых встреч; без membership/RBAC
- `WorldEntity` / `WorldEntityAlias` — стабильный ID сущности мира и её имена; удаление запрещено
- `Location`, `Faction`, `Item`, `Concept`, `Character`, `WorldEvent` — typed-строки с тем же ID; FK-цели для графа. PC: unique `user_id` (1 пользователь = 1 вампир); NPC без пользователя; гуль без пользователя, с `domitor_character_id`
- `CharacterStat` / `CharacterStatSpecialization` — реляционный лист, не JSON-blob
- `CharacterMeritFlaw` — merits/flaws (`character_merits_flaws`)
- `CharacterHealthBox` — клетки здоровья V20
- `Discipline` / `DisciplinePower` / `CharacterDiscipline` / `CharacterPower` — каталог и изученные силы
- `CharacterStatus` / `CharacterStatusEffect` / `CharacterStatusChange` — текущее состояние, эффекты, журнал
- `CharacterBiography` / `CharacterBiographyVersion` — канон биографии и immutable snapshot; цели персонажа не хранятся
- `CharacterBioChunk` — производный векторный индекс биографии, не `rag_chunks`
- `WorldRelationType` — каталог типов рёбер
- `WorldRelation` — направленное ребро графа; симметрия только в query layer
- `CharacterRelationship` — typed extension character→character; метрики и журнал отложены
- `CharacterAffiliation` / `CharacterAffiliationChange` — отношение к миру и журнал
- `WorldEvent` / `WorldEventParticipant` / `WorldEventSource` — канон события; без timeline
- `LoreEntry` / `LoreEntryVersion` / `LoreEntryEntity` / `LoreChunk` — канон лора и производный индекс
- `Ruleset` / `RuleDocument` / `RuleDocumentVersion` / `ChronicleRuleOverride` / `GameRuleChunk` — правила и house rules
- `CharacterLoreKnowledge` / `CharacterRuleKnowledge` — grants знания NPC
- `CharacterMemoryNode` / `CharacterMemoryEdge` — субъективная память; без timeline
- `MemoryNodeEntity` / `MemoryNodeMessage` / `MemoryNodeEvent` / `MemoryNodeLoreEntry` / `MemoryNodeScene` — мосты памяти к канону
- `MessageEmbedding` — отдельный scoped-корпус эмбеддингов сообщений
- `GameSession` — игровая встреча хроники; активна не более одной на хронику
- `Scene` — сцена со статусом `draft`, `active` или `closed`
- `SceneContext` / `SceneParticipant` — канон сцены и присутствующие
- `Message` — канон чата; обязательный `scene_id`, nullable `user_id`/`author_character_id`, snapshot `npc_name`, кеш оценки токенов
- `CopilotRequest` — успешный вызов Copilot, drafts, context metadata, обязательный для новых запросов `character_id` и snapshot `npc_name`

## Middleware

- `auth:sanctum` — защищённые маршруты
- `storyteller` (`EnsureStoryteller`) — только рассказчик

## Конфиг

`config/rag.php`, `config/ollama.php`, `config/copilot.php`, `config/context.php`, `config/retrieval.php`.

## Artisan

- `rag:embed-ping`, `llm:ping` — smoke Ollama
- `rag:reindex-messages` — полная переиндексация сообщений в `message_embeddings`
- `rag:search {chronicle_id} {query}` — scoped-поиск корпуса сообщений

## API

См. [[API/Overview]].
