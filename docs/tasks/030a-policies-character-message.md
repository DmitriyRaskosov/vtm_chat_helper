# 030a — Policies: персонажи, сообщения, запросы Copilot

- **Stage:** 2
- **Part:** 3
- **Status:** todo
- **Depends on:** 022
- **Risk:** high (авторизация)
- **Разрешённые зависимости:** нет

## Goal

Доступ к персонажам, сообщениям и использованию `CopilotRequest` проверяется только Policies (`CharacterPolicy`, `MessagePolicy`, `CopilotRequestPolicy`), а `CharacterAccess`, `StoreMessageRequest::authorize` и `AccessDeniedHttpException` в `MessageEditService` удалены.

## Context

- ADR-003. Роль пока глобальная (`User::isStoryteller()`), хроника — из модели.
- Из 024: `CharacterArchivedException` сейчас недостижим по HTTP (валидация 403/422 раньше). После переноса `authorize()` в Policy путь меняется — решить судьбу исключения.
- Текущие механизмы и тексты 403:
  - `CharacterAccess::canManageSheet` / `abortUnlessManagesSheet` → `403 You cannot access this character sheet.` (show, update, disciplines, biography, traits, place);
  - `CharacterAccess::canSpeakAs` — из `StoreMessageRequest::authorize` (говорить за NPC — рассказчик, за игрока — его владелец, архивный — никто) → стандартный текст Laravel `This action is unauthorized.`;
  - `MessageEditService::assertCanEdit` → `AccessDeniedHttpException('You cannot edit this message.')` (рассказчик или автор);
  - `MessageService` (025b): `403 Copilot request belongs to another storyteller.`;
  - middleware `storyteller` на `POST /characters`, `archive`, `restore`, `PUT /place` → `403 Только рассказчик.`;
  - `authorize()` с `isStoryteller()` в `StoreCharacterRequest`, `UpdateCharacterPlaceRequest`.
- Правило видимости списка (задача 005): рассказчик — все активные; игрок — свой и текущие участники активной сцены; без персонажа — пусто. Это фильтр, не 403.

## Scope

- `app/Policies/{Character,Message,CopilotRequest}Policy.php` (создать)
- `app/Character/CharacterVisibility.php` (создать) — запрос видимых id для игрока (перенос из контроллера/сервиса)
- `app/Character/CharacterAccess.php` — `canManageSheet`, `canSpeakAs` → `CharacterPolicy::view` / `update` / `speakAs`. После переноса удалить `CharacterAccess`.
- `app/Character/CharacterSceneGuard.php` (из 024) — `assertUsableIn` → `CharacterPolicy::viewInScene`. После переноса удалить guard.
- `CharacterArchivedException` (из 024) — решить: 409 (текущее, доменное) или 403 (Policy-отказ, ADR-005). Если 403 — исключение удалить. Обосновать в отчёте.
- `app/Messages/MessageEditService::assertCanEdit` — перенести в `MessagePolicy::update` / `delete`. Удалить `AccessDeniedHttpException` из сервиса.
- `app/Messages/MessageEditService.php`, `app/Messages/MessageService.php` — удалить проверки доступа
- `app/Http/Controllers/{CharacterSheet,Chat}Controller.php` — `Gate::authorize` / `$this->authorize`
- `app/Http/Requests/{StoreMessage,StoreCharacter,UpdateCharacterPlace,UpdateCharacterIdentity,UpdateCharacterBiography,UpdateCharacterDisciplines,UpdateCharacterTraits}Request.php` — удалить `authorize()`
- `routes/api.php` — маршруты персонажей: `storyteller` → `->can(...)`
- `tests/Feature/CharacterAuthorizationTest.php`, `MessageAuthorizationTest.php` (создать), существующие тесты

**Out of scope:** сцены, сессии, дневник, Copilot drafts, удаление `EnsureStoryteller` (030b); полная матрица (030c); членство в хронике (этап 3).

## Steps

1. Характеризация: на каждую проверку из Context — тест «разрешено» и «запрещено» с текущим кодом и текстом.
2. `CharacterPolicy`: `view`, `update` (= canManageSheet), `create`, `archive`, `restore`, `updatePlace` (рассказчик), `speakAs` (= canSpeakAs). Отказы — `Response::deny('<прежний текст>')`.
3. `MessagePolicy`: `create` (с `character_id` → `speakAs`), `update`/`delete`/`restore` (рассказчик или автор). `CopilotRequestPolicy::use` — рассказчик-владелец.
4. Вызовы: контроллеры — `Gate::authorize(...)`; маршруты персонажей из группы `storyteller` — `->can('create', Character::class)` и т. п.
5. Видимость списка — `CharacterVisibility` (без изменения правила, тесты `CharacterVisibilityTest` без правок).
6. Удалить `CharacterAccess`, `authorize()` из перечисленных FormRequest, проверки доступа из сервисов сообщений.
7. CharacterArchivedException (из 024): после переноса authorize() в 
  CharacterPolicy::createMessage — решить, возвращается ли это 
  исключение (409, текущее) или заменяется на Policy-отказ (403). 
  Согласовать с семантикой ADR-005 (авторизация — 403, доменные — 
  конфликт). Если 403 — исключение удалить.
8. CharacterSceneGuard::assertUsableIn() → CharacterPolicy::viewInScene() 
  (или аналогичный метод). CharacterAccess::canSpeakAs() и 
  canManageSheet() — тоже в CharacterPolicy. После 030a grep 
  "CharacterAccess|CharacterSceneGuard" по app/ должен быть пустым.
9. CopilotRequestForbiddenException (403, из 025b) → MessagePolicy::create 
  (или CopilotPolicy). После переноса исключение удалить.

## Definition of Done

- [ ] `rg 'CharacterAccess|CharacterSceneGuard|AccessDeniedHttpException' app/` → пусто (кроме допустимых комментариев)
- [ ] `rg "function authorize" app/Http/Requests -g "{StoreMessage,StoreCharacter,UpdateCharacter*}Request.php"` → пусто
- [ ] коды и тексты 403 из шага 1 не изменились; `CharacterVisibilityTest` зелёный без правок
- [ ] `sail artisan test` — зелёные; `sail pint --test` — чисто; phpstan — OK, baseline не вырос

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно: таблица «механизм было → Policy::ability».
