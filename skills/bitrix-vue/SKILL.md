---
name: bitrix-vue
description: 'BitrixVue 3 (ui.vue3): createApp, $Bitrix.Loc, Pinia и компоненты ядра в страницах и расширениях Битрикс. Используй для реактивного интерфейса, состояния, виджетов и миграции с Vue 2.'
---

# BitrixVue 3

Baseline: main 23.0+ · Verified: main 26.1000.0, ui 26.700.0

Перед PHP-кодом прочитай `bitrix-framework`, если ещё не читал его в этой задаче.

`ui.vue3` — Vue 3 с плагином BitrixVue (`$Bitrix`), единый экземпляр на страницу. Компиляция шаблонов во время выполнения; SFC `.vue` и SSR нет. Vue 2 (`ui.vue`, глобальный `BX.BitrixVue`) устарел, новый код пиши на Vue 3.

## В расширении (предпочтительно)

```javascript
import { BitrixVue, ref } from 'ui.vue3';

BitrixVue.createApp({
    setup() {
        return { items: ref([]) };
    },
    mounted() {
        BX.ajax.runAction('vendor:module.item.list').then((r) => {
            this.items = r.data.items;
        });
    },
    template: `
        <h2>{{ $Bitrix.Loc.getMessage('VENDOR_ITEMS_TITLE') }}</h2>
        <div v-for="item in items" :key="item.id">{{ item.name }}</div>
    `,
}).mount('#vendor-items');
```

- Импортируй `BitrixVue` и API Vue (`ref`, `computed`, `markRaw`, `defineComponent` …) из `ui.vue3`, как ядро, а не из `ui.vue3.bitrixvue`. Достаточно `rel: ['ui.vue3']`.
- Настройка расширения и фраз: `bitrix-extensions`.

## На PHP-странице без сборки

```php
\Bitrix\Main\UI\Extension::load('ui.vue3');
```

```html
<div id="vendor-app"></div>
<script>
    BX.Vue3.BitrixVue.createApp({ template: '<div>Здравствуйте</div>' }).mount('#vendor-app');
</script>
```

Глобальный объект — `BX.Vue3.BitrixVue`. `BX.BitrixVue` — Vue 2: здесь он не определён либо молча запустит Vue 2, если загружен и `ui.vue`.

## `$Bitrix` в каждом компоненте

| Член | Назначение |
| --- | --- |
| `$Bitrix.Loc.getMessage(code, {'#N#': n})` | Фразы из `BX.message`; вне Vue — `Loc` из `main.core` |
| `$Bitrix.eventEmitter` | `EventEmitter` приложения |
| `$Bitrix.Data.get/set` | Общие данные приложения |
| `$Bitrix.RestClient.get()` / `$Bitrix.PullClient.get()` | Клиенты REST / Pull по умолчанию (`bitrix-pull`) |

`BitrixVue.getFilteredPhrases(this, 'VENDOR_')`: первый аргумент в Vue 3 — экземпляр компонента (документация показывает форму Vue 2 без него).

## Готовые средства ядра

- Компоненты: `ui.vue3.components.*` (`button`, `popup`, `hint`, `switcher` …), например `import { Counter } from 'ui.vue3.components.counter'` (`components: { Counter }` → `<Counter :value="5"/>`; **Since ui 26.200\***; на старом ядре оберни `ui.cnt` самостоятельно). Настройки виджетов: `bitrix-ui`.
- Состояние и маршруты: `ui.vue3.pinia`, `ui.vue3.vuex`, `ui.vue3.router`.
- Отложенный компонент: `BitrixVue.defineAsyncComponent('vendor.module.heavy', 'HeavyForm')` загружает расширение при первом выводе.
- Расширяемый компонент: `BitrixVue.mutableComponent(name, def)`; изменения через `mutateComponent()` / `cloneComponent()`.

## Виджеты ядра внутри Vue

Экземпляры Popup / Menu / Dialog / Popover / ActionPanel оставляй нереактивными: `this.popup = markRaw(new Popup(...))` либо храни вне `data()`. Виджеты, переделанные с ui 26.650, используют нативные поля `#private`; реактивный Proxy вызывает `TypeError: Cannot read private member`.

## Отладка

`define('VUEJS_DEBUG', true);` в `/local/php_interface/init.php` включает отладочную сборку и devtools только для разработки. Вместе с ним `VUEJS_LOCALIZATION_DEBUG` показывает коды фраз вместо текста.

## Переход с Vue 2 (`ui.vue`)

- `BX.BitrixVue` / `import … from 'ui.vue'` → `ui.vue3`.
- `BitrixVue.component()` / `.directive()` не регистрируют компоненты в Vue 3 (ошибка в консоли). Используй локальные `components: {}`, `app.component()` либо `mutableComponent()`.
- `getFilteredPhrases(prefix)` → `getFilteredPhrases(this, prefix)`; `destroyed` → `unmounted`; фильтров и `$on`/`$off` нет.

## Проверка

- [ ] Только `ui.vue3`, все импорты из него; встроенные скрипты используют `BX.Vue3.BitrixVue`.
- [ ] Экземпляры виджетов ядра обёрнуты в `markRaw()`.
- [ ] Фразы читаются через `$Bitrix.Loc` / `Loc`, не зашиты в код.
- [ ] Engine вызывается через HttpClient.action() при поддержке ядром либо BX.ajax.runAction(), с CSRF; контракт — `bitrix-extensions`.
- [ ] Подписки Pull сняты в `unmounted`.
