# Safety and review

## Safety

- `up`/`down` change schema and data: production only with explicit human approval, never because a local `up` passed.
- `down` without a version name and `delete` are destructive; name the version.
- `mark --as=installed` skips code: only to align status after a known manual change, never instead of a failed `up`.
- No secrets, tokens or personal dumps in migrations or exchange dirs.

## Anti-patterns

| Don't | Do |
| --- | --- |
| Edit a migration already applied elsewhere | New migration |
| Ids copied from a local DB | Resolve by `CODE`/`XML_ID` via helpers |
| Plain `add*` that fails on a second copy | `save*` / `*IfNotExists` |
| `add*IfNotExists` where fields must change | `save*` |
| Commit builder output unread | Review generated arrays and files |
| Business logic in migrations | Module services; migration only calls them |

## Review focus

- Destructive deletes without filters (`deleteOptions` without `NAME`, mass `down`).
- Ignored `Result` / missing `outError` + `return false`.
- Custom module classes used without `Loader::includeModule`.
- Config without explicit `migration_dir` + `migration_table`.

## Before finishing

- [ ] File created by `add` or a builder; `namespace Sprint\Migration`, class = file name, `$description` set.
- [ ] `up()` is idempotent or safe to run once per copy; `down()` implemented or explained.
- [ ] `ls --new` lists it; `up <Version>` passes on a local copy; `ls` shows `installed`.
- [ ] With `down()`: `redo <Version>` once on a disposable copy.
- [ ] Exchange files (if any) committed with the version.
