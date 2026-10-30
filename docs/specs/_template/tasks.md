# Tasks: {Название фичи}

## Stage 1: Backend (Foundation)
- [ ] Создать миграцию `add_avatar_path_to_characters_table`.
- [ ] Обновить модель `Character`.
- [ ] Написать Feature-тест `AvatarUploadTest` (проверить AC-1, AC-3).
- [ ] Создать `CharacterAvatarService`.
- [ ] Добавить маршруты в `routes/api.php` и метод в `CharacterSheetController`.

## Stage 2: Frontend (UI)
- [ ] Создать `useCharacterAvatar.js` (с поддержкой `URL.createObjectURL`).
- [ ] Обновить `RightSidebar.vue` (добавить `<img>` с заглушкой).
- [ ] Создать `AvatarUploader.vue` в Мастерской.

## Stage 3: Finalization
- [ ] Прогнать `php artisan test`.
- [ ] Прогнать `npm run build`.
- [ ] Проверить вручную (загрузить, удалить, посмотреть в чате).