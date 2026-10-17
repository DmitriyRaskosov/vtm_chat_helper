# 025b — Сообщения: логика ChatController в модуле Messages

- **Stage:** 2
- **Part:** 2
- **Status:** todo
- **Depends on:** 025a
- **Risk:** high (отправка сообщения с черновиком Copilot — основной игровой путь)
- **Разрешённые зависимости:** нет

## Goal

`ChatController` не содержит транзакций, Eloquent-запросов и бизнес-проверок: создание, переключение OOC, удаление и восстановление сообщений выполняет `App\Messages\MessageService`.

## Context

- `ChatController::store` (~79–125): `DB::transaction`, `CopilotRequest` с `lockForUpdate`, 4 проверки:
  - `409 Copilot request was already used.`
  - `403 Copilot request belongs to another storyteller.`
  - `409 Copilot request does not match this message.`
  - `422 Selected Copilot draft does not exist.` (Larastan считает `array_key_exists` на `drafts` всегда ложным — ложное срабатывание или баг, проверить тестом)
- `toggleOoc`/`destroy`/`restore` внедряют `MessageEditService` в параметры метода, `destroy`/`restore` берут пользователя через `request()->user()`; `serialize` использует `auth()->id()`.
- `isNpcLikeSpeech(Character, ?User)` — `$user` не используется.
- Baseline: 3 записи `ChatController` (`debt: 025b`).
- После 024 — сцена из `ActiveSceneResolver`, персонаж — из guard; после 025a — `DiaryScheduler::afterMessagePosted`.

## Scope

- `app/Messages/MessageService.php` (создать) — или расширение `MessageEditService` (обосновать)
- `app/Messages/Exceptions/*` (создать)
- `app/Messages/MessageEditService.php`
- `app/Http/Controllers/ChatController.php`
- `phpstan-baseline.neon` — удаление закрытых записей
- `tests/Feature/MessageTest.php`, `MessageEditTest.php`, `CopilotTest.php`

**Out of scope:** форма JSON сообщения (Resource — 021); авторизация (`canSpeakAs`, `assertCanEdit` → 030a); `StoreMessageRequest::authorize`.

## Steps

1. Характеризация: 4 проверки `CopilotRequest` выше (код + текст); успешная отправка с черновиком выставляет `selected_draft_index`; повторное использование → 409. Если тест 422 «draft does not exist» показывает, что валидный индекс отвергается, — это баг: остановиться и сообщить.
2. `MessageService::post(Scene, User, PostMessageData): Message` — транзакция, блокировка `CopilotRequest`, проверки через доменные исключения (тексты и коды прежние; 403 пока остаётся исключением авторизации — пометить для 030a), вызов `DiaryScheduler::afterMessagePosted` после коммита.
3. `toggleOoc`/`softDelete`/`restore` — принимают `User` явно; контроллер передаёт `$request->user()`.
4. Контроллер: зависимости в конструктор; убрать `request()`, `auth()` из приватных методов (зритель передаётся явно в `serialize`); удалить `isNpcLikeSpeech` или параметр `$user`.
5. `MessageEditService::markDiaryStale` — сейчас пишет через `DB::table` в `character_diary_entries`. Заменить на вызов метода Diary-модуля (`DiaryScheduler::markStale` из 025a, или `Diary\DiaryStaleMarker` — см. Scope 025a). Модуль Messages не должен знать о схеме дневника.
6. Удалить закрытые записи baseline.

## Definition of Done

- [ ] `rg "DB::|::query\(\)|abort|request\(\)|auth\(\)" app/Http/Controllers/ChatController.php` → пусто (кроме `load()` для ответа)
- [ ] `rg 'DB::table.*character_diary_entries' app/Messages` → пусто
- [ ] тесты шага 1 зелёные, коды и тексты не изменились
- [ ] записи baseline `ChatController` удалены или обоснованно оставлены
- [ ] ручная проверка: отправка реплики NPC из черновика Copilot в чате работает
- [ ] `sail artisan test` — зелёные; `sail pint --test` — чисто; phpstan — OK

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно: результат проверки `array_key_exists` (ложное срабатывание или баг).
