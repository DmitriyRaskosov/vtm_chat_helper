# TRPG Chat Helper (VtM V20)

Чат для настольных ролевых игр (Vampire: The Masquerade V20) с Copilot-помощником рассказчика, ведением дневников NPC, бросками кубиков и генерацией боевых событий.

## 🚀 Быстрый старт (всё в Docker)

Проект полностью контейнеризирован. Эмбеддер (Ollama + GPU) работает внутри Docker.

1. Убедитесь, что Docker Desktop запущен с поддержкой WSL2 и GPU (NVIDIA).
2. Скопируйте `.env.example` в `.env` и заполните `DEEPSEEK_API_KEY`.
3. Запустите стек: `docker compose up -d`
4. Примените миграции и сиды: `docker compose exec laravel.test php artisan migrate:fresh --seed`
5. Скачайте модель эмбеддера (один раз): `docker compose exec ollama ollama pull bge-m3`
6. Запустите фронтенд (на хосте): `cd frontend && npm install && npm run dev`

**Адреса:**
* Frontend (Vite): `http://localhost:5173`
* Backend API: `http://localhost:8080/api`

## 🛠 Стек
* **Backend:** PHP 8.4, Laravel 13, PostgreSQL 18 + pgvector, очереди в БД (воркер).
* **Frontend:** Vue 3, Vue Router, Vite 7 (Composition API).
* **AI / LLM:** DeepSeek API (текст), Ollama `bge-m3` (векторы памяти NPC, Docker с GPU).
* **Тестирование:** PHPUnit (660+ тестов), Larastan, Pint.

## 🎲 Основной функционал
* **Мульти-хроники:** Несколько независимых игровых миров, приглашения.
* **Copilot:** Генерация черновиков реплик для NPC с учетом лора, сцены и памяти.
* **Дневники:** Векторная память NPC (pgvector), автоматическая суммаризация сцен.
* **Кубы и бой:** Броски пулов d10 с учетом Силы Воли, генератор боевых событий на основе физики локации.
* **UI:** Готический интерфейс (две темы), независимые плавающие панели инструментов, разделение IC/OOC.
