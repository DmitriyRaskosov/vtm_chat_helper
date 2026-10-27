# ADR-007. Членство пользователей в хрониках (multi-chronicle)

- **Status:** Accepted
- **Date:** 2026-10-16

## Context

До этапа 3 инстанс был рассчитан на одну хронику: `Chronicle::resolveId` брал единственную, роль пользователя была глобальной (`users.role`), `characters.user_id` был уникален глобально (один персонаж на пользователя во всём инстансе), членство в хронике не проверялось (аудит §2.4). Цель — несколько групп на одном инстансе.

## Decision

1. Таблица `chronicle_members`: `chronicle_id`, `user_id`, `role` (enum `ChronicleRole`: storyteller, player), `joined_at`, `left_at`; `unique(chronicle_id, user_id)`.
2. Таблица `chronicle_invitations`: `chronicle_id`, `token_hash`, `role`, `created_by`, `expires_at`, `max_uses`, `uses`, `revoked_at`. Вступление по ссылке-коду, без почты.
3. `characters`: глобальный unique по `user_id` заменяется частичным unique `(chronicle_id, user_id) WHERE character_type = 'player' AND is_active` и составным FK `(chronicle_id, user_id) → chronicle_members`.
4. `users.role` удаляется. Хронику создаёт любой пользователь и становится её рассказчиком.
5. Маршруты-коллекции переезжают под `/api/chronicles/{chronicle}/...` (messages, characters, game-sessions/active). Маршруты по модели (`/scenes/{scene}`, `/characters/{character}`, `/messages/{message}`, `/diary-entries/{entry}`) остаются плоскими; Policy берёт хронику из модели.
6. Фронт: выбор хроники, composable `useCurrentChronicle` (без Pinia), роль из членства.

## Открытые вопросы

Задачи 035–044 их не закрыли. Ниже фактическое поведение, без нового решения.

- Чужая хроника: 403 или 404 не унифицировано. `GET /api/chronicles/{chronicle}` для не-участника — 404 (`ChronicleService::show` → `ChronicleNotFoundException`, задача 036). `ChroniclePolicy::view` на группе коллекций и `ScenePolicy::view` — 403 (задача 044). Матрица доступа принимает оба кода.
- Персонаж игрока при `left_at`: эндпоинта «выйти из хроники» нет, поведение не задано (архив или остаётся активным).
- Несколько рассказчиков в одной хронике: схема и приглашение с ролью `storyteller` это позволяют, отдельного запрета нет. Переключателя роли в UI нет.
- Рассказчик одной хроники может быть игроком другой: модель позволяет. Отдельного переключателя роли нет; роль в UI берётся из членства выбранной хроники (задача 042d).

## Consequences

- Policies берут роль из членства (`User::isStorytellerIn`). Колонки `users.role` нет.
- `CurrentChronicle` читает хронику из параметра маршрута `{chronicle}`.
- Матрица «чужая хроника × маршрут» покрыта тестами (задача 044). Разделение 403 и 404 остаётся открытым вопросом выше.
- Поле `chronicle_id` в ответе 409 на принятие приглашения выставляет контроллер, не общий маппинг исключений.
