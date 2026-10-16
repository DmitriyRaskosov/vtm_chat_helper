# 004 — Страховочная сетка тестов для LLM-части дневника

- **Stage:** 1
- **Status:** todo
- **Depends on:** 003
- **Risk:** low
- **Разрешённые зависимости:** нет

## Goal

Writer, Summarizer и семантическая ветка retrieval покрыты тестами, которые ловят регрессии вроде дефекта `writeFinalForNpc`.

## Context

- Аудит §2.1.1 (условие `int !== 'npc'` — исправлено коммитом `51a4a21`), §2.6 (Writer/Summarizer не тестируются, семантическая ветка не выполняется).
- `app/Diary/DiaryWriterService.php`: `writeForScene`, `writeFinalForNpc`; `DiarySummarizerService`; `DiaryRetrievalService`.
- Фейки из задачи 003.

## Scope

- `tests/Feature/DiaryTest.php` или новые `tests/Feature/Diary*Test.php`
- `tests/Support/*` — только дополнения хелперов

**Out of scope:**
- `app/` целиком. Если тест вскрывает баг — тест помечается `markTestSkipped('bug: …')` и баг описывается в отчёте, код не правится.

## Steps

1. `writeForScene`:
   - меньше порога новых сообщений → запись не создаётся, курсор не двигается;
   - порог достигнут → по одной L0-записи на каждого NPC сцены, `last_diary_message_id` участника сдвинут на последнее обработанное сообщение;
   - ошибка LLM для одного NPC → остальные NPC получают запись.
2. `writeFinalForNpc`:
   - NPC → запись создана;
   - персонаж-игрок → `false`, запись не создана.
3. Summarizer при закрытии сцены: L0-хвост дописан (сообщения после курсора), L1-сводка создана.
4. Retrieval, семантическая ветка: непустой запрос + эмбеддинги из фейка → в результате есть запись, выбранная по близости, а не только последняя.
5. Проверить чувствительность: локально откатить фикс `51a4a21` (`$character->character_type !== CharacterType::Npc` → `(int) $character->character_type->value !== 'npc'`), убедиться, что тест п. 2 падает, вернуть фикс. Откат не оставлять в диффе.

## Definition of Done

- [ ] все сценарии шагов 1–4 покрыты отдельными тест-методами
- [ ] при откате фикса `51a4a21` тест `writeFinalForNpc` для NPC падает (подтвердить в отчёте выводом)
- [ ] `git diff app/` → пусто
- [ ] `sail artisan test` — зелёные; `sail pint --test` — чисто
- [ ] тесты не обращаются к реальному DeepSeek и Ollama

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно:
- вывод падающего теста при откате фикса;
- список найденных багов (если есть `markTestSkipped`).
