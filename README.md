# TRPG Chat Helper

Чат для настольных RPG с Copilot-помощником рассказчика.
Сеттинг: Vampire: The Masquerade V20.

## Что умеет

- Чат сцен: мастер и игроки пишут от своих персонажей; IC/OOC, удаление и восстановление сообщений.
- Copilot (мастер): синопсис → **3** варианта реплики NPC через **DeepSeek API**.
- Броски кубов и лог боя. Рассказчик может описать исход уже посчитанной атаки и защиты (кнопка «Описать событие» у лога боя, место временное): DeepSeek пишет прозу, в чат она сама не отправляется.
- Дневник NPC: автозапись (L0/L1), семантический поиск по записям в контексте Copilot; просмотр, правка, удаление, пересоздание.
- Хроники, сессии, сцены, участники, контекст сцены.
- Канон V20: секты, кланы, дисциплины, статьи лора из markdown.
- Лист персонажа: клан, секта, дисциплины, биография, черты.

Пользователь с ролью storyteller создаётся при регистрации, если storyteller ещё нет. В local после `migrate --seed` также есть демо-аккаунт (см. ниже).

Подробный scope и вехи: [`docs/Scope.md`](docs/Scope.md).

## Стек

- **Backend:** PHP 8.4, Laravel 13, Sanctum, PostgreSQL 18 + **pgvector**, очереди в БД
- **Frontend:** Vue 3, Vue Router, Vite 7 (dev на порту **5173**)
- **Генерация текста:** [DeepSeek API](https://platform.deepseek.com) (Copilot, описание события, записи дневника)
- **Эмбеддинги дневника:** [BGE-M3](https://ollama.com/library/bge-m3) через сервис compose **Ollama на GPU** (модель **bge-m3**, dense-вектор **1024** измерения)
- **Контейнеры:** Docker Desktop, Laravel Sail (`laravel.test`, `pgsql`, `worker`, `ollama`)
- **Node.js** 20+

## Требования

- Docker Desktop с NVIDIA GPU, доступным контейнерам
- Node.js 20+
- API-ключ DeepSeek

## Запуск локально

### 1. Эмбеддинги (Ollama в compose, GPU)

Сервис `ollama` — образ `ollama/ollama` с тегом, зафиксированным в `compose.yaml` (не `latest`). Он поднимается вместе с остальными контейнерами и использует NVIDIA GPU. Backend ходит в него по `OLLAMA_URL` (по умолчанию `http://ollama:11434`). Порт 11434 на хост не публикуется.

Модель **bge-m3**: мультиязычные эмбеддинги, dense-вектор **1024** измерения (как в БД). Контекст входа — до 8192 токенов. После старта контейнеров её один раз скачивают в том сервиса (команда в шаге 2).

### 2. Backend

```bash
cp .env.example .env
# заполнить DEEPSEEK_API_KEY; при необходимости OLLAMA_URL / EMBEDDING_MODEL

docker compose up -d
# или: ./vendor/bin/sail up -d

docker compose exec ollama ollama pull bge-m3

docker compose exec laravel.test php artisan key:generate
docker compose exec laravel.test php artisan migrate:fresh --seed
```

Сервис **`worker`** обрабатывает очередь (запись и сводки дневника). Без него дневник не обновится после сообщений.

API: http://localhost:8080/api  
Health: http://localhost:8080/up

### 3. Frontend

```bash
cd frontend
npm install
npm run dev
```

UI: http://localhost:5173 (прокси `/api` → backend на 8080)

## Artisan-команды

| Команда | Назначение |
|---------|------------|
| `php artisan canon:import-lore` | Импорт `.md` из `resources/canon/lore/` (уже вызывается из `DatabaseSeeder`) |
| `php artisan canon:import-lore --path=...` | Импорт из другой директории |
| `php artisan demo:reset` | Очистить данные игры (хроника, сцены, сообщения, дневник, персонажи), **канон и пользователи остаются**, затем `DemoSeeder` |
| `php artisan diary:reembed` | Пересчитать эмбеддинги всех записей дневника (после смены модели) |
| `php artisan diary:reembed --dry-run` | Пробный прогон без записи в БД |

В Docker префикс: `docker compose exec laravel.test php artisan …`

### Демо-данные

`php artisan migrate:fresh --seed` (или `db:seed`) загружает канон (`Canon*Seeder`), пустую хронику «Моя первая хроника» (`ChronicleSeeder`) и импорт лора (`canon:import-lore`). **DemoSeeder при этом не запускается.**

Демо-стенд: `php artisan demo:reset` — TRUNCATE данных игры (хроника, сцены, персонажи, сообщения, дневник), канон и пользователи остаются, затем `DemoSeeder` (логин **`admin`** / **`admin`**, «Demo Chronicle», «Demo Session», сцена «Встреча на пустыре», 5 NPC, без сообщений в чате).

## Тесты

```bash
composer test
# или: docker compose exec laravel.test php artisan test
```

## Структура

- `app/` — backend (Laravel)
- `app/Diary/`, `app/Rag/` — дневник и эмбеддинги
- `frontend/` — SPA (Vue)
- `resources/canon/lore/` — markdown канона
- `resources/prompts/` — промпты Copilot и дневника
- `database/seeders/` — канон и `DemoSeeder`
- `docs/Scope.md` — живой документ по границам проекта

## Переменные окружения

См. `.env.example`. Основное:

**DeepSeek (обязательно для Copilot и LLM-текста дневника)**

- `DEEPSEEK_API_KEY`
- `LLM_DRIVER=deepseek`
- `DEEPSEEK_CHAT_MODEL=deepseek-chat` (по умолчанию)

**PostgreSQL (Sail)**

- `DB_HOST=pgsql`, `DB_USERNAME=sail`, `DB_PASSWORD=password`

**Эмбеддинги (BGE-M3, сервис compose `ollama` на GPU)**

- `EMBEDDING_DRIVER=ollama`
- `OLLAMA_URL=http://ollama:11434`
- `EMBEDDING_MODEL=bge-m3`
- `EMBEDDING_DIMENSIONS=1024`
- `EMBEDDING_TIMEOUT=60`

**Дневник и Copilot**

- `DIARY_WRITE_THRESHOLD=15` — сообщений до новой записи L0
- `QUEUE_CONNECTION=database` — нужен работающий `worker`
- `COPILOT_HISTORY_LIMIT`, `COPILOT_DRAFT_COUNT` — см. `config/copilot.php`
