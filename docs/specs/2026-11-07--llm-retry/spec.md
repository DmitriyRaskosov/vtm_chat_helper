# Spec: Retry при finish_reason=length

**Status:** Completed
**Date:** 2026-11-07

## 1. Контекст и Цель
При генерации длинных ответов LLM иногда упирается в лимит `max_tokens` и обрывает генерацию на полуслове (`finish_reason = 'length'`). 
Это ломает дневники (NPC теряет память) и Copilot (JSON становится невалидным). 
Цель — внедрить механизм однократного Retry (спасательный жилет) для обоих сценариев, без увеличения базовых лимитов токенов.

## 2. User Stories
*   **US-1:** Как система, я хочу автоматически дописывать обрезанный текст дневника, чтобы память NPC была полной.
*   **US-2:** Как рассказчик, я хочу видеть в интерфейсе дневника, что запись была обрезана (и восстановление не помогло), чтобы я мог вручную пересоздать её.
*   **US-3:** Как рассказчик, я хочу, чтобы Copilot сам исправлял сломанный JSON, если модель превысила лимит токенов, прося её быть короче.

## 3. Acceptance Criteria (EARS)
*   **AC-1 (Дневник - Детекция):** WHEN LLM возвращает ответ для дневника с finish_reason = 'length', THE system SHALL инициировать одну повторную попытку (Retry).
*   **AC-2 (Дневник - Логика):** THE system SHALL отправлять модели последние 200 символов сгенерированного текста с инструкцией «Продолжи текст с этого места».
*   **AC-3 (Дневник - Ограничение):** IF после повторной попытки модель снова возвращает finish_reason = 'length', THE system SHALL сохранить обрезанный текст, записать предупреждение в лог и установить флаг is_truncated = true.
*   **AC-4 (Дневник - UI):** WHEN запись имеет флаг is_truncated = true, THE system SHALL отображать бейдж «ОБРЕЗАНО» в шапке этой записи в интерфейсе дневника.
*   **AC-5 (Copilot - Детекция):** WHEN LLM возвращает ответ для Copilot с finish_reason = 'length' (или JSON не парсится из-за обрыва), THE system SHALL инициировать одну повторную попытку.
*   **AC-6 (Copilot - Логика):** THE system SHALL отправлять модели оригинальный промпт с добавленной инструкцией: «Your previous answer was too long. Make drafts shorter and strictly return valid JSON.».
*   **AC-7 (Copilot - Ограничение):** IF повторная попытка тоже возвращает finish_reason = 'length' (или невалидный JSON), THE system SHALL выбросить LlmResponseInvalidException (502), чтобы пользователь увидел понятную ошибку.

## 4. Out of Scope
*   Увеличение лимитов `max_tokens`.
*   Автоматическое пересоздание обрезанных записей дневника (только ручное через UI).

## 5. Post-Implementation Notes

Реализован механизм Retry для дневников и Copilot при `finish_reason='length'`.

Для дневников: трейт `RetriesLlmOutput` — одна повторная попытка с хвостом последних 200 символов и инструкцией «Продолжи текст с этого места»; склейка ответов; при повторном `length` — `is_truncated = true` и предупреждение в лог (`diary.llm.truncated_after_retry`). Результат LLM возвращается через DTO `DiaryLlmCallResult`.

Для Copilot: в `NpcCopilotService::drafts` один retry с инструкцией *Your previous answer was too long. Make drafts shorter and strictly return valid JSON.*; при повторной неудаче — `LlmResponseInvalidException` (HTTP 502).

UI: бейдж «ОБРЕЗАНО» (класс `.stale-badge`) в `DiaryView.vue` и во вкладке дневников в Мастерской (`DiaryManagement.vue`); поле `is_truncated` в API (`DiaryEntryListResource`).

Пересоздание записи (`regenerateEntry` / `regenerateSummary`) обновляет `is_truncated` в БД; при успешной генерации без обрезки флаг сбрасывается в `false`.