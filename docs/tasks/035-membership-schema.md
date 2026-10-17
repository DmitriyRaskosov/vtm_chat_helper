# 035 — Схема членства и приглашений

- **Stage:** 3
- **Status:** todo
- **Depends on:** 034, 018
- **Risk:** high (меняет схему, затрагивает Character)
- **Part:** 4
- **Разрешённые зависимости:** нет

## Goal

В базе есть членство и приглашения в хронику, а у игрока не больше одного активного персонажа на хронику, и этот персонаж ссылается на участника хроники.

## Context

- План `docs/plan-2026-10-16.md` §«Этап 3», ADR-007 п. 1–3. Новые файлы миграций не создавать (ADR-004): таблицы добавляются в существующие файлы, БД пересоздаётся `migrate:fresh`.
- Сейчас `characters.user_id` — глобальный `unique()` (`database/migrations/2026_09_11_000014_create_characters_and_attach_message_authors.php`). CHECK `characters_player_user_check`: у `player` user_id обязателен, у `npc` запрещён. Составной FK сира `(sire_character_id, chronicle_id)` уже есть — не потерять.
- `User::character()` — `HasOne` с комментарием «глобально не больше одного игрового персонажа». После частичного unique это неверно. Вызовов `->character` у `User` в `app/` нет.
- Ревью Part 3: после замены unique проверить `Character::isPlayableBy(User)`. Метод сравнивает `user_id` и не читает уникальный индекс. Его вызывают `CharacterPolicy::speakAs` и `denyUnlessManagesSheet` (`update` / `view`).
- PostgreSQL не проверяет FK, если любой столбец ключа NULL. У NPC `user_id` NULL — составной FK на `chronicle_members` их не задевает. Частичный FK не нужен.
- `unique(chronicle_id, user_id)` на членстве одно на пару, включая вышедших (`left_at` не NULL). Второй строки для того же пользователя в той же хронике быть не может.
- До задачи 041 колонка `users.role` остаётся. Её не удалять.
- `CharacterFactory::player()` и `WorldEntityService` создают PC без членства: после FK вставка упадёт. `FactoriesTest` ждёт `QueryException` на PC с `user_id = null` (CHECK), это сохранить.
- `demo:reset` и `DemoSeeder` — задача 043. `DatabaseSeeder` персонажей игрока не создаёт.

## Scope

- `database/migrations/2026_09_01_000001_create_game_sessions_table.php` — таблицы `chronicle_members`, `chronicle_invitations` сразу после `chronicles`
- `database/migrations/2026_09_11_000014_create_characters_and_attach_message_authors.php` — снять глобальный unique `user_id`, частичный unique, составной FK
- `app/Enums/ChronicleRole.php` (создать; кейсы `storyteller`, `player`)
- `app/Models/ChronicleMember.php`, `app/Models/ChronicleInvitation.php` (создать)
- `app/Models/Chronicle.php`, `app/Models/User.php`, `app/Models/Character.php` — связи и `isPlayableBy` (логику сравнения id не менять)
- `database/factories/ChronicleMemberFactory.php`, `database/factories/ChronicleInvitationFactory.php` (создать)
- `database/factories/CharacterFactory.php` — `player()` создаёт членство до вставки персонажа
- `app/World/WorldEntityService.php` — перед вставкой PC проверить членство
- `app/World/Exceptions/PlayerNotChronicleMemberException.php` (создать, `extends ConflictException`)
- `tests/Feature/MembershipSchemaTest.php` (создать)
- `tests/Feature/FactoriesTest.php` и тесты, которые создают PC через API или фабрику (`CharacterAuthorizationTest`, `CharacterVisibilityTest`, `DiaryLlmServicesTest`, `AccessMatrixFixtures`) — только вставка членства, ожидания не ослаблять

**Out of scope:**
- HTTP хроник и приглашений (036, 037);
- Policies (038);
- удаление `users.role` (041);
- `DemoSeeder` / `demo:reset` (043);
- смена `isPlayableBy` на проверку хроники (сравнение `user_id` остаётся).

## Steps

1. До правок: `pg_dump --schema-only` локальной БД в отчёт (или список индексов/CHECK таблицы `characters`). Зафиксировать, что `isPlayableBy` — сравнение `user_id`.
2. `chronicle_members`: `id`, `chronicle_id` FK → `chronicles` restrict, `user_id` FK → `users` restrict, `role` (CHECK `storyteller`/`player`), `joined_at`, `left_at` nullable, `timestamps`, `unique(chronicle_id, user_id)`.
3. `chronicle_invitations`: `id`, `chronicle_id` FK restrict, `token_hash` string unique, `role` (тот же CHECK), `created_by` FK → `users` restrict, `expires_at`, `max_uses` nullable unsigned, `uses` unsigned default 0, `revoked_at` nullable, `timestamps`.
4. В миграции персонажей: убрать `unique()` с `user_id`. Частичный unique `(chronicle_id, user_id) WHERE character_type = 'player' AND is_active`. Составной FK `(chronicle_id, user_id)` → `chronicle_members (chronicle_id, user_id)` restrict. Имя ограничения `characters_chronicle_member_foreign`. Все прежние CHECK, триггеры и FK сира на месте.
5. Модели и фабрики. `ChronicleRole` — PHP enum, колонка string, как `CharacterType`. `User::isStorytellerIn(Chronicle $chronicle): bool` — активное членство (`left_at` NULL) с ролью `storyteller`. `User::character()` заменить на `characters(): HasMany`. `CharacterFactory::player()` до INSERT персонажа пишет строку участника с ролью `player`.
6. `WorldEntityService`: если тип `player` и нет активного членства в этой хронике — `PlayerNotChronicleMemberException` (409), не доводить до ошибки FK. NPC не проверять.
7. `MembershipSchemaTest`:
   - второй активный PC того же пользователя в той же хронике → ошибка БД;
   - активный PC того же пользователя в другой хронике → успех;
   - архивный PC не занимает слот: новый активный в той же хронике создаётся;
   - PC без строки `chronicle_members` → ошибка FK или исключение сервиса;
   - NPC с `user_id` NULL вставляется;
   - `isPlayableBy`: пользователь с двумя PC в двух хрониках — true для обоих своих, false для чужого. Отдельный тест `CharacterPolicy::speakAs` и `update` на таком пользователе: свой PC разрешён, чужой — нет.
8. Проверить, переделать, если не совпадает:
- Character::user() → HasMany, не HasOne (после partial unique у 
  игрока может быть по PC в каждой хронике).
- User::characters() → HasMany (все PC во всех хрониках), не HasOne.
- User::character() (если используется где-то) → переименовать или 
  удалить. Скорее всего, это старое HasOne, которое сломается.

## Definition of Done

- [ ] `sail artisan migrate:fresh --seed` — проходит
- [ ] дамп схемы до/после: новые таблицы и индексы объяснены; CHECK `characters_player_user_check`, `characters_type_check`, `characters_sire_not_self_check`, FK сира и триггеры из `docs/ARCHITECTURE.md` §4 на месте
- [ ] `MembershipSchemaTest` зелёный, включая `isPlayableBy` / `speakAs` / `update`
- [ ] `rg "user_id'\)->nullable\(\)->unique" database/migrations` → пусто
- [ ] `sail pint --test` — чисто
- [ ] `sail php vendor/bin/phpstan analyse` — без новых ошибок; baseline не вырос (снимок 18.10.2026: 9 `message:`; пересчитать в контейнере). В отчёте `baseline: <до> → <после>`
- [ ] `sail artisan test` — зелёные. В отчёте `tests: <до> → <после>`

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно: имена индексов и FK; как `CharacterFactory::player()` гарантирует членство до INSERT; результат тестов `isPlayableBy`.
