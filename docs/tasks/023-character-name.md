# 023 — Имя персонажа через связь Character::entity

- **Stage:** 2
- **Part:** 1
- **Status:** todo
- **Depends on:** 018
- **Risk:** med (N+1 при неправильной загрузке)
- **Разрешённые зависимости:** нет

## Goal

HTTP-слой и сервисы `Character` получают имя персонажа через связь `Character::entity` с eager load, без отдельных запросов `canonical_name`, и число SQL-запросов на ключевых маршрутах не растёт.

## Context

- Аудит §2.2: поиск `canonical_name` по id размножен. Сейчас: `CharacterSheetController` (5), `CharacterSheetReader` (3), `ChatController` (2), `CopilotController` (1), `SceneContextController` (1), `SceneParticipantController` (1), `Message::displayAuthor`, `CharacterIdentityService` (3).
- Общий PK: `characters.id = world_entities.id`. Связь: `belongsTo(WorldEntity::class, 'id', 'id')` или `hasOne(WorldEntity::class, 'id', 'id')` — выбрать и обосновать.
- **Ловушка:** связь без eager load даёт N+1 хуже текущих явных запросов (`whereIn(...)->pluck(...)` на список).
- Провайдеры Context и Writer/Summarizer формируют текст промпта — не трогаем, чтобы не менять поведение Copilot.

## Scope

- `app/Models/Character.php` — связь `entity()` и аксессор `name` (опционально, без запроса внутри)
- `app/Http/Controllers/{CharacterSheet,Chat,Copilot,SceneContext,SceneParticipant}Controller.php` — только замена поиска имени
- `app/Character/CharacterSheetReader.php`, `app/Character/CharacterIdentityService.php` — только чтение имени
- `app/Models/Message.php` — `displayAuthor` (сигнатуру не менять)
- `tests/Feature/CharacterNameQueryCountTest.php` (создать)

**Out of scope:** `app/Context/**`, `app/Diary/**`, форма JSON, перенос сериализации в Resources (020–021), `WorldEntityService`.

## Steps

1. **До правок — замер запросов.** Тест с `DB::listen` (или `DB::enableQueryLog()`): число SQL на `GET /characters/{id}`, `GET /characters`, `GET /messages`, `GET /scenes/{id}/participants` — при демо-подобных данных (5 NPC, 1 игрок, 20 сообщений). Числа записать в тест как верхнюю границу.
2. Добавить связь `entity()` и (при желании) аксессор `name` = `$this->entity?->canonical_name`. От N+1 защищает не аксессор, а eager load в месте выборки и тест шага 1. Глобальный `Model::preventLazyLoading()` не включать (вне Scope).
3. Заменить поиски имени в Scope на `with('entity')` / `load('entity')` / `loadMissing('entity')` в месте выборки.
4. Повторить замер шага 1.

## Definition of Done

- [ ] **DB::listen:** после изменений число SQL на `GET /characters/{id}` **не больше**, чем до (числа до/после — в отчёте); то же для трёх остальных маршрутов шага 1
- [ ] `rg "value\('canonical_name'\)|pluck\('canonical_name'" app/Http app/Character app/Models` → пусто
- [ ] форма JSON всех затронутых ответов не изменилась (существующие тесты без правок)
- [ ] `sail artisan test` — зелёные; `sail pint --test` — чисто; phpstan — OK, baseline не вырос

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно: таблица «маршрут → SQL до → SQL после»; выбранный тип связи и почему.
