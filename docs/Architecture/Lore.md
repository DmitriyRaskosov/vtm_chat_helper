# Лор хроники

Канон лора — PostgreSQL, не `rag_chunks`. CRUD UI нет.

`lore_entries`: chronicle_id, title, kind, canonical_text, status (`draft`/`approved`/`archived`), visibility (`public`/`storyteller_only`), current_version, legacy_source_id, created/approved metadata.

Immutable `lore_entry_versions`. Many-to-many `lore_entry_entities` только внутри той же хроники. Физический DELETE запрещён: `archive()` меняет статус; Eloquent и PG trigger отклоняют удаление.

Legacy `RagIndexer::indexLore`, глобальный lore search и таблица `rag_chunks` удалены этапом 35.

Производный индекс — `lore_chunks` (HNSW + GIN). `LoreIndexer` пишет только approved-версию. `LoreSearcher` требует `chronicle_id` и не возвращает archived. Поиск NPC — `searchForCharacter` по `character_lore_knowledge`; public visibility сам по себе знание не даёт.

Мост из памяти — `memory_node_lore_entries`; NPC видит связанный лор только при grant. World GraphRAG повторно применяет тот же фильтр. Context Assembler кладёт разрешённые lore chunks в блок `## World`.

См. [[Architecture/Backend]], [[Architecture/Memory]], [[Architecture/Retrieval]], [[API/RAG]], [[Project/Architecture Migration]].
