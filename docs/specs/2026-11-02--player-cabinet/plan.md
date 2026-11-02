# Plan: Личный кабинет игрока (Player Cabinet)

## 1. Backend (Laravel)

### 1.1 База данных и Модели
*   **Миграция:** Создать `add_avatar_path_to_characters_table`.
    *   Добавить колонку `avatar_path` (string, nullable) в `characters`.
*   **Модель:** Добавить `avatar_path` в `#[Fillable]` и `casts` (строка) модели `Character`.

### 1.2 Сервисы и Логика
*   **Сервис загрузки аватаров:** Создать `app/Character/CharacterAvatarService.php`. Принимает картинку, ресайзит до 400x400 и сохраняет в `storage/app/public/avatars`.
*   **Обновление Чарлиста:** В `CharacterSheetImageController` разрешить загрузку PDF.
    *   Валидация: `mimes:jpeg,png,pdf, max:40960` (40 МБ).
    *   Сохранять файл в `storage/app/public/characters/{id}/sheet.{ext}` **без конвертации** (как есть).
    *   В модель `Character` добавить поле `sheet_mime_type` (или определять MIME при отдаче через `Storage::mimeType()`), чтобы фронтенд знал, как рендерить файл.
*   **Отдача файла:** Эндпоинт `GET /api/characters/{character}/sheet` должен возвращать файл с правильным заголовком `Content-Type` (`image/jpeg`, `image/png` или `application/pdf`).

### 1.3 API и Маршруты
*   `GET /api/profile/characters` — возвращает список персонажей текущего игрока (id, name, avatar_path). (Новый метод в `ProfileController`).
*   `POST /api/characters/{character}/avatar` — загрузка аватара (FormRequest: mimes:jpeg,png, max:40960).
*   `POST /api/characters/{character}/sheet` — (существующий маршрут, меняем Policy, чтобы владелец тоже мог).

### 1.4 Авторизация (Policies)
*   В `CharacterPolicy` добавить метод `manageProfile(User, Character)`:
    *   Возвращает `true`, если `$user->id === $character->user_id` (владелец) или `$user->isStorytellerIn($chronicle)`.
*   Применить эту Policy к новым маршрутам.

## 2. Frontend (Vue 3)

### 2.1 Маршрутизация и Навигация
*   **Router:** Добавить маршрут `/profile` (`ProfileView.vue`) с `meta.auth: true`.
*   **Sidebar (AppNav):** Добавить иконку `🎭` (Личный кабинет) в левое меню. Скрыть её для рассказчика, если он не является владельцем персонажа (или показывать всем, но с пустым экраном для ST).

### 2.2 Зависимости (Frontend)
*   Установить `vue-pdf-embed` (для просмотра PDF).
*   Установить `pdf-lib` (для заполнения PDF-форм и генерации нового файла для сохранения на сервер).

### 2.3 Composables
*   Создать `useProfile.js`:
    *   `characters`, `loading`, `error`.
    *   `loadCharacters()` — запрос к `/api/profile/characters`.
    *   `uploadAvatar(characterId, file)`.
    *   `uploadSheet(characterId, file)`.

### 2.4 Компоненты (UI)
*   **ProfileView.vue:**
    *   Сетка карточек с персонажами игрока.
    *   В карточке: аватар (клик -> полноэкранная модалка), имя, кнопки «Загрузить аватар» (input file) и «Загрузить чарлист» (input file).
    *   Клик по имени/карточке ведет на стандартную страницу листа (`/c/:chronicleId/characters/:id`).
*   **AvatarModal.vue:** Простая модалка для полноэкранного просмотра (по клику на аватар).
*   **SheetViewer.vue:** Компонент, который определяет тип файла (картинка или PDF). 
    *   Если картинка — показывает `<img>`.
    *   Если PDF — показывает `vue-pdf-embed`. Если в PDF есть поля, позволяет их заполнить и по кнопке «Сохранить» отправляет обновленный файл через API.

## 3. Риски и ограничения
*   **Вес файлов:** Загруженные аватары и чарлисты могут занимать место. Добавить команду `artisan profile:prune` в будущем.
*   **PDF-формы:** `pdf-lib` поддерживает только стандартные формы AcroForm. Если игрок загрузит PDF, который является просто картинкой (сканом) без полей ввода, он сможет его только просматривать, но не редактировать.

## 4. План тестирования
*   `ProfileApiTest`: проверить, что игрок видит своих персонажей, а чужой игрок/рассказчик получает 403 или пустой список.
*   `AvatarUploadTest`: проверить успешную загрузку, ресайз, отказ для файлов >40MB.
*   `SheetUploadTest`: проверить, что игрок теперь может загрузить чарлист (PNG/PDF) своему персонажу.