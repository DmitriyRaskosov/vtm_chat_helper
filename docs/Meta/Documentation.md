# Документация проекта

Каноническая narrative-документация — Obsidian vault в каталоге `docs/`.

## Открыть vault

1. Obsidian → **Open folder as vault**
2. Выбрать `docs/` в корне репозитория (`c:\Users\dmitr\game-chat\docs`)
3. Точка входа: [[Home]]

## Настройки Obsidian (рекомендуется)

Settings → **Files & Links**:

- New link format: **Relative path to file**
- **Automatically update internal links**: on

## Связи

Заметки связаны через `[[wikilinks]]`. Mermaid — для диаграмм (встроен в Obsidian).

## Код → документация

При изменении кода обновлять релевантные заметки в **той же задаче**:

| Область кода | Заметки |
|--------------|---------|
| `routes/api.php`, auth controllers | [[API/Auth]], [[API/Overview]] |
| messages, ChatController | [[API/Messages]], [[Features/Chat]] |
| copilot, NpcCopilotService, config/copilot.php | [[API/Copilot]], [[Features/Copilot]] |
| `app/Rag/**`, rag config, IndexRagMessageJob | [[API/RAG]], [[Architecture/Backend]] |
| `app/Llm/**`, ollama config | [[Architecture/Backend]], [[Features/Copilot]] |
| `frontend/**`, ChatView / листы персонажей / WorldView | [[Architecture/Frontend]], [[Features/Copilot]], [[Features/Characters]], [[Features/World]], [[API/Characters]], [[API/World]], [[API/Lore]] |
| compose.yaml, Sail, Ollama | [[Development/Setup]] |
| `.env.example` | [[Development/Environment]] |
| phpunit, feature tests | [[Development/Testing]] |
| roadmap, отложенные фичи | [[Project/Roadmap]] |
| роли storyteller/player | [[Project/Roles]] |
| `app/World/**`, typed entities | [[Architecture/World]], [[Architecture/Backend]], [[Architecture/Database]] |
| `app/Character/**`, CharacterSheetController | [[API/Characters]], [[Features/Characters]], [[Architecture/World]], [[Architecture/Database]] |
| `app/Lore/**` | [[Architecture/Lore]], [[API/Lore]], [[Architecture/Backend]] |
| `app/Rulebook/**` | [[Architecture/Rules]], [[Architecture/Backend]] |
| `app/Memory/**` | [[Architecture/Memory]], [[Architecture/Backend]] |
| структура каталогов, file map | [[Meta/Structure]] |

## Архивация планов

Живой пошаговый план: [[Project/ClanCard]]. Канон экстрактора: [[Project/Extractor]] (не архив). Завершённый или заменённый план из `docs/Project/` переносится в `docs/Archive/`; на старом пути — короткая заглушка со ссылкой на архив (образец: [[Project/Architecture Migration]] → [[Archive/Architecture Migration]]). Живые заметки (`Roadmap`, `Overview`, `Roles`) не архивируются. Подробности — правило `.cursor/rules/docs-plan-archive.mdc`.

## Cursor

- Narrative docs: `docs/` (Obsidian vault)
- Coding conventions: `.cursor/rules/*.mdc` (`project-map.mdc`, `docs-obsidian.mdc`, `database.mdc` — always apply). Схема БД не копируется в rules: канон [[Architecture/Database]].
