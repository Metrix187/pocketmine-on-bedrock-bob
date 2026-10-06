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

## Phase 2 — Protocol Mapping & Adapter Layer

**Commit prefix:** `[Bob-Network]`

Phase 2 built the handler-layer infrastructure for protocol versioning. Three new
artifacts were added to `src/network/mcpe/`.

### 5. `ProtocolCompatibilityLayer` (new file)

**Commit:** `[Bob-Network] Add ProtocolCompatibilityLayer; wire into SessionStartPacketHandler`

| File | Change |
|---|---|
| `src/network/mcpe/ProtocolCompatibilityLayer.php` | New static utility class |
| `src/network/mcpe/handler/SessionStartPacketHandler.php` | Uses new layer; adds mismatch debug log |

`ProtocolCompatibilityLayer` is a single source of truth for which Bedrock protocol
versions this build accepts. It wraps `ProtocolInfo::CURRENT_PROTOCOL` from the
`bedrock-protocol` library (no value duplication), provides `isProtocolSupported(int)`
used by the session handshake, and a `SUPPORTED_PROTOCOLS` list for accepting one
older version during Mojang's rolling-update window.

`SessionStartPacketHandler` was updated to use `isProtocolSupported()` instead of
an inline constant comparison, removing the now-redundant `isCompatibleProtocol()`
method and the stale `//TODO` comment on `NetworkSettingsPacket::create()`. Each
field of `NetworkSettingsPacket` is now documented inline.

### 6. `StartGamePacket` Field Documentation

**Commit:** `[Bob-Network] Document StartGamePacket field mapping in PreSpawnPacketHandler`

| File | Change |
|---|---|
| `src/network/mcpe/handler/PreSpawnPacketHandler.php` | Comments only — no logic change |

Every argument to `StartGamePacket::create()` and every `LevelSettings` field
assignment now carries an inline comment explaining:
- What the field controls in the Bedrock binary protocol
- Why PM sends the specific value (e.g. `naturalregeneration=false` = server-side regen)
- What the forward path is for unimplemented features (rain/lightning, enchantment seed)
- `@protocol-version-sensitive` tags on fields whose valid values may change per version

Resolves three `//TODO` comments that had been open since the 1.19.x era.

### 7. `ProtocolVersionChangelog` (new file)

**Commit:** `[Bob-Network] Add ProtocolVersionChangelog — structured protocol bump audit trail`

| File | Change |
|---|---|
| `src/network/mcpe/ProtocolVersionChangelog.php` | New static reference class |

Provides a machine-readable + human-readable record of every protocol version
from 1.21.80 (protocol 767) through 1.26.30 (protocol 858, current):

- `PROTOCOL_*` integer constants for each known version
- `CHANGELOG` array: per-version metadata (bedrock string, date, summary, affected files)
- `AUDIT_TARGETS` array: every `@protocol-version-sensitive` file with a prose
  description of what to check — this is the checklist for future protocol bumps

No runtime effect. Purely additive.

---

## Phase 3 — Next Steps (Block State Mapping)

When the next Bedrock version ships and upstream `pmmp/BedrockProtocol` and
`pmmp/BedrockData` are updated:

1. Bump `pocketmine/bedrock-protocol` and `pocketmine/bedrock-data` constraints
2. Add new protocol constant to `ProtocolVersionChangelog::PROTOCOL_*`
3. Run: `composer update pocketmine/bedrock-protocol pocketmine/bedrock-data`
4. Run: `composer update-codegen` (regenerates block/item type constants)
5. Search `@protocol-version-sensitive` in `src/network/mcpe/` — audit each hit
6. Consult `ProtocolVersionChangelog::AUDIT_TARGETS` for the full checklist
7. Update `ProtocolCompatibilityLayer::SUPPORTED_PROTOCOLS` with the old version
   if you want a compatibility window

Reference: https://doc.pmmp.io/en/rtfd/developers/internals-docs/updating-minecraft-protocol.html
