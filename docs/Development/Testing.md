# Тестирование

## PHPUnit

```bash
docker compose exec laravel.test php artisan test
```

## RAG без Ollama

В `phpunit.xml`: `RAG_EMBEDDING_DRIVER=stub` — тесты не требуют контейнер Ollama.

## Feature-тесты API

| Тест | Покрытие |
|------|----------|
| `tests/Feature/AuthenticationTest.php` | register, login, logout, `/api/user` |
| `tests/Feature/ChatTest.php` | GET/POST messages, author, mine |
| `tests/Feature/GameSessionSceneTest.php` | lifecycle сессий/сцен, роли и статусы |
| `tests/Feature/CopilotTest.php` | drafts, лимит/дедупликация Context Builder, runtime options Ollama, tool-loop, хранение и одноразовая привязка запроса |
| `tests/Feature/RagSearchTest.php` | индексация, search, lore chunks |
| `tests/Feature/ContextSummaryTest.php` | L0 thresholds, oversized, failure/cursor, idempotency, L1/final/session provenance и Context Builder |
| `tests/Feature/RetrievalToolsTest.php` | session scope, лимиты range, изоляция search_messages/summaries |
| `tests/Feature/StorytellerIntentTest.php` | intent в Copilot, отсутствие в world summary и player chat |

## Unit-тесты контекста

- `TokenEstimatorTest` — Unicode-оценка и валидация коэффициента.
- `MessageWindowSelectorTest` — лимиты 15000/50, точная граница и oversized-сообщение.

Selector проверяется без Ollama и никогда не делит одно сообщение между окнами.

Context Builder проверяется через фактический payload fake Ollama и `context_metadata`: вход не превышает бюджет, newest raw history имеет приоритет, RAG/raw дубли удаляются.

Summary-тесты подменяют `ChatProvider` и используют stub embeddings. Проверяется отдельный профиль 24576/3000, отсутствие сдвига курсора при ошибке и неизменность количества summaries при повторном запуске.

## Ollama в feature-тестах

Не вызывать реальный Ollama. Для copilot — `Http::fake` на `config('ollama.url').'/api/chat'`:

```php
Http::fake([
    config('ollama.url').'/api/chat' => Http::response([
        'message' => ['content' => json_encode(['drafts' => ['...']])],
    ]),
]);
```

## Sanctum в тестах

`Sanctum::actingAs($user)` или `withToken($token)` для защищённых маршрутов.

## Smoke Ollama (вручную, из контейнера Laravel)

- `php artisan rag:embed-ping`
- `php artisan llm:ping`
