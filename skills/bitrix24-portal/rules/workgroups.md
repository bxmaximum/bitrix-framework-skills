# Workgroups, projects, collabs (socialnetwork)

One table for all: `Bitrix\Socialnetwork\WorkgroupTable` (`b_sonet_group`). Kind is `TYPE` → `Bitrix\Socialnetwork\Item\Workgroup\Type`: `Group` (`group`), `Project` (`project`), `Scrum` (`scrum`), `Collab` (`collab`). `TYPE` is nullable on old rows — also check `PROJECT = 'Y'`.

## Read

```php
$group = \Bitrix\Socialnetwork\Item\Workgroup::getById($groupId);   // Workgroup|false (cached registry)
$isProject = $group && $group->isProject();

$rows = \Bitrix\Socialnetwork\WorkgroupTable::query()
    ->setSelect(['ID', 'NAME', 'TYPE', 'CLOSED', 'VISIBLE', 'OWNER_ID'])
    ->where('ACTIVE', 'Y')
    ->whereIn('ID', $groupIds)
    ->fetchAll();
```

`Workgroup` item: `getId()`, `getFields()`, `getType()`, `isProject()`, `isScrumProject()`, `isCollab()`.

## Membership

```php
$row = \Bitrix\Socialnetwork\UserToGroupTable::query()
    ->setSelect(['ROLE'])
    ->where('GROUP_ID', $groupId)
    ->where('USER_ID', $userId)
    ->fetch();
$role = $row['ROLE'] ?? null;

$isMember = in_array($role, \Bitrix\Socialnetwork\UserToGroupTable::getRolesMember(), true);   // A, E, K
```

Roles (`UserToGroupTable::ROLE_*`): `ROLE_OWNER` `A`, `ROLE_MODERATOR` `E`, `ROLE_USER` `K`, `ROLE_BAN` `T`, `ROLE_REQUEST` `Z` (invite/request pending — not a member). `AUTO_MEMBER = 'Y'` marks users added by department sync.

User's groups: `UserToGroupTable::query()->setSelect(['GROUP_ID'])->where('USER_ID', $userId)->whereIn('ROLE', UserToGroupTable::getRolesMember())`.

## Access checks

```php
$canView = \Bitrix\Socialnetwork\Helper\Workgroup\Access::canView(['groupId' => $groupId, 'userId' => $userId]);
$canModify = \Bitrix\Socialnetwork\Helper\Workgroup\Access::canModify(['groupId' => $groupId, 'userId' => $userId]);
```

Also `canJoin`, `canLeave` (same `['groupId', 'userId']` params, default user = current); `canExclude`, `canSetModerator`, … take `userId` = target member and the actor in `fromUserId` (default current). Feature rights inside a group are legacy: `CSocNetFeatures::IsActiveFeature(SONET_ENTITY_GROUP, $groupId, 'files')`, `CSocNetFeaturesPerms::CanPerformOperation($userId, SONET_ENTITY_GROUP, $groupId, 'tasks', 'view_all')`.

## Writes

- `WorkgroupTable::add()/update()` and `UserToGroupTable::add()` throw `NotImplementedException`.
- Members: `Bitrix\Socialnetwork\Control\ServiceFactory::getInstance()->getMemberService($groupId)->add($command)` with `(new \Bitrix\Socialnetwork\Control\Member\Command\MembersCommand())->setGroupId($groupId)->setMembers(['U' . $userId])->setInitiatorId($currentUserId)`; returns `Result`. Members are access codes (`U<id>`, departments too). The service checks no initiator rights — call `Access::canModify()` first.
- Compat only: `CSocNetUserToGroup::Add()`, `CSocNetGroup::*`.
- Group disk storage: `Bitrix\Disk\Driver::getInstance()->getStorageByGroupId($groupId)` (see `rules/disk.md`).
