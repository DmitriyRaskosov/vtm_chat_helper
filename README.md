# TRPG Chat Helper (VtM V20)

Чат для настольных ролевых игр (Vampire: The Masquerade V20) с Copilot-помощником рассказчика, ведением дневников NPC, бросками кубиков и генерацией боевых событий.

## 📋 Требования
* **Docker Desktop** (с поддержкой WSL2, если у вас Windows).
* **Node.js 20+** (для запуска фронтенда на хосте).
* **DeepSeek API Key:** Оплаченный ключ от DeepSeek API (без баланса Copilot не сможет генерировать текст).
* **GPU (Опционально):** Если у вас видеокарта NVIDIA, эмбеддер (Ollama) будет работать быстро через Docker. Если у вас Mac (M1/M2) или AMD, вам нужно удалить блок `deploy` из сервиса `ollama` в `docker-compose.yml` (тогда он будет работать на CPU, но медленнее).

## 🚀 Быстрый старт (всё в Docker)

1. Скопируйте `.env.example` в `.env` и заполните `DEEPSEEK_API_KEY`.
2. Запустите стек:
   docker compose up -d
3. Примените миграции и базовые сиды:
   docker compose exec laravel.test php artisan migrate:fresh --seed
4. **Загрузите демо-данные** (создаст пользователя `admin` и тестовую хронику):
   docker compose exec laravel.test php artisan demo:reset
5. Скачайте модель эмбеддера (один раз, займет ~1.2 ГБ):
   docker compose exec ollama ollama pull bge-m3
6. Запустите фронтенд (на хосте):
   cd frontend
   npm install
   npm run dev

**Адреса:**
* Frontend (Vite): `http://localhost:5173`
* Backend API: `http://localhost:8080/api`

**Как войти в систему:**
* Логин: `admin`
* Пароль: `admin`

> *Примечание: Если записи в дневниках NPC перестали генерироваться, проверьте, что контейнер `worker` запущен (`docker compose ps`), так как все фоновые задачи (очереди) работают через него.*

## 🛠 Стек
* **Backend:** PHP 8.4, Laravel 13, PostgreSQL 18 + pgvector, очереди в БД.
* **Frontend:** Vue 3, Vue Router, Vite 7 (Composition API).
* **AI / LLM:** DeepSeek API (текст), Ollama `bge-m3` (векторы памяти NPC).
* **Тестирование:** PHPUnit (660+ тестов), Larastan, Pint.

## 🎲 Основной функционал
* **Мульти-хроники:** Несколько независимых игровых миров, приглашения.
* **Copilot:** Генерация черновиков реплик для NPC с учетом лора, сцены и памяти.
* **Дневники:** Векторная память NPC (pgvector), автоматическая суммаризация сцен.
* **Кубы и бой:** Броски пулов d10 с учетом Силы Воли, генератор боевых событий.
* **UI:** Готический интерфейс (две темы), независимые плавающие панели инструментов, разделение IC/OOC.