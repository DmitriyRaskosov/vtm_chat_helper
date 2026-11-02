# Tasks: Личный кабинет игрока (Player Cabinet)

## Этап 1: Backend (База и Сервисы)
- [x] Создать миграцию `add_avatar_path_to_characters_table` (добавить `avatar_path` string nullable).
- [x] Обновить модель `Character` (добавить `avatar_path` в `#[Fillable]` и `casts`).
- [x] Создать `app/Character/CharacterAvatarService.php` (метод `upload(Character, UploadedFile)` с ресайзом через GD).
- [x] Обновить `CharacterSheetImageController` (разрешить `pdf` в валидации, сохранять файл как есть без конвертации).
- [x] В `CharacterSheetImageController` обновить метод `show()`, чтобы он возвращал правильный `Content-Type` (MIME-тип файла).

## Этап 2: Backend (API и Права)
- [x] В `CharacterPolicy` добавить метод `manageProfile(User, Character)`, разрешающий доступ владельцу (user_id) и рассказчику.
- [x] Создать `ProfileController` с методом `index()` (возвращает список персонажей текущего пользователя: `id`, `canonical_name`, `avatar_path`, `chronicle_id`).
- [x] Создать `CharacterAvatarController` с методом `store()` для загрузки аватара.
- [x] Добавить маршруты в `routes/api.php`: `GET /api/profile/characters`, `POST /api/characters/{character}/avatar`.
- [x] Обновить маршрут `POST /api/characters/{character}/sheet` (применить Policy `manageProfile`).

## Этап 3: Frontend (Зависимости и Логика)
- [ ] Установить npm-пакеты: `vue-pdf-embed` и `pdf-lib` в папке `frontend`.
- [ ] Создать `frontend/src/composables/useProfile.js` (загрузка списка персонажей, функции загрузки аватара и чарлиста).
- [ ] Создать `frontend/src/composables/usePdfForm.js` (логика для `pdf-lib`: чтение формы, заполнение, генерация файла).

## Этап 4: Frontend (Интерфейс)
- [ ] Добавить маршрут `/profile` (`ProfileView.vue`) в `router.js`.
- [ ] В `AppNav.vue` добавить иконку `🎭` (Личный кабинет).
- [ ] Создать `ProfileView.vue`: сетка карточек персонажей (аватар, имя, кнопки загрузки).
- [ ] Создать `AvatarModal.vue`: полноэкранный просмотр аватара.
- [ ] Создать `SheetViewer.vue`: 
    *   Если файл — картинка: показывать `<img>`.
    *   Если файл — PDF: показывать `vue-pdf-embed`. Если есть поля ввода — активировать `usePdfForm` и кнопку «Сохранить».

## Этап 5: Тестирование и Проверка (DoD)
- [ ] Написать `ProfileApiTest` (игрок видит своих, чужой получает 403).
- [ ] Написать `AvatarUploadTest` (успешная загрузка, ресайз, лимит 40MB).
- [ ] Обновить `SheetUploadTest` (игрок может загрузить PDF/PNG своему персонажу).
- [ ] Прогнать `docker compose exec laravel.test php artisan test` — все тесты зелёные.
- [ ] Прогнать `docker compose exec laravel.test ./vendor/bin/pint`.
- [ ] Прогнать `cd frontend && npm run build` — сборка успешна.
- [ ] Сверить результат с Acceptance Criteria из `spec.md`.