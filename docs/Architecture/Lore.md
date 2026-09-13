# Лор хроники

Канон лора — PostgreSQL, не `rag_chunks`. ST-редактор статей на вкладке «Лор» экрана [[Features/World]]. Допуск персонажа — на [[Features/Characters|листе]] рядом с местом в мире.

`lore_entries`: chronicle_id, title, kind, canonical_text, status (`draft`/`approved`/`archived`), visibility (`public`/`storyteller_only`), classification (`0`–`5`), `situational` (boolean), current_version, legacy_source_id, created/approved metadata.

Immutable `lore_entry_versions` (включая classification и situational). Many-to-many `lore_entry_entities` только внутри той же хроники (статья «о» фракции/месте/персонаже). Исключения — `character_lore_knowledge.access` `grant`/`deny`; норма доступа — `classification` входит в `characters.lore_clearance_levels` (набор, не потолок); `situational=true` — только grant, минус deny. `visibility` сама по себе знание NPC не даёт и не отбирает. Физический DELETE запрещён: `archive()` меняет статус; Eloquent и PG trigger отклоняют удаление. `restore()` возвращает `approved`.

HTTP: [[API/Lore]]. Экран мира публикует статью как approved и сразу зовёт `LoreIndexer`.

Legacy `RagIndexer::indexLore`, глобальный lore search и таблица `rag_chunks` удалены этапом 35.

Производный индекс — `lore_chunks` (HNSW + GIN). `LoreIndexer` пишет только approved-версию. `LoreSearcher` требует `chronicle_id` и не возвращает archived. Поиск NPC — `searchForCharacter` по видимому множеству (допуск + гранты − запреты).

Мост из памяти — `memory_node_lore_entries`; NPC видит связанный лор только если статья в этом видимом множестве. World GraphRAG повторно применяет тот же фильтр. Context Assembler кладёт разрешённые lore chunks в блок `## World`. Гриф не пишет в промпт «ты неофит»: он только решает, какие куски попадут в `Known lore:`.

См. [[Architecture/Backend]], [[Architecture/Memory]], [[Architecture/Retrieval]], [[API/RAG]], [[Archive/Architecture Migration]].
