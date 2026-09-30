---
name: bitrix-extensions
description: 'Bitrix JS/CSS extensions — ID-to-path mapping, config.php, bundle.config.js, lang phrases, Extension::load, Runtime.loadExtension, @bitrix/cli build. Use when adding, building or loading frontend code in /local or a module.'
---

# Bitrix JS/CSS extensions

Baseline: main 23.0+ · Verified: main 26.800.0, ui 26.687.0

## ID → directory

Every dot of the ID is a directory level (`Extension::getPath()`): `vendor.module.editor` → `js/vendor/module/editor/`, searched in `/local/js/` first, then `/bitrix/js/`. At least 2 segments, each `[a-z0-9_-]`. A dotted directory (`/local/js/vendor.module/editor/`) cannot be loaded by ID.

| Code | Where |
| --- | --- |
| Project | `/local/js/vendor/module/editor/` → ID `vendor.module.editor` |
| Distributed module `vendor.module` | `install/js/vendor/module/editor/`, copied to `/local/js/` on install |
| Kernel | `/bitrix/js/<module>/<ext>/` (`ui.buttons`) — never edit; a same-path copy in `/local/js/` shadows it and stops getting updates |

```php
public function InstallFiles(): void
{
    CopyDirFiles(__DIR__ . '/js', \Bitrix\Main\Application::getDocumentRoot() . '/local/js', true, true); // 4th arg = recursive
}

public function UnInstallFiles(): void
{
    DeleteDirFilesEx('/local/js/vendor/module'); // DeleteDirFiles() is flat, leaves nested dirs
}
```

## Layout

```
/local/js/vendor/module/editor/
├── src/index.js         # ES6+ entry
├── dist/                # built bundles — commit and deploy
├── lang/ru/config.php   # optional $MESS phrases
├── bundle.config.js     # deploy too: server reads `namespace` from it
└── config.php
```

Scaffold: `bitrix create`; build: `bitrix build` in the extension dir (`@bitrix/cli`).

```javascript
module.exports = {
    input: './src/index.js',
    output: './dist/editor.bundle.js',
    namespace: 'BX.Vendor.Module.Editor',
    browserslist: true,
};
```

- CSS: `import './style.css';` in the entry → `dist/editor.bundle.css`.
- Imports: `import { Loc, Runtime } from 'main.core';`, legacy CoreJS 1.0 `import 'main.date';`.
- The build rewrites `config.php` (`js`, `css`, `rel` from imports) unless `adjustConfigPhp: false`.

## config.php

```php
<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) { die(); }

return [
    'js' => './dist/editor.bundle.js',
    'css' => './dist/editor.bundle.css',
    'rel' => ['main.core', 'ui.buttons'],
    'skip_core' => false,
    'settings' => ['maxItems' => 50],
];
```

Other keys: `lang_additional` (computed phrases), `oninit` (callable, gets and returns the config), `controllerEntrypoint` (JS function for `renderExtension()`, see `bitrix-controllers`).

Settings in JS: `Extension.getSettings('vendor.module.editor').get('maxItems', 20)` (`main.core`).

## Phrases

If `lang/` exists, the kernel loads `lang/<LANGUAGE_ID>/config.php` with the extension (no `lang` key needed) and exposes it to JS: `Loc.getMessage('VENDOR_EDITOR_TITLE')`. Phrases share one global `BX.message` — prefix codes with vendor + extension.

```php
<?php
$MESS['VENDOR_EDITOR_TITLE'] = 'Editor';
```

## Loading

```php
\Bitrix\Main\UI\Extension::load(['vendor.module.editor', 'ui.buttons']); // unknown ID = silent no-op
```

```javascript
import { Runtime } from 'main.core';

Runtime.loadExtension('vendor.module.editor').then(({ Editor }) => {
    new Editor().render();
});
```

`Runtime.loadExtension(...ids)` resolves with the merged exports of the requested extensions (from their `namespace`), after JS/CSS/phrases/settings are on the page.

## Checklist

- [ ] Directory path equals the ID with dots → `/`; module extensions prefixed `vendor.module.`.
- [ ] Load only by ID (`Extension::load()`, `rel`, `Runtime.loadExtension()`); no `<script src>` / `Asset::addJs()` to any `/local/js/…` or `/bitrix/js/…` file — updates move files (ui 26.675 moved `ui.actionpanel` into `dist/`).
- [ ] `dist/` rebuilt after `src/` changes; `bundle.config.js` deployed with it.
- [ ] Every dependency in `rel`.
- [ ] UI strings in `lang/<lang>/config.php`, read via `Loc.getMessage()`.
