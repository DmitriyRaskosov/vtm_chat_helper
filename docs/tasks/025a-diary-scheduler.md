# 025a — DiaryScheduler: единственная точка запуска задач дневника

- **Stage:** 2
- **Part:** 2
- **Status:** todo
- **Depends on:** 024a
- **Risk:** med (момент запуска относительно транзакции)
- **Разрешённые зависимости:** нет

## Goal

Все задачи дневника запускаются только из `App\Diary\DiaryScheduler`, после коммита транзакции, а пометка записей дневника устаревшими выполняется методом модуля Diary.

## Context

- Точки `dispatch` сейчас:
  - `ChatController::maybeDispatchDiaryWrite` → `WriteDiaryJob` (порог `config('diary.write_threshold')` по минимальному курсору `last_diary_message_id` текущих участников);
  - `SceneController::close` → `SummarizeSceneDiaryJob`;
  - `SceneParticipantService::leave` → `WriteFinalDiaryJob` (вне транзакции, если `left_message_id !== null`).
- `MessageEditService::markDiaryStale` пишет `DB::table('character_diary_entries')->update(['is_stale' => true])` и логирует `diary.marked_stale`.
- ADR-001: `dispatch` только из `DiaryScheduler`; у таблицы один модуль-владелец.
- Задачи — `ShouldBeUnique`; воркер может стартовать раньше коммита, если `dispatch` вызван внутри транзакции.

## Scope

- `app/Diary/DiaryScheduler.php` (создать)
- `app/Diary/DiaryStaleMarker.php` (создать) или метод в существующем сервисе Diary
- `app/Http/Controllers/ChatController.php` — только `maybeDispatchDiaryWrite`
- `app/Http/Controllers/SceneController.php` — только строка `dispatch`
- `app/Scene/SceneParticipantService.php` — только `dispatch`
- `app/Messages/MessageEditService.php` — только `markDiaryStale`
- `tests/Feature/DiarySchedulerTest.php` (создать)

**Out of scope:** логика Job-классов и сервисов записи; новые Job-классы; остальная логика `ChatController`/`SceneController` (025b, 026a).

## Steps

1. Характеризация (`Queue::fake()`): после N-го сообщения при пороге N — `WriteDiaryJob` запущен ровно один раз, при N−1 — нет; закрытие сцены → `SummarizeSceneDiaryJob`; уход NPC из сцены с сообщениями → `WriteFinalDiaryJob`, без сообщений — нет; правка/удаление/восстановление сообщения → записи в диапазоне `is_stale = true`.
2. `DiaryScheduler` (`final`): `afterMessagePosted(Scene $scene): void` (порог), `sceneClosed(Scene $scene): void`, `participantLeft(SceneParticipant $p): void`. Внутри — `dispatch(...)->afterCommit()` (или `DB::afterCommit`), логирование `diary.write.dispatched` как сейчас.
3. `markDiaryStale` → `Diary`-метод `markStaleCovering(Message $message, string $reason): int`; `MessageEditService` его вызывает.
4. Заменить три точки `dispatch`.
5. Тест: `dispatch` внутри `DB::transaction`, которая откатывается, — задача не поставлена.

## Definition of Done

- [ ] `rg "::dispatch\(|dispatch\(" app` → только `app/Diary/DiaryScheduler.php`
- [ ] `rg "character_diary_entries" app/Messages app/Scene app/Http` → пусто
- [ ] тесты шагов 1 и 5 зелёные
- [ ] `sail artisan test` — зелёные; `sail pint --test` — чисто; phpstan — OK, baseline не вырос

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно: как обеспечен запуск после коммита.
