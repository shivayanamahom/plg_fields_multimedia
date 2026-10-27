# Правила и архитектура для ИИ-агентов (AGENTS.md)

Плагин пользовательского поля **Multimedia Field** (`plg_fields_multimedia`, группа `fields`, элемент `multimedia`) для Joomla 5.2+/6.x. Репозиторий: `shivayanamahom/plg_fields_multimedia`, ветка `main`. Общение и документация — на русском.

## Архитектура

- Пространство имён `Joomla\Plugin\Fields\Multimedia`, сервисный провайдер `services/provider.php`.
- `src/Extension/Multimedia.php` — плагин (`FieldsPlugin`): `onCustomFieldsPrepareDom` (id поля, фильтр, проверенные настройки), `onContentPrepareForm` (блокировка режима, скрытие параметров чужого режима), `onContentBeforeSave` (режим берётся из БД, параметры очищаются), `onAjaxMultimedia` (загрузка), `onContentBeforeDelete`/`AfterDelete` (удаление файлов).
- `src/Field/MultimediaField.php` — наследник `SubformField`: строки subform (штатное поле `media`, подпись, alt) + обёртка загрузки (`layouts/.../field.php`).
- `src/Filter/MultimediaFilter.php` — серверный фильтр значения (подключается `filter="multimedia"`, префикс регистрируется в плагине). Значение приходит из Registry: вложенные массивы — объекты (`MediaHelper::parseValue` это учитывает).
- `src/Helper/` — `MediaHelper` (единственный источник значений по умолчанию, пределов и проверки путей), `UploadHandler`, `ImageProcessor` (GD), `UploadException`.
- `params/multimedia.xml` — параметры поля. Параметры режима помечены `showon="mode:…"`; для сохранённого поля чужие параметры удаляются из формы (списки `galleryOnlyParams()`/`filesOnlyParams()` в `MediaHelper`).
- Макеты в `layouts/plg_fields_multimedia/` вызываются через `LayoutHelper::render(..., MediaHelper::layoutPath())`, поэтому переопределяются из `templates/<шаблон>/html/layouts/plg_fields_multimedia/`.
- Значение поля — JSON-список `[{"file":"images/…","title":"","alt":""}]`.

## Правила

1. **Безопасность прежде всего.** Любой параметр загрузки сервер берёт из БД по `field_id`; от клиента принимается только файл. Не добавляйте параметры, влияющие на загрузку, в запрос.
2. Не разрешайте исполняемые и активные типы (`MediaHelper::DENIED_EXTENSIONS/MIMES`); не ослабляйте проверки в `UploadHandler`, фильтре и удалении файлов (удалять можно только внутри `images/<папка поля>`, без ссылок на файл из других записей).
3. Нет inline-скриптов и стилей, нет CDN. Просмотр крупно — только через WebAsset `wtfancybox` (если зарегистрирован) или встроенный `<dialog>`.
4. Вся разметка — в макетах. Новые строки языка добавляйте в `ru-RU` и `en-GB` одновременно; ключи, не используемые в коде, удаляйте.
5. Режим поля не меняется после сохранения: проверяется и в форме, и на сервере (`onFieldBeforeSave`).
6. Значения по умолчанию и диапазоны — только в `MediaHelper::defaults()/settings()`; XML-значения должны им соответствовать.

## Проверка и сборка

```bash
JOOMLA_ROOT=/path/to/site php tests/run.php   # должно быть «0 failed»
tools/build.sh                                 # dist/plg_fields_multimedia-<версия>.zip
```

Перед релизом: поднять `<version>` в `multimedia.xml` и `media/joomla.asset.json`, дописать `CHANGELOG.md`. Пуш в `main` запускает workflow `.github/workflows/release.yml`: сборка, релиз `v<версия>`, обновление `updates.xml`.

Проверять интерфейс нужно в реальной админке Joomla (создать поле каждого режима, загрузить файлы, сохранить материал, открыть страницу на сайте).
