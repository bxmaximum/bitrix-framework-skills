# Версии ядра и совместимость

- Версия main: `bitrix/modules/main/classes/general/version.php`, константа `SM_VERSION`.
- Версия другого модуля: `bitrix/modules/<module>/install/version.php`.
- `Baseline` — версия, на которую рассчитаны базовые примеры; `Verified` — реально проверенное ядро, а не минимальное требование.
- `**Since main X.Y**` — API требует эту версию. Рядом должен быть вариант для старого ядра.
- `**Since main X.Y\***` — точная первая версия неизвестна: API найден в указанной версии и отсутствует в main 25.100.500. Он мог появиться раньше. Для `**Since rest 26.200\***` сравниваются версии rest в соответствующих эталонах.
- Для модулей без старого эталона не выдумывай `Since`: проверяй `class_exists()` / `method_exists()` и предусмотренный старый путь. Точную границу можно подтвердить диффом версии модуля.
- Источник истины — `bitrix/modules/<module>/lib/`. С main 26.600 многие пути стали PascalCase (`lib/Data`); ищи без учёта регистра.

| Возможность | Since main |
| --- | --- |
| Маршруты `/local/routes` | 21.400 |
| `/local/.settings.php` | 24.100 |
| `Bitrix\Main\Validation` | 24.300 |
| Render-методы контроллера | 25.700 |
| Новые `make:*`: service, entity, event, message, agent | 25.900 |
| Persistent Storage | 25.1100 |
| Атрибуты фильтров, правила параметров action, LoggerFactory | 26.250* |
| Установщик модуля на `UpdateSystem\Migration` | 26.600* |
| `#[ActionAccess]` | 26.600* |
| Фича-флаги `Config\Feature` | 26.700 |
| DI конструктора консольной команды, `Type\Callback` для агентов | 26.900 |

До 26.900 консольная команда получает сервис внутри `execute()`; строковая регистрация агентов остаётся вариантом для старого ядра. Подробности — `bitrix-console-commands` и `bitrix-background-jobs`.
