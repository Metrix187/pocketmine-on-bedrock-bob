## Beat 1: 0:00 to 0:15

0:00:00.00 - 0:00:04.86: This is PocketMine-on-Bedrock: a Minecraft Bedrock server modernization built with IBM Bob.

0:00:04.86 - 0:00:10.95: Bob analyzed dependencies, refactored the core, wrote test sources, and supported the live build-test-fix loop.

0:00:10.95 - 0:00:15.00: Bob was the dev loop, not a bolted-on code generator.

## Beat 2: 0:15 to 0:50

0:00:15.00 - 0:00:23.45: Here's what that actually looked like. These are real Bob sessions from the local chat history, backed by atomic Bob commits.

0:00:23.45 - 0:00:29.89: Bob raised the PHP floor, upgraded dependency constraints, and worked on protocol compatibility and packet handlers.

0:00:29.89 - 0:00:35.92: It also wrote login and handshake test sources. A full automated-test pass is not claimed.

0:00:35.92 - 0:00:44.77: I supplied the final 26.52 patch. Bob reviewed it and verified the retry. The session IDs and excerpts are in the repository.

0:00:44.77 - 0:00:50.00: The process is the product. This is the workflow the build stands on.

## Beat 3: 0:50 to 1:20

0:00:50.00 - 0:00:58.36: And the artifact works. This take extracts a newly built Windows deploy package into a fresh folder.

0:00:58.36 - 0:01:06.72: It checks the bundled PHP CLI and runs the start script. The server boots and reaches Done.

0:01:06.72 - 0:01:15.57: The originally named fixed ZIP wasn't available. This is a new local PHAR build from the working tree.

0:01:15.57 - 0:01:20.00: The production assistant completed the packaging steps before recording.

## Beat 4: 1:20 to 1:45

0:01:20.00 - 0:01:26.82: The same server session listens on UDP 19132, the standard Bedrock port.

0:01:26.82 - 0:01:35.34: On Windows, Get-NetUDPEndpoint replaces the Linux ss command. This is a real captured command result.

0:01:35.34 - 0:01:45.00: The process ID, bind address, and LAN address are visible. Public internet access has not been verified.

## Beat 5: 1:45 to 2:25

0:01:45.00 - 0:01:55.56: Now a real Bedrock client joins. This is the installed Windows 26.52 client connecting to localhost on port 19132.

0:01:55.56 - 0:02:01.67: The build accepts protocol 2193. The player joins and spawns in-world.

0:02:01.67 - 0:02:09.44: The base server banner still says 26.44. The real join proves this client path.

0:02:09.44 - 0:02:15.56: The final integration fixed jigsaw data ordering and hashed block IDs.

0:02:15.56 - 0:02:25.00: Those fixes came from the human developer, with Bob reviewing the patch and supporting the verification loop.

## Beat 6: 2:25 to 2:45

0:02:25.00 - 0:02:30.00: That's PocketMine-on-Bedrock, built with IBM Bob. Theme 2: Modernize What Matters.

0:02:30.00 - 0:02:39.09: The repository contains source, real Bob evidence, a runnable package, and testing instructions. Download it to reproduce this local demo.

0:02:39.09 - 0:02:45.00: Next: clean reproducible builds, full tests, plugins, and public hosting. Thanks for watching.
