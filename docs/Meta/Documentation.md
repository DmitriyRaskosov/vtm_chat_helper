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
| `frontend/**`, ChatView copilot UI | [[Architecture/Frontend]], [[Features/Copilot]] |
| compose.yaml, Sail, Ollama | [[Development/Setup]] |
| `.env.example` | [[Development/Environment]] |
| phpunit, feature tests | [[Development/Testing]] |
| roadmap, отложенные фичи | [[Project/Roadmap]] |
| роли storyteller/player | [[Project/Roles]] |
| структура каталогов, file map | [[Meta/Structure]] |

## Cursor

- Narrative docs: `docs/` (Obsidian vault)
- Coding conventions: `.cursor/rules/*.mdc` (`project-map.mdc`, `docs-obsidian.mdc` — always apply)
