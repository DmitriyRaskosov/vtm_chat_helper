# VtM Chat Helper

Чат для настольной RPG (Vampire: The Masquerade) с ИИ-copilot для рассказчика.

- **UI:** http://localhost:5173 (Vue 3, `cd frontend && npm run dev`)
- **API:** http://localhost:8080/api (Laravel Sail / Docker)

Первый зарегистрированный пользователь — рассказчик: генерирует черновики реплик НПС (`POST /api/copilot/drafts`) и отправляет их в общий чат (`npc_name` на `POST /api/messages`). Игроки видят только чат.

## Документация

Полная документация — Obsidian vault в [`docs/Home.md`](docs/Home.md). Открыть каталог `docs/` в Obsidian (**Open folder as vault**).

## Быстрый старт

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec laravel.test php artisan key:generate
docker compose exec laravel.test php artisan migrate
docker compose exec ollama ollama pull nomic-embed-text
docker compose exec ollama ollama pull qwen3:8b
```

Подробности: [docs/Development/Setup.md](docs/Development/Setup.md).
