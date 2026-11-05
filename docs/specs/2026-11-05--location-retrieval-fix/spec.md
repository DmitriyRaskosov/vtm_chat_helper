# Spec: Фикс Retrieval для Графа Локаций

## 1. Контекст и Цель
Эмбеддинги на коротких текстах плохо справляются с поиском конкретных объектов. Нужно внедрить Hybrid Retrieval (Vector + Keyword) и увеличить лимит выборки.

## 2. Acceptance Criteria (EARS)
*   **AC-1 (UI):** Никаких новых полей. Рассказчик пишет весь контекст в существующее поле `action_description`.
*   **AC-2 (Vector):** THE system SHALL искать по pgvector с лимитом **5** узлов (минимальный порог сходства 0.45).
*   **AC-3 (Keyword):** THE system SHALL извлекать слова из `action_description` (длиной >3 символов) и искать совпадения по полю `name` в `location_nodes` (LIKE) с лимитом **5**.
*   **AC-4 (Merge):** THE system SHALL объединять результаты Vector и Keyword, удаляя дубликаты (по ID узла).
*   **AC-5 (General):** Корневые узлы и их прямые дети по-прежнему добавляются в промпт (как General Environment).
*   **AC-6 (Fallback):** Если узлов нет (ни General, ни Specific), THE system SHALL использовать сырой текст `environment_details`.

## 3. Post-Implementation Notes
- **Status:** Completed (2026-11-05).
- **Причина фикса:** чистый vector-поиск плохо находил короткие узлы (cosine ~0.47 на релевантных, разница с шумом всего 0.15).
- **Решение:** Hybrid Retrieval — vector (top-5, порог 0.45) + keyword (name ILIKE по префиксам слов >3 символов с обрезкой 2 последних букв для падежей). Результаты склеиваются, дубли по id удаляются.
- **Лимит:** `copilot.event_location_retrieval_limit` увеличен с 3 до 5.
- **Fallback:** сохранен — если Specific пуст, идем в General + сырой `environment_details`.
- **Отклонения от spec:** plan.md и tasks.md не создавались (задача короткая, по решению). Тесты `EventGeneratorLocationTest` — 4 passed. Pint пройден. Полный `php artisan test` и сборка фронта — на этапе общего DoD (см. ниже).