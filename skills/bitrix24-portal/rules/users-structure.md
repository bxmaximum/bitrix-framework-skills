# Пользователи и оргструктура

## Роль пользователя

```php
$role = (new \Bitrix\Intranet\User($userId))->getUserRole(); // ArgumentOutOfRangeException при $userId <= 0
$isExternal = in_array($role, [\Bitrix\Intranet\Enum\UserRole::EXTRANET, \Bitrix\Intranet\Enum\UserRole::COLLABER], true);
```

Варианты `Bitrix\Intranet\Enum\UserRole` проверяются по порядку: `INTEGRATOR`, `ADMIN`, `INTRANET` (`'employee'`), `COLLABER`, `EXTRANET`, `IM_GUEST`, `EMAIL`, `SHOP`, `EXTERNAL`, `VISITOR`.

- Сотрудник — участник отдела humanresources; администратор также считается пользователем интранета.
- Экстранет — член группы из опции `extranet/extranet_group`. Сотрудник и администратор тоже могут в неё входить, поэтому одной группы недостаточно.
- Интегратор существует только с облачным модулем `bitrix24`; в коробке `isIntegrator()` возвращает `false`.
- Администратор: `\Bitrix\Intranet\CurrentUser::get()->isAdmin()` для текущего, `(new \Bitrix\Intranet\User($id))->isAdmin()` для произвольного пользователя.
- Сокращения: `Bitrix\Intranet\Util::isIntranetUser(?int)`, `Util::isExtranetUser(?int)`. Коллабер: `\Bitrix\Extranet\Service\ServiceContainer::getInstance()->getCollaberService()->isCollaberById($id)`.
- Для совместимости: устаревшие `CExtranet::IsExtranetUser()` (текущий пользователь), `CExtranet::IsIntranetUser()`.
- Экстранет-сайт: `CExtranet::GetExtranetSiteID()` (`false`, если нет), `CExtranet::IsExtranetSite($siteId)`.

## Отделы через humanresources

Публичный API: `Bitrix\HumanResources\Public\Service\Container` и `Bitrix\HumanResources\Builder\Structure\*`. `Service\Container`, репозитории и `Internals\*` — внутренние. Подключи `\Bitrix\Main\Loader::requireModule('humanresources')`.

```php
$nodes = \Bitrix\HumanResources\Public\Service\Container::getNodeService();
$root = $nodes->getRootNode();                                  // структура по умолчанию
$tree = $nodes->findChildrenByNodeIds([$root->id], depthLevel: \Bitrix\HumanResources\Enum\DepthLevel::FULL);
$userNodeIds = $nodes->findAllByMemberEntityId($userId)->getIds(); // отделы пользователя
$node = $nodes->getByAccessCode('D' . $iblockSectionId);        // старый ID раздела преобразуется в узел

$users = \Bitrix\HumanResources\Public\Service\Container::getUserDepartmentService();
$headIds = $users->getUserHeads($userId)->getUniqueEntityIds(); // ближайшие руководители, с подъёмом по структуре
$isHead = $users->isHeadOfDepartment($userId);
$subIds = $users->getSubordinateUserIds($userId);               // по умолчанию непосредственные подчинённые
```

- `Item\Node`: публичные `id`, `name`, `type` (`Type\NodeEntityType`: `DEPARTMENT`, `TEAM`), `parentId`, `structureId`, `accessCode` (`D<iblockSectionId>` отдела), `xmlId`, `active`.
- Команды: `Container::getUserTeamService()` с `isHeadOfTeam()`, `getUserHeads()`, `getSubordinateUserIds()`; `Container` — `Public\Service\Container`. По умолчанию большинство методов выбирают `nodeTypes=[DEPARTMENT]`, `findChildrenByNodeIds()` — отделы и команды. Активность — `GLOBAL_ACTIVE`, `structureId=null` — основная структура.
- Руководители возвращаются в `NodeMemberCollection`; ID пользователей — `entityId`, список через `getUniqueEntityIds()`.

Все пользователи поддерева отдела через builder:

```php
$filter = new \Bitrix\HumanResources\Builder\Structure\Filter\NodeMemberFilter(
    nodeFilter: new \Bitrix\HumanResources\Builder\Structure\Filter\NodeFilter(
        idFilter: \Bitrix\HumanResources\Builder\Structure\Filter\Column\IdFilter::fromId($nodeId),
        entityTypeFilter: \Bitrix\HumanResources\Builder\Structure\Filter\Column\Node\NodeTypeFilter::createForDepartment(),
        direction: \Bitrix\HumanResources\Enum\Direction::CHILD,
        depthLevel: \Bitrix\HumanResources\Enum\DepthLevel::FULL,
    ),
);
$builder = \Bitrix\HumanResources\Builder\Structure\NodeMemberDataBuilder::createWithFilter($filter);
$userIds = $builder->getAll()->getUniqueEntityIds();
```

- Только руководители: `roleFilter: RoleFilter::fromRole(NodeMemberRole::Head)` (`Builder\Structure\Filter\Column\RoleFilter`, `Type\NodeMemberRole`).
- **Since humanresources 26.500** боты/виртуальные пользователи исключены по умолчанию; `withVirtualUsers: true` включает их. На старом модуле фильтруй их самостоятельно.

Запись: `$users->assignToDepartments($userId, $nodeIds, replaceExisting: false)` принимает ID узлов, не разделов, без проверки прав. Сначала `StructureAccessController::can($currentUserId, StructureActionDictionary::ACTION_EMPLOYEE_ADD_TO_DEPARTMENT, $nodeId)` (`Bitrix\HumanResources\Access`). Публичного API создания/перемещения узлов в проверенной версии нет.

## `UF_DEPARTMENT` для совместимости

- Источник — humanresources. Разделы инфоблока `departments` и `UF_DEPARTMENT`/`UF_HEAD` синхронизируются из него. Запись `UF_DEPARTMENT` выполняется после ответа фоном, сразу после изменения HR значение старое. Команды туда не попадают.
- `CIntranetUtils::GetUserDepartments()`, `GetDepartmentManager()`, `GetStructure()` устарели; первый всё ещё читает `UF_DEPARTMENT`.
- Читай `UF_DEPARTMENT`, только если требуются старые ID разделов инфоблока.

## События

Модуль `humanresources`: `OnNodeAdded`, `OnNodeUpdated`, `OnNodeDeleted`, `OnMemberAdded`, `OnMemberUpdated`, `OnMemberDeleted`; параметры `node`/`member`, при изменении также `fields`. Регистрируй под ID своего модуля: синхронизация HR временно снимает обработчики, зарегистрированные на `humanresources`.

## Ограничения

- `$member->node` и `$node->depth` выполняют по одному ленивому запросу. Загружай порциями через `findAllByIds`.
- Результаты builder кешируются на 24 часа; не записывай `b_hr_*` через SQL.
- `structureAction` в агенте/CLI проверяет пользователя 0 и возвращает пустой результат.
