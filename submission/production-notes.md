# Demo production record

Final video: `bob-demo-final.mp4`, 165.000 seconds, 1920x1080, 30 fps, H.264 video and a silent AAC track.
All narration is burned text. FFprobe checks and a full FFmpeg decode passed.

## Six shots

1. 0:00-0:15: plain title card. The Bob development-loop framing is prominent.
2. 0:15-0:50: real Bob database excerpts, labeled as verbatim log excerpts, plus actual Bob UI footage. No recreated Bob UI or fabricated sessions.
3. 0:50-1:20: actual Windows Terminal capture of fresh ZIP extraction, bundled PHP CLI, `start.ps1`, and boot to Done. The final captured frame is held to fit the prescribed beat.
4. 1:20-1:45: a genuine captured terminal result from the same fresh server process. Windows `Get-NetUDPEndpoint` replaces Linux `ss -uln`. The captured result is held on screen. No public reachability is claimed.
5. 1:45-2:25: genuine Minecraft for Windows footage showing the server list, connection, and spawn in-world. Client package version 1.26.5203.0. This is the same PHAR build used in the clean deployment take. The camera stands in the loaded world; no walking claim is made.
6. 2:25-2:45: plain recap card with the repository, localhost address, and roadmap.

## Changes from the supplied script

The named `pocketmine-deploy_0_3e8d-fixed.zip` was not present locally. The production assistant rebuilt a PHAR from the actual local working tree and made `pocketmine-bob-local-build.zip` instead. This is disclosed in the video, README, and provenance file.

The environment has Windows PHP and no WSL/Linux. The video therefore shows Windows launch and socket commands.

The real evidence supports core modernization, dependency/protocol work, test-source generation, and live debugging. Unsupported plugin-scaffolding and three-packaging-fix claims were removed. The final 26.52 patch is credited to the human developer, with Bob review and verification.

The base banner remains 26.44 while the compatibility layer accepts protocol 2193. A real Bedrock 26.52 client joined and spawned. Full PHPUnit/PHPStan passes are not claimed.

## Post-demo CI repair (October 18)

The production assistant repaired Composer's mismatched manifest/lockfile by installing the committed protocol/data snapshots as mirrored path repositories. Only the protocol snapshot's package metadata was normalized; its PHP source remains the captured demo source. Docker now includes these paths in its build context. The upstream collaborator auto-approval job skips when the dispatch app ID is not configured.

PHP-CS-Fixer 3.75 applied the ten formatting changes reported by CI. Three login-test fixtures were corrected to match the installed OpenID JWT models without relaxing validation. Local Windows PHP 8.4.16 PHPUnit results: 226 tests, 72,618 assertions, no failures/errors, 1 skipped, 28 deprecation notices. PHPStan still reports 116 findings. Remote checks remain authoritative for Linux/PHP-version coverage; no full green CI is claimed. Published MP4/PDF/deployment artifacts retain their original hashes and provenance.

The runtime demo is local. The submission's demo link points to a runnable package and testing instructions, rather than claiming an internet-hosted server.

## Delivery

The finished MP4 is in `C:/Users/skyuu/Downloads/bob-demo-final.mp4` and the GitHub demo release.
The release also includes the 10-page PDF pitch deck and runnable Windows deployment ZIP.
The Chrome submission draft has been saved. The final hackathon Submit button has not been pressed.
