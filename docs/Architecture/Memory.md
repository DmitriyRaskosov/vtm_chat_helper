# Память персонажа

Субъективная память отделена от лора и мира. CRUD UI нет. Автосоздания из `messages` нет. Шкалы времени нет.

`character_memory_nodes`: текст, тип (event/person/place/object/emotion/conclusion/promise/trauma/rumor), importance, valence, arousal, confidence, false-belief, aliases, recall metrics, embedding + tsvector. **Без `timeline_id`.**

`CharacterMemorySearcher` требует `character_id`: vector + FTS + exact alias. Чтение не увеличивает `recall_count`.

`character_memory_edges`: направленные ассоциации одного персонажа; self-loop и дубли запрещены. `authored_weight` не смешивается с `traversal_count`. `neighbors()` не обновляет метрики обхода.

Мосты к канону (явные, не из текста): `memory_node_entities` (роль сущности), `memory_node_messages`, `memory_node_events`, `memory_node_lore_entries`, `memory_node_scenes`. `MemoryBridgeService` не копирует `node_text` в лор/сущность. Для NPC lore-мост виден только при grant; `storyteller_only` события скрыты.

GraphRAG личной памяти — `MemoryGraphRag`: seed searcher, `WITH RECURSIVE` depth ≤2, cycle guard, min weight, bounded bundle. Чтение графа не меняет recall/traversal. [[Architecture/Context|Context Assembler]] вставляет bundle в секцию Personal memory до LLM (не в tool-loop).

Видимость UI лора (`public` / `storyteller_only`) не равна знанию NPC: нужны строки `character_lore_knowledge` / `character_rule_knowledge`. Собственная биография доступна персонажу без grant.

См. [[Architecture/Lore]], [[Architecture/Retrieval]], [[Architecture/Backend]], [[Project/Architecture Migration]].
