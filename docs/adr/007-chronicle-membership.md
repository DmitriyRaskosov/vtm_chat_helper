# ADR-007. Членство пользователей в хрониках (multi-chronicle)

- **Status:** Draft — утверждается в начале этапа 3, после завершения этапа 2
- **Date:** 2026-10-16

<!-- TODO: перед утверждением сверить с фактическим состоянием Policies (030) и CurrentChronicle (019). -->

## Context

Сейчас инстанс рассчитан на одну хронику: `Chronicle::resolveId` берёт единственную, роль пользователя глобальная (`users.role`), `characters.user_id` уникален глобально (один персонаж на пользователя во всём инстансе), членство в хронике не проверяется (аудит §2.4). Цель — несколько групп на одном инстансе.

## Decision (черновик)

1. Таблица `chronicle_members`: `chronicle_id`, `user_id`, `role` (enum `ChronicleRole`: storyteller, player), `joined_at`, `left_at`; `unique(chronicle_id, user_id)`.
2. Таблица `chronicle_invitations`: `chronicle_id`, `token_hash`, `role`, `created_by`, `expires_at`, `max_uses`, `uses`, `revoked_at`. Вступление по ссылке-коду, без почты.
3. `characters`: глобальный unique по `user_id` заменяется частичным unique `(chronicle_id, user_id) WHERE character_type = 'player' AND is_active` и составным FK `(chronicle_id, user_id) → chronicle_members`.
4. `users.role` удаляется. Хронику создаёт любой пользователь и становится её рассказчиком.
5. Маршруты-коллекции переезжают под `/api/chronicles/{chronicle}/...` (messages, characters, game-sessions/active). Маршруты по модели (`/scenes/{scene}`, `/characters/{character}`, `/messages/{message}`, `/diary-entries/{entry}`) остаются плоскими; Policy берёт хронику из модели.
6. Фронт: выбор хроники, composable `useCurrentChronicle` (без Pinia), роль из членства.

## Открытые вопросы

- <!-- TODO --> Чужая хроника: 403 или 404 (не раскрывать существование)?
- <!-- TODO --> Может ли рассказчик одной хроники быть игроком в другой одновременно — да по модели; нужен ли UI-переключатель роли?
- <!-- TODO --> Что происходит с персонажем игрока при `left_at` — архив или остаётся активным?
- <!-- TODO --> Несколько рассказчиков в одной хронике — разрешать?
- <!-- TODO --> Миграция демо: `DemoSeeder` и `demo:reset` создают членства (задача 043).

## Consequences (ожидаемые)

- Меняется реализация Policies (ADR-003) и `CurrentChronicle`, но не контроллеры.
- Матрица доступа «чужая хроника × каждый маршрут» обязательна (задача 044).
