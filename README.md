# PocketMine-on-Bedrock

Built with IBM Bob for **Building with IBM Bob**, Theme 2: Modernize What Matters.
Solo creator: **skyuu72**, team **quantara.cv**, GitHub **Metrix187**.

**Bob was the dev loop, not a bolted-on code generator.**
Real session excerpts and the inherited `[Bob-*]` commit history show the process.

## What changed during the hackathon

This extends the existing PocketMine-MP server. The work does not claim ownership of upstream PocketMine or Amber-PM libraries.
Work shown here took place during September 28 - October 18, 2026.

- PHP floor raised from 8.1 to 8.2, dependency constraints updated, and null-safety/readonly refactors committed with Bob.
- Protocol compatibility checks, an audit changelog, and packet-handler documentation added.
- Login, handshake, and compatibility test sources added by Bob. Full PHPUnit/PHPStan results are **not** claimed for tonight's build.
- Local Bedrock protocol integration and pre-spawn packet handling updated.
- Human-provided 26.52 fixes address jigsaw packet ordering and hashed block IDs. Bob applied/reviewed these and drove the live retry loop. See `docs/bob-sessions/04-session-excerpt.md`.
- Tonight's video-production session regenerated the export's Composer autoloader and built the PHAR from the local working tree. These packaging steps were performed by the production assistant, not attributed to Bob.

## Demo video

[Watch the Unlisted demo on YouTube](https://youtu.be/C2YtDaEYOCM).
The captioned demo runs 2:45 with silent audio.

## Run the verified demo

Download **pocketmine-bob-local-build.zip** from this repository's demo release.
Extract the entire ZIP on Windows x64. It includes PHP CLI 8.4.16 and required extensions.
Open PowerShell in that folder and run:

```powershell
.\start.ps1 -extraPocketMineArgs "--no-wizard --disable-ansi"
```

Wait for **Done**. On this PC, join **127.0.0.1**, UDP **19132**, using **Minecraft Bedrock 26.52**.
The installed Windows client used tonight is `1.26.5203.0`. The compatibility layer accepts protocol **2193**.
The server's base startup banner and default advertisement still say **26.44**. The client actually joined and spawned in-world.
No custom plugins ship in this demo. This project modernizes the server core.

The demonstration is **local**. Public internet access is not verified. To test on a judge's machine, run the deploy package there and join localhost.
The supplied local-demo configuration disables Xbox-auth enforcement. It is not a public-hosting configuration.
Type `stop` in the server console to stop cleanly.

## Source and build

Branch: `modernize/phase1-dependency-bump`.
Base commit: `326fb018aeb9aea6a4def2232a4269ca70bf0edc`, plus the local working-tree changes committed in this submission.
`demo-source-overrides/pocketmine/` preserves the local BedrockProtocol and BedrockData used in the build.
Composer installs these checked-in snapshots as mirrored path packages, so no manual vendor overwrite or moving upstream branch is needed. Protocol package metadata uses the `pocketmine/bedrock-protocol` name expected by the server; its PHP sources are unchanged from the demo snapshot. Version `59.0.0` identifies this local snapshot, not an upstream release.
With PocketMine's PHP CLI and extensions available, install the locked dependencies and build:

```powershell
composer install --no-dev --classmap-authoritative --prefer-dist
php '-dphar.readonly=0' build\server-phar.php --git=<actual-git-state>
```

For development, use `composer install` including dev dependencies, then run `vendor/bin/phpunit --bootstrap vendor/autoload.php --fail-on-warning tests/phpunit` and `vendor/bin/phpstan analyze --no-progress --memory-limit=2G`. The CI workflow runs these checks across PHP 8.2–8.5; a successful dependency install alone does not establish a passing test suite.

Post-demo CI repair on October 18: a fresh locked install succeeded on Windows PHP 8.4.16, and PHPUnit completed with 226 tests / 72,618 assertions, no failures or errors, one skipped test and 28 deprecation notices. Three login fixtures were corrected to the installed OpenID models' required fields. PHPStan still reports 116 findings; a full green CI is not claimed. These repairs do not change the already-recorded deployment or video.

The downloadable PHAR was built tonight before this submission commit. Its metadata retains `326fb018a...-dirty` to identify that working-tree origin.
The originally referenced `pocketmine-deploy_0_3e8d-fixed.zip` was unavailable on the machine. The demo uses the newly built local package, with SHA-256 recorded in `submission/build-provenance.json`.

## Evidence and artifacts

- `docs/bob-sessions/`: five real Bob message excerpts with source IDs and timestamps.
- `MODERNIZATION.md`: historical implementation notes. Earlier version references there describe earlier phases.
- `submission/`: pitch deck and submission text.
- Demo release: MP4 and runnable Windows deployment ZIP.

## Next steps

Run the full automated test suite, finish broader protocol coverage, add plugins, and deploy a secured public server.

## Attribution

PocketMine-MP is LGPL-3.0 licensed. Existing upstream history and license are preserved.
Amber-PM BedrockProtocol and BedrockData are included as the local dependency sources used for the demo. Their licenses remain in their folders.

