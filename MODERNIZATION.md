# PocketMine-MP Modernization — Phase 1 Record

> Branch: `modernize/phase1-dependency-bump`
> Base version: `5.44.4` (bedrock-1.26.30)

This document records all changes made during Phase 1 of the hackathon modernization
effort. Each section maps to an atomic git commit prefixed with `[Bob-Dependency-Bump]`
or `[Bob-Refactor]`.

---

## 1. PHP Floor Raised to 8.2

**Commit:** `[Bob-Dependency-Bump] Raise PHP floor to 8.2, drop EOL 8.1 from CI matrix`

| File | Change |
|---|---|
| `composer.json` | `"php": "^8.1"` → `"php": "^8.2"`, `platform.php` `8.1.0` → `8.2.0` |
| `.github/workflows/main.yml` | Removed `"8.1"` from PHP test matrix |

**Why:** PHP 8.1 reached end-of-life November 2024. PHP 8.2 introduces `readonly` classes,
standalone `true`/`false`/`null` return types, and DNF types. Dropping 8.1 removes
maintenance burden and unblocks the modernizations in section 3.

---

## 2. Dependency Bumps

**Commit:** `[Bob-Dependency-Bump] Bump pocketmine/* bedrock libs, symfony/filesystem, phpstan, phpunit`

| Package | Old Constraint | New Constraint | Notes |
|---|---|---|---|
| `pocketmine/bedrock-block-upgrade-schema` | `~5.2.0+bedrock-1.21.110` | `~5.3.0` | Drops pinned bedrock version suffix |
| `pocketmine/bedrock-item-upgrade-schema` | `~1.17.0+bedrock-1.26.20` | `~1.18.0` | Drops pinned bedrock version suffix |
| `pocketmine/bedrock-protocol` | `~58.0.0+bedrock-1.26.30` | `~58.0.1+bedrock-1.26.30` | Patch bump |
| `symfony/filesystem` | `~6.4.0` | `~7.4.0` | Symfony 7 LTS |
| `phpstan/phpstan` | `2.1.46` (pinned) | `2.2.17` | Latest stable |
| `phpunit/phpunit` | `^10.5.24` | `^11.0` | Major bump, PHP 8.2+ minimum |
| `pocketmine/bedrock-data` | `~6.7.0+bedrock-1.26.30` | *(unchanged)* | No newer stable release available |

> **Note:** `composer update` must be run with PHP 8.2+ installed to regenerate
> `composer.lock`. This will happen automatically in CI on the next push.

---

## 3. PHPStan Null-Safety Fixes

**Commit:** `[Bob-Refactor] Fix PHPStan actual-problems: null-safety in NetworkSession, VersionInfo, ServerConfigGroup`

These were pre-existing type errors that the project tracked in
`tests/phpstan/configs/actual-problems.neon` with suppression entries.
They are now fixed at source.

### `NetworkSession.php` — 27 suppressions removed

All lifecycle methods that operate on `$this->player` or `$this->info` after they
are guaranteed to be non-null now use `?? throw new AssumptionFailedError()`,
consistent with the existing idiom in the file. This makes invariant violations
loud failures rather than silent null-dereferences.

Methods fixed: `createPlayer`, `setAuthenticationStatus`, `onServerLoginSuccess`,
`beginSpawnSequence`, `onClientSpawnResponse`, `onServerDeath`, `onServerRespawn`,
`syncAvailableCommands`, `prepareClientTranslatableMessage`, `onChatMessage`,
`onJukeboxPopup`, `startUsingChunk`.

### `VersionInfo.php` — 2 suppressions removed

`Phar::getMetadata()` returns `mixed`. Added `is_array() && is_string()` guard
before accessing `$meta["git"]`, eliminating both the offset-access-on-mixed and
the string|null-does-not-accept-mixed errors.

### `ServerConfigGroup.php` — 4 suppressions removed

`getopt()` returns `array<string,string|false>|false`. Added `is_array() && is_string()`
guards in `getConfigString()` and `getConfigInt()` before using the returned value,
eliminating unsafe `(int)` and `(string)` casts from `mixed`.

---

## 4. PHP 8.2 `readonly` Modernizations

**Commit:** `[Bob-Refactor] Apply PHP 8.2 readonly modernizations; clean fixed PHPStan suppressions`

Promoted pure value objects to use `readonly` at the class or property level.

| File | Change |
|---|---|
| `src/scheduler/BulkCurlTaskOperation.php` | `final class` → `readonly final class` |
| `src/network/mcpe/ItemStackInfo.php` | `final class` → `readonly final class` |
| `src/entity/EntitySizeInfo.php` | All three properties marked `readonly` individually |
| `src/item/ArmorTypeInfo.php` | All five promoted + one body-assigned properties marked `readonly` |

`readonly` classes are a PHP 8.2 feature. They guarantee that all properties are
write-once (set in the constructor), preventing accidental mutation and enabling
better optimization by static analysers and JIT.

---

## Phase 2 — Next Steps

Phase 2 targets the Bedrock protocol layer. The groundwork is now in place:

- [ ] Identify the next Minecraft Bedrock release target (post-1.26.30)
- [ ] Bump `pocketmine/bedrock-protocol` and `pocketmine/bedrock-data` to the
      new protocol version once upstream packages are published
- [ ] Audit `src/network/mcpe/convert/` for hardcoded protocol constants that
      need updating
- [ ] Audit `src/network/mcpe/handler/` for packet handlers referencing
      renamed or restructured packet types
- [ ] Run codegen: `composer update-codegen` to regenerate block/item type
      constants from the new bedrock-data
- [ ] Update `src/VersionInfo.php::BASE_VERSION` and `VersionInfo::BUILD_CHANNEL`

Reference: https://doc.pmmp.io/en/rtfd/
