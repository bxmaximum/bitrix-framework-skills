# Права доступа и REST

## Права модуля

```php
$permissions = \Bitrix\DocumentGenerator\Driver::getInstance()->getUserPermissions($userId); // кеш на пользователя
if (!$permissions->canCreateDocumentOnTemplate($templateId))
{
    return $result->addError(new \Bitrix\Main\Error('Доступ запрещён')); // $result: \Bitrix\Main\Result
}
```

Методы `UserPermissions`: `canViewDocuments()`, `canModifyDocuments()`, `canModifyDocument($documentIdOrDocument)`, `canModifyTemplates()`, `canModifyTemplate(int $templateId)`, `canCreateDocumentOnTemplate(int $templateId)`, `canModifySettings()`, `getFilterForTemplateList()` (ORM-фильтр `TemplateTable`).

- Роли (`Model\RoleTable`, `RolePermissionTable`, `RoleAccessTable`) редактируются в `bitrix:documentgenerator.settings.perms`. При установке создаются ADMIN (администраторы) и MANAGER (корневой отдел компании).
- Статус администратора проверяется только для текущего пользователя; для остальных ID действуют роли.
- Видимость шаблона: коды доступа `TemplateUserTable`; список для поставщика — `Model\TemplateTable::getListByClassName($providerClass, $userId, $value)`.

## Проверяй все три уровня

1. Право модуля: можно ли создавать / видеть документы вообще.
2. Коды доступа шаблона: можно ли использовать этот шаблон.
3. Доступ к данным: `$document->hasAccess($userId)` вызывает `hasAccess()` поставщика. `Template::loadById()` и `Document::createByTemplate()` прав не проверяют.

ID пользователя `0` проходит все проверки данных (агенты, CLI). При генерации от чьего-либо имени задай настоящего пользователя через `setUserId()`.

## Контроллеры и REST

- Контроллеры модуля опубликованы под псевдонимом `api` с интеграцией REST: AJAX `documentgenerator.api.document.*`, `documentgenerator.api.template.*`, например `BX.ajax.runAction('documentgenerator.api.document.getfile', …)`.
- Публичный REST (scope `documentgenerator`): `documentgenerator.template.{add,update,delete,get,list,getfields}`, `documentgenerator.document.{add,update,delete,get,list,getfields,enablepublicurl}`, `documentgenerator.numerator.*`, `documentgenerator.region.*`, `documentgenerator.role.{…,fillaccesses}`. Подробности: `bitrix24-rest-api`.
- Шаблоны REST получают модуль `rest` и поставщика `DataProvider\Rest`. Приложение передаёт все значения в `document.add` (`values`), PHP-поставщик недоступен. Шаблоны / документы других модулей закрыты для REST (`CheckScope`).
- У документов CRM свой REST: `crm.documentgenerator.{template,document,numerator}.*` (`bitrix24-crm`).

## Компоненты интерфейса

`bitrix:documentgenerator.templates` (список / загрузка), `.documents` (грид), `.placeholders` (каталог полей), `.settings.perms` (роли), `.config`, `.region`, `.templates.default`, `.feature`, `.view` (публичный просмотр по ID и хешу). Это готовый продуктовый интерфейс: открывай в слайдере.
