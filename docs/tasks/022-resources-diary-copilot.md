# 022 — Resources для дневника и ответа Copilot

- **Stage:** 2
- **Part:** 3
- **Status:** todo
- **Depends on:** 021
- **Risk:** low
- **Разрешённые зависимости:** нет

## Goal

Записи дневника и ответ Copilot сериализуются через `JsonResource`, приватные `serializeList`/`serializeFull` в `DiaryEntryController` удалены, форма JSON не изменилась.

## Context

- `DiaryEntryController::serializeList`: `id, character_id, scene_id, scene_title, level, from_message_id, to_message_id, is_stale, importance, emotion, preview` (300 символов), `word_count`, `created_at` (ISO 8601); `serializeFull` = list + `entry`.
- `index` → `{character_id, entries: [...]}`; `show/update/regenerate` → `{entry: {...}}`.
- Copilot (после 024a) → `{copilot_request_id, drafts}` из DTO сервиса.
- **Превью дневника (`POST /characters/{id}/diary/preview`) исключено:** его форма формируется в `App\Context\DiaryPreviewService` (перенесён в 029) и остаётся массивом сервиса.
- `DiaryPreviewService` перенесён в `App\Context` (029) — если в задаче есть ссылки на `App\Diary\DiaryPreviewService`, обновить на `App\Context`. Форма ответа preview (`selected`, `rendered`, `stats`) не должна измениться.

## Scope

- `app/Http/Resources/{DiaryEntryListResource,DiaryEntryResource,CopilotDraftsResource}.php` (создать; имена можно уточнить)
- `app/Http/Controllers/DiaryEntryController.php` — только `index/show/update/regenerate`
- `app/Http/Controllers/CopilotController.php` — только сериализация
- `tests/Feature/DiaryJsonShapeTest.php` (создать), `tests/Feature/CopilotTest.php`
- `DiaryEntryController::serializeList` / `serializeFull` → `DiaryEntryResource`.
- `CopilotController`: ответ `CopilotDraftService` → `CopilotDraftResource`.

**Out of scope:** `preview` и `DiaryPreviewService`; изменение полей; авторизация.

## Steps

1. Тест формы JSON до правок: `GET /characters/{id}/diary`, `GET/PATCH /diary-entries/{id}`, `POST .../regenerate`, `POST /copilot/drafts`.
2. Resources с теми же ключами; `preview` и `word_count` считаются как сейчас (`mb_substr`, `preg_split('/\s+/u')`).
3. Заменить сериализацию, удалить приватные методы.

## Definition of Done

- [ ] тесты шага 1 зелёные до и после без изменений
- [ ] `rg "function serialize" app/Http/Controllers` → пусто
- [ ] ручная проверка: экран дневника — список, открыть, правка, пересоздание
- [ ] `sail artisan test` — зелёные; `sail pint --test` — чисто; phpstan — OK, baseline не вырос

## Report

Формат — `.cursor/rules/task-execution.mdc` §5.
