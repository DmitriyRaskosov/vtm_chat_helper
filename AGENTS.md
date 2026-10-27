# AGENTS.md — карта проекта для ИИ

Читай этот файл первым. Подробности — в `docs/ARCHITECTURE.md`, границы продукта — в `docs/Scope.md`.
При расхождении документов приоритет такой: задача из `docs/tasks/` → `docs/Scope.md` → этот файл → `.cursor/rules/*.mdc`.

## Суть продукта

Чат для настольной ролевой игры VtM V20 с Copilot-помощником рассказчика.
Рассказчик пишет синопсис, Copilot (DeepSeek, два прохода) разворачивает его в 3 варианта реплики NPC.
NPC ведут долговременный дневник (L0 — записи по ходу сцены, L1 — сводка сцены), он подмешивается в контекст Copilot.
Полной боёвки и рулбука нет; есть упрощённые броски кубов, лог боя и описание уже посчитанного встречного действия (`EventGeneratorService`: один вызов DeepSeek, без сборки контекста Copilot). Форма «Описать событие» — вкладка «События» правой панели. В промпт события уходят `situation` и `environment_details`; в контекст Copilot — только `situation`. Активные состояния персонажа (кровь, здоровье, временная воля на листе) есть. Запас крови и уровень здоровья подмешиваются в контекст Copilot (`CharacterStateProvider`). Чат — оболочка Gothic v3: сайдбар, сцена, правая панель; IC слева и OOC справа; темы `theme-gothic` и `theme-blood` (переключатель в сайдбаре, `localStorage` `app-theme`). У рассказчика библиотека шаблонов локации (`chronicle_locations`, `ChronicleLocationService`) в модалке «Настроить локацию» и во вкладке «Локации» Мастерской. «Управление сценой» — отдельная модалка. «Мастерская» (`/c/{id}/workshop`, только рассказчик): персонажи, локации, дневники.
Сейчас одна хроника на инстанс; multi-chronicle — этап 3 (ADR-007).

## Стек

- Backend: PHP 8.4, Laravel 13, PostgreSQL 18 + pgvector, Sanctum. Тесты — PHPUnit 12 (не Pest).
- Frontend: Vue 3 (`<script setup>`), Vite, axios. Без Pinia. Папка `frontend/`.
- LLM: DeepSeek (`deepseek-chat`). Эмбеддинги: Ollama `bge-m3` в сервисе compose `ollama` на GPU (`http://ollama:11434`), вектор 1024.
- Окружение: Laravel Sail / Docker Compose, сервисы `laravel.test`, `worker`, `pgsql`, `ollama`.

## Команды

Всё PHP — только в контейнере. Хостовый `php` не используется.
`sail` ниже означает `./vendor/bin/sail` (bash/WSL) или `docker compose exec laravel.test` (PowerShell).

| Что | Команда |
|---|---|
| Тесты | `sail artisan test` |
| Один тест | `sail artisan test --filter=DiaryTest` |
| Стиль (проверка) | `sail pint --test` (в PowerShell: `docker compose exec laravel.test ./vendor/bin/pint --test`) |
| Стиль (только изменённое) | `sail pint --dirty` |
| Статанализ (после задачи 002) | `sail php vendor/bin/phpstan analyse` |
| Пересоздать БД | `sail artisan migrate:fresh --seed` |
| Сброс демо | `sail artisan demo:reset` |
| Сборка фронта | `cd frontend; npm run build` (на хосте) |

## Карта `app/`

