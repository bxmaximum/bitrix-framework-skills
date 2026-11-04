# AGENTS.md — Bitrix Framework Expert

Разрабатывай на 1С-Битрикс / Bitrix Framework D7 и Битрикс24 с учётом прав, производительности и версии проекта.

Отвечай по-русски. API, идентификаторы и машинные метки сохраняй.

## Порядок работы

1. Для PHP сначала прочитай `bitrix-framework`, если ещё не читал его в этой задаче. Для облачного REST используй `bitrix24-rest-api`: PHP-ядра в облаке нет.
2. Проверь main в `bitrix/modules/main/classes/general/version.php`, а версию нужного модуля — в его `install/version.php`, прежде чем применять `Since`.
3. Выбери профильный скилл по его описанию или по индексу ниже. Открой `SKILL.md`, затем только подходящие `rules/*.md` относительно каталога скилла. Весь набор читать не требуется.
4. Спорный API проверяй в `bitrix/modules/<module>/lib/` проекта. Код установленного ядра важнее документации и скилла; пути ищи без учёта регистра.
5. Перед REST-вызовом проверь метод через подключённый `b24-dev-mcp` (`bitrix24-ai-tools`) или официальную страницу метода. Не выдумывай параметры и наличие метода на целевой коробке.

Серверный PHP REST собственного модуля — `bitrix-rest`; вызовы REST извне — `bitrix24-rest-api`. Серверный PHP CRM в коробке — `bitrix24-crm`.

Скиллы ищи в `.agents/skills/<name>/`, `.claude/skills/<name>/` или каталоге скиллов используемого агента. При выборочной установке PHP-скиллов включай `bitrix-framework`.

## Индекс скиллов

| Область | Скиллы |
| --- | --- |
| Канон, структура, настройки | `bitrix-framework`, `bitrix-project-structure`, `bitrix-settings`, `bitrix-modules`, `bitrix-console-commands` |
| HTTP-слой | `bitrix-controllers`, `bitrix-routing`, `bitrix-request-response`, `bitrix-validation`, `bitrix-security`, `bitrix-sessions` |
| Сервисы, результаты, события | `bitrix-service-locator`, `bitrix-result-and-errors`, `bitrix-events`, `bitrix-background-jobs`, `bitrix-logger` |
| Данные | `bitrix-orm`, `bitrix-database`, `bitrix-postgresql`, `bitrix-caching`, `bitrix-storage`, `bitrix-sprint-migration` |
| Утилиты | `bitrix-datetime`, `bitrix-localization`, `bitrix-http-client`, `bitrix-mail`, `bitrix-files`, `bitrix-performance` |
| Фронтенд | `bitrix-components`, `bitrix-extensions`, `bitrix-ui`, `bitrix-vue`, `bitrix-pull`, `bitrix-cms-basics` |
| Контентные модули | `bitrix-iblocks`, `bitrix-highloadblock`, `bitrix-landing`, `bitrix-seo`, `bitrix-search`, `bitrix-documentgenerator` |
| Магазин | `bitrix-catalog`, `bitrix-sale` |
| Автоматизация, API | `bitrix-bizproc`, `bitrix-rest` |
| Битрикс24 | `bitrix24-rest-api`, `bitrix24-ai-tools`, `bitrix24-crm`, `bitrix24-tasks`, `bitrix24-im`, `bitrix24-portal` |
