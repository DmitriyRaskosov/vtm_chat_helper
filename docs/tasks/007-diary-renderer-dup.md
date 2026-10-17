# 007 — Дубль класса DiaryRenderer

- **Stage:** 1
- **Status:** done
- **Depends on:** 001
- **Risk:** low
- **Разрешённые зависимости:** нет

## Goal

Класс `App\Context\DiaryRenderer` объявлен ровно в одном файле — `app/Context/DiaryRenderer.php`.

## Context

- Аудит §2.1.4: `app/Diary/DiaryRenderer.php` (44 строки) объявляет `App\Context\DiaryRenderer`, по PSR-4 не загружается; рабочий — `app/Context/DiaryRenderer.php` (41 строка).
- Импортируют только `App\Context\DiaryRenderer`: `DiaryProvider`, `DiaryPreviewService`.

## Scope

- `app/Context/DiaryRenderer.php` — только если в `Diary/`-версии есть логика, которой нет в `Context/`
- `app/Diary/DiaryRenderer.php` (удалить)

**Out of scope:** перенос `DiaryPreviewService` (задача 029), изменение формата рендера.

## Steps

1. Сравнить файлы построчно (`git diff --no-index app/Context/DiaryRenderer.php app/Diary/DiaryRenderer.php`).
2. Если отличие только в форматировании/комментариях — удалить `app/Diary/DiaryRenderer.php`.
3. Если в `Diary/`-версии есть иное поведение — **остановиться** и показать дифф пользователю: какая версия правильная, решает он.
4. `sail composer dump-autoload`.

## Definition of Done

- [ ] `rg "class DiaryRenderer" app` → одно вхождение, `app/Context/DiaryRenderer.php`
- [ ] `sail composer dump-autoload` — без предупреждений `Ambiguous class resolution`
- [ ] `sail artisan test --filter=Diary` и `sail artisan test` — зелёные; `sail pint --test` — чисто

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно: вывод сравнения из шага 1.