- `Http/Controllers` — тонкие контроллеры (цель; сейчас Chat и CharacterSheet толстые, см. этап 2).
- `Http/Requests` — валидация входа. `Http/Resources` — сериализация JSON (сейчас только `UserResource`).
- `Models` — Eloquent: связи, casts, scopes. `Enums` — все перечисления домена.
- `Character/` — сервисы листа персонажа (идентичность, место, черты, биография, `CharacterStateService`).
- `Scene/` — сцены, участники, контекст сцены (`SceneContextService` с оптимистичной блокировкой), описание события (`EventGeneratorService`).
- `Messages/` — создание и редактирование сообщений.
- `Diary/` — дневник NPC: запись L0, сводка L1, retrieval, парсер метаданных.
- `Context/` — сборка промпта Copilot: `ContextAssembler`, провайдеры секций, `TokenEstimator`. Потребитель остальных модулей (`MessageObserver` — исключение, ADR-001).
- `Llm/` — `ChatProvider`, DeepSeek, `NpcCopilotService` (два прохода: topics → reply). Тот же `ChatProvider` вызывает `EventGeneratorService` для прозы боя.
- `Rag/` — `EmbeddingProvider`, Ollama.
- `Prompt/` — `PromptRepository`, промпты лежат в `resources/prompts/*.md`.
- `World/` — `world_entities` как identity персонажей и локаций; связи только на чтение (ADR-002).
- `Jobs/` — три задачи дневника. `Console/Commands` — `demo:reset`, `canon:import-lore`, `diary:reembed`.

## Карта `frontend/src`

- `views/` — Login, Register, Chat, CharacterList, CharacterSheet, Diary, Workshop (только рассказчик: персонажи, локации, дневники).
- `components/chat`, `components/sheet`, `components/layout`, `components/workshop` — компоненты экранов. Окна инструментов чата — `FloatingWindow` (`useDraggable`). Модалки сцены — `SceneToolbar` (локация) и `SceneManagementModal`.
- `composables/` — `useSceneSession`, `useChatMessages`, `useCharacterSheet`, `useChatPanels`, `useDraggable`, `useTheme`. Новая логика — сюда.
- `auth.js` — единственный экземпляр axios и состояние пользователя. `router.js` — маршруты.
- `styles.css` — глобальные стили и темы (`body.theme-gothic`, `body.theme-blood`). Цвета новых экранов — через CSS-переменные. Файл по-прежнему один, его не дробят.

## Куда класть новый код

| Что | Где |
|---|---|
| Валидация входа | FormRequest (`rules()`); `authorize()` не используется для новых запросов |
| Бизнес-логика, транзакции | сервис модуля (`app/<Module>/`) |
| Инварианты, которым нужна БД | сервис + доменное исключение; если можно — ограничение PostgreSQL |
| Сериализация | `JsonResource` в `Http/Resources` |
| Авторизация | Policy (вводится в задаче 030; до неё — существующие механизмы, не плодить новые) |
| Запуск задач дневника | только `Diary/DiaryScheduler` (вводится в задаче 025) |
| Промпты LLM | `resources/prompts/` через `PromptRepository` |
| Параметры LLM | `config/copilot.php`, `config/context.php`, `config/diary.php` |
| Схема БД | существующие миграции (до первого релиза, ADR-004) |
| Справочные данные | Seeder-классы |

## Запрещено без явного указания в задаче

- Новые зависимости (composer, npm).
- `dispatch()` вне `Diary/DiaryScheduler`; новые Job-классы.
- Правки `ContextAssembler`, новые провайдеры контекста.
- `declare(strict_types=1)`, Pest-синтаксис.
- `abort()` и `HttpException` в сервисах и моделях; `app()`/`resolve()` вне service providers.
- Запись в `canon_*` из кода приложения (только сидеры).
- Изменение формы JSON-ответов, которые читает фронт.
- Коммиты.

## Работа по задачам

Задачи — `docs/tasks/NNN-slug.md`, шаблон — `docs/tasks/000-template.md`.
Порядок выполнения и формат отчёта — `.cursor/rules/task-execution.mdc`.
Одна сессия — одна задача. Файлы вне Scope задачи не трогаются.

## Документы

- `docs/ARCHITECTURE.md` — слои, модель данных, потоки, авторизация, ошибки, что не вводим.
- `docs/Scope.md` — что в продукте есть и чего нет.
- `docs/adr/` — архитектурные решения (001–007).
- `docs/tasks/` — атомарные задачи миграции.
- `docs/audit-2026-10-16.md`, `docs/plan-2026-10-16.md` — аудит и план миграции.
- `docs/Naming.md` — русские названия дисциплин.
