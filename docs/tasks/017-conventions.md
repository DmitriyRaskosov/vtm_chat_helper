# 017 — Конвенции: #[Fillable], DI через конструктор, язык комментариев

- **Stage:** 1
- **Status:** blocked — нужно решение пользователя по языку комментариев (см. Context)
- **Depends on:** 016
- **Risk:** low
- **Разрешённые зависимости:** нет

## Goal

Все модели объявляют поля атрибутом `#[Fillable]`, `DiaryEntryController` получает зависимости через конструктор, комментарии в `app/` на одном языке.

## Context

- Аудит §3: канонические модели используют `protected $fillable`, новые — `#[Fillable]`; `DiaryEntryController` внедряет сервисы в методы и берёт `app(EmbeddingProvider::class)`; комментарии смешаны (русские и английские).
- `.cursor/rules/backend.mdc` §Модели, §Классы.
- **Требует решения:** язык комментариев. План предлагает русский. До решения шаг 4 не выполняется.
- `Message::booted` с `app(TokenEstimator::class)` переводится на Observer в задаче 029 — здесь не трогать.

## Scope

- `app/Models/Canon*.php` и прочие модели с `protected $fillable`
- `app/Http/Controllers/DiaryEntryController.php` — только способ внедрения зависимостей
- `app/**/*.php` — только текст комментариев (шаг 4)

**Out of scope:** логика `DiaryEntryController` (регенерация и эмбеддинг переносятся в задаче 027); `Message::booted`; docblock-аннотации типов (`@param`, `@return`, `@var`) — их не переводить.

## Steps

1. `rg "protected \$fillable" app` → для каждой модели заменить на `#[Fillable([...])]` с тем же списком полей, как в существующих моделях с атрибутом.
2. `DiaryEntryController`: все сервисы и `EmbeddingProvider` — в конструктор с property promotion; `app(...)` удалить. Публичные сигнатуры action меняются только в части внедряемых параметров.
3. `rg "app\(|resolve\(" app --glob '!app/Providers/**'` — перечислить оставшееся в отчёте (ожидаемо только `Message::booted`).
4. (после решения) Перевести комментарии на выбранный язык. Комментарии, пересказывающие код, удалить.
5. Починить 20 × generics.notSubtype в PHPDoc фабрик моделей
  (типовой паттерн: @extends Factory<Model> → Factory<Model, Model>).
  Это отдельная категория, не связана с World/enum, но входит в финальный
  baseline ≤50.

## Definition of Done

- [ ] `rg "protected \$fillable" app` → пусто
- [ ] `rg "app\(|resolve\(" app --glob '!app/Providers/**'` → только `app/Models/Message.php` (задача 029)
- [ ] тесты Diary CRUD/regenerate зелёные без правок
- [ ] `sail artisan test` — зелёные; `sail pint --test` — чисто; phpstan — без новых ошибок

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно: число переведённых/удалённых комментариев по папкам.
