# Plan: Фикс Retrieval для Графа Локаций

## 1. Backend (Laravel)

### 1.1 Поиск объектов
*   Вход без новых полей: текст действия уже лежит в `action_description`.
*   **Vector:** pgvector по `location_nodes.embedding`, лимит 5, минимальное сходство 0.45.
*   **Keyword:** слова из `action_description` длиннее 3 символов; поиск по `location_nodes.name` через `ILIKE`. Для падежей у слова обрезаются 2 последние буквы. Лимит 5.
*   **Merge:** объединить vector и keyword, дубликаты удалить по id узла.
*   **General:** корневые узлы и их прямые дети по-прежнему добавляются как общее окружение.
*   **Fallback:** если Specific пуст — General и сырой `environment_details`. Если узлов нет вовсе — сырой `environment_details`.
*   Лимит `copilot.event_location_retrieval_limit` увеличен с 3 до 5.

### 1.2 Тесты
*   `EventGeneratorLocationTest`.

## 2. Риски
*   Обрезка двух букв даёт ложные совпадения коротких слов.
*   Порог 0.45 близок к шуму: на коротких узлах cosine релевантных был около 0.47, зазор с шумом около 0.15.
*   **Вынесено из критериев приёмки:** pgvector (топ-5, порог 0.45), keyword по `location_nodes.name` (`ILIKE`, слова длиннее 3 символов, обрезка 2 букв), склейка без дублей по id, General = корень + прямые дети, fallback на `environment_details`, конфиг `copilot.event_location_retrieval_limit`.
