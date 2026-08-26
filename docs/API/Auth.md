# Auth API

Контроллеры: `app/Http/Controllers/Auth/`.

## POST /api/register

Публичный. Регистрация по логину.

**Body:**

| Поле | Тип | Правила |
|------|-----|---------|
| `name` | string | required, max 255 |
| `login` | string | required, max 64, unique |
| `password` | string | required, confirmed, min 2 |
| `password_confirmation` | string | required |

**Response 201:**

```json
{
  "token": "…",
  "user": {
    "id": 1,
    "name": "…",
    "login": "…",
    "role": "storyteller",
    "is_storyteller": true
  }
}
```

Первый пользователь — `storyteller`, остальные — `player`. См. [[Project/Roles]].

## POST /api/login

Публичный.

**Body:** `login` (required), `password` (required)

**Response 200:** `{ "token", "user" }`

**422:** неверный логин или пароль.

## GET /api/user

**Auth:** sanctum

**Response 200:** `{ "user": { id, name, login, role, is_storyteller } }`

## POST /api/logout

**Auth:** sanctum

**Response 204:** токен удалён, последующие запросы с ним — 401.
