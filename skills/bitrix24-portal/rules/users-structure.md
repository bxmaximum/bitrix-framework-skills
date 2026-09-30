# Users and company structure

## Who is the user

```php
$role = (new \Bitrix\Intranet\User($userId))->getUserRole(); // throws ArgumentOutOfRangeException if $userId <= 0
$isExternal = in_array($role, [\Bitrix\Intranet\Enum\UserRole::EXTRANET, \Bitrix\Intranet\Enum\UserRole::COLLABER], true);
```

`Bitrix\Intranet\Enum\UserRole` cases, checked in this order: `INTEGRATOR`, `ADMIN`, `INTRANET` (`'employee'`), `COLLABER`, `EXTRANET`, `IM_GUEST`, `EMAIL`, `SHOP`, `EXTERNAL`, `VISITOR`.

- Employee (intranet user) = member of a department in the company structure (`humanresources`); admins count as intranet.
- Extranet = member of the group from option `extranet`/`extranet_group`. An employee or admin can also be in it, so don't test the group alone.
- Integrator exists only in the cloud (module `bitrix24`); on-premise `isIntegrator()` is always `false`.
- Admin: `\Bitrix\Intranet\CurrentUser::get()->isAdmin()` (current user), `(new \Bitrix\Intranet\User($id))->isAdmin()` (any user).
- Shortcuts: `Bitrix\Intranet\Util::isIntranetUser(?int)`, `Util::isExtranetUser(?int)`. Collaber: `\Bitrix\Extranet\Service\ServiceContainer::getInstance()->getCollaberService()->isCollaberById($id)`.
- Compat only: `CExtranet::IsExtranetUser()` (deprecated, current user), `CExtranet::IsIntranetUser()`.
- Extranet site: `CExtranet::GetExtranetSiteID()` (`false` if none), `CExtranet::IsExtranetSite($siteId)`.

## Departments: humanresources, not `UF_DEPARTMENT`

Supported API = `Bitrix\HumanResources\Public\Service\Container` + `Bitrix\HumanResources\Builder\Structure\*`. `Service\Container`, repositories and `Internals\*` are internal. Load the module: `\Bitrix\Main\Loader::requireModule('humanresources')`.

```php
$nodes = \Bitrix\HumanResources\Public\Service\Container::getNodeService();
$root = $nodes->getRootNode();                                  // default structure
$tree = $nodes->findChildrenByNodeIds([$root->id], depthLevel: \Bitrix\HumanResources\Enum\DepthLevel::FULL);
$userNodeIds = $nodes->findAllByMemberEntityId($userId)->getIds(); // user's departments
$node = $nodes->getByAccessCode('D' . $iblockSectionId);        // legacy section id -> node

$users = \Bitrix\HumanResources\Public\Service\Container::getUserDepartmentService();
$headIds = $users->getUserHeads($userId)->getUniqueEntityIds(); // nearest head(s), walks up
$isHead = $users->isHeadOfDepartment($userId);
$subIds = $users->getSubordinateUserIds($userId);               // direct only by default
```

- `Item\Node`: public `id`, `name`, `type` (`Type\NodeEntityType`: `DEPARTMENT`, `TEAM`), `parentId`, `structureId`, `accessCode` (`D<iblockSectionId>` for departments), `xmlId`, `active`.
- Teams: `Container::getUserTeamService()` (team twins: `isHeadOfTeam()`, `getUserHeads()`, `getSubordinateUserIds()`; `Container` = `Public\Service\Container`). Defaults narrow results: `nodeTypes` = `[DEPARTMENT]` in most methods (`findChildrenByNodeIds()`: departments and teams), active filter = `GLOBAL_ACTIVE`, `structureId = null` = default structure.
- Heads return a `NodeMemberCollection`; user ids are `entityId` → `getUniqueEntityIds()`.

All users of a department subtree (builder):

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

- Only heads: add `roleFilter: RoleFilter::fromRole(NodeMemberRole::Head)` (`Builder\Structure\Filter\Column\RoleFilter`, `Type\NodeMemberRole`).
- Since humanresources 26.500 bots/virtual users are excluded by default; `withVirtualUsers: true` includes them.

Writes: `$users->assignToDepartments($userId, $nodeIds, replaceExisting: false)` takes **node ids**, not section ids, and checks no rights — check `StructureAccessController::can($currentUserId, StructureActionDictionary::ACTION_EMPLOYEE_ADD_TO_DEPARTMENT, $nodeId)` (namespace `Bitrix\HumanResources\Access`) yourself. No public API for creating/moving nodes.

## `UF_DEPARTMENT` (compat only)

- humanresources is the master; iblock `departments` sections and `UF_DEPARTMENT`/`UF_HEAD` are synced from it. `UF_DEPARTMENT` is written in a background job after the hit — stale right after an HR write. Teams never reach it.
- `CIntranetUtils::GetUserDepartments()`, `GetDepartmentManager()`, `GetStructure()` are deprecated; `GetUserDepartments()` still reads `UF_DEPARTMENT`.
- Read `UF_DEPARTMENT` only when you need legacy iblock section ids.

## Events

Module `humanresources`: `OnNodeAdded`, `OnNodeUpdated`, `OnNodeDeleted`, `OnMemberAdded`, `OnMemberUpdated`, `OnMemberDeleted` (params `node` / `member`; updates also `fields`). Register under your own module id — HR sync temporarily removes handlers registered to `humanresources`.

## Pitfalls

- `$member->node` and `$node->depth` lazy-load one query each — batch by ids (`findAllByIds`).
- Builder results are cached 24 h; never write `b_hr_*` tables with SQL.
- Passing `structureAction` in agents/CLI checks rights of user 0 and returns empty.
