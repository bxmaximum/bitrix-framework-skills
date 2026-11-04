# Группы, проекты и коллабы (`socialnetwork`)

Общая таблица `Bitrix\Socialnetwork\WorkgroupTable` (`b_sonet_group`). `TYPE` соответствует `Bitrix\Socialnetwork\Item\Workgroup\Type`: `Group` (`group`), `Project` (`project`), `Scrum` (`scrum`), `Collab` (`collab`). В старых строках `TYPE` бывает `null`; дополнительно проверяй `PROJECT = 'Y'`.

## Чтение

```php
$group = \Bitrix\Socialnetwork\Item\Workgroup::getById($groupId); // Workgroup|false, кешируемый реестр
$isProject = $group && $group->isProject();

$rows = \Bitrix\Socialnetwork\WorkgroupTable::query()
    ->setSelect(['ID', 'NAME', 'TYPE', 'CLOSED', 'VISIBLE', 'OWNER_ID'])
    ->where('ACTIVE', 'Y')
    ->whereIn('ID', $groupIds)
    ->fetchAll();
```

Объект `Workgroup`: `getId()`, `getFields()`, `getType()`, `isProject()`, `isScrumProject()`, `isCollab()`.

## Участники

```php
$row = \Bitrix\Socialnetwork\UserToGroupTable::query()
    ->setSelect(['ROLE'])
    ->where('GROUP_ID', $groupId)
    ->where('USER_ID', $userId)
    ->fetch();
$role = $row['ROLE'] ?? null;

$isMember = in_array($role, \Bitrix\Socialnetwork\UserToGroupTable::getRolesMember(), true);   // A, E, K
```

Роли `UserToGroupTable::ROLE_*`: владелец `ROLE_OWNER=A`, модератор `ROLE_MODERATOR=E`, участник `ROLE_USER=K`, заблокирован `ROLE_BAN=T`, приглашение/заявка `ROLE_REQUEST=Z` (ещё не участник). `AUTO_MEMBER='Y'` — добавлен синхронизацией отделов.

Группы пользователя: `UserToGroupTable::query()->setSelect(['GROUP_ID'])->where('USER_ID', $userId)->whereIn('ROLE', UserToGroupTable::getRolesMember())`.

## Проверка доступа

```php
$canView = \Bitrix\Socialnetwork\Helper\Workgroup\Access::canView(['groupId' => $groupId, 'userId' => $userId]);
$canModify = \Bitrix\Socialnetwork\Helper\Workgroup\Access::canModify(['groupId' => $groupId, 'userId' => $userId]);
```

Также `canJoin`, `canLeave` с `['groupId', 'userId']`, по умолчанию текущий пользователь. `canExclude`, `canSetModerator`, … принимают целевого участника в `userId`, инициатора в `fromUserId` (по умолчанию текущий). Права инструментов legacy: `CSocNetFeatures::IsActiveFeature(SONET_ENTITY_GROUP, $groupId, 'files')`, `CSocNetFeaturesPerms::CanPerformOperation($userId, SONET_ENTITY_GROUP, $groupId, 'tasks', 'view_all')`.

## Запись

- `WorkgroupTable::add()/update()` и `UserToGroupTable::add()` бросают `NotImplementedException`.
- Участники: `Bitrix\Socialnetwork\Control\ServiceFactory::getInstance()->getMemberService($groupId)->add($command)` с `(new \Bitrix\Socialnetwork\Control\Member\Command\MembersCommand())->setGroupId($groupId)->setMembers(['U' . $userId])->setInitiatorId($currentUserId)` возвращает `Result`. Участники задаются кодами доступа (`U<id>`, также отделы). Сервис не проверяет права инициатора: сначала `Access::canModify()`.
- Для совместимости: `CSocNetUserToGroup::Add()`, `CSocNetGroup::*`.
- Диск группы: `Bitrix\Disk\Driver::getInstance()->getStorageByGroupId($groupId)` (`rules/disk.md`).
