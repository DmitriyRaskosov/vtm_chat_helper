# Неудачные разборы экстрактора

Сюда пишется **сырой** ответ модели, если PHP не смог разобрать JSON (502 «Could not parse…»).

Sail монтирует репозиторий в контейнер: файлы появляются в этой папке в Cursor и Проводнике, без `docker cp`.

В `laravel.log` остаётся только `extractor.parse_failed` (`bytes`, `json_error`, `file`). `*.txt` в git не входят.
