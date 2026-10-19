<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\network\mcpe;

use pocketmine\network\mcpe\protocol\ProtocolInfo;

/**
 * ProtocolCompatibilityLayer provides a single place to reason about which client
 * protocol versions this server build can accept, and what the compatibility policy is.
 *
 * Background
 * ----------
 * PocketMine-MP delegates all binary packet encoding/decoding to the
 * `pocketmine/bedrock-protocol` Composer library. That library is versioned against a
 * specific Minecraft Bedrock release (e.g. bedrock-1.26.30). When Mojang releases a new
 * Bedrock version with protocol changes, the upstream workflow is:
 *
 *   1. pmmp/BedrockProtocol is updated with new/changed packet field definitions.
 *   2. The `bedrock-protocol` constraint in composer.json is bumped.
 *   3. Handler-layer code in *this* repo is adapted to new exposed fields.
 *
 * This class handles step 3's version-gating logic in one place, rather than scattered
 * through individual handlers. It is intentionally a pure static utility with no state.
 *
 * Adding support for a new protocol version
 * ------------------------------------------
 * 1. Bump CURRENT_PROTOCOL below to match ProtocolInfo::CURRENT_PROTOCOL in the new
 *    bedrock-protocol release.
 * 2. Add the old version to SUPPORTED_PROTOCOLS if you want to keep backward
 *    compatibility for one release cycle (optional, not always feasible).
 * 3. Update MINECRAFT_VERSION_DISPLAY to the human-readable Bedrock version string.
 * 4. Search for @protocol-version-sensitive annotations in the handler layer and
 *    audit each site for compatibility with the new protocol.
 */
final class ProtocolCompatibilityLayer{

	/**
	 * The single protocol version this build's bedrock-protocol library was compiled for.
	 * Derived directly from the library constant so there is exactly one source of truth.
	 */
	public const CURRENT_PROTOCOL = ProtocolInfo::CURRENT_PROTOCOL;

	/**
	 * Human-readable Minecraft Bedrock version string matching the current protocol.
	 * Update this when bumping CURRENT_PROTOCOL.
	 */
	public const MINECRAFT_VERSION_DISPLAY = ProtocolInfo::MINECRAFT_VERSION_NETWORK;

	/**
	 * Additional protocol version numbers this server will accept on the initial
	 * RequestNetworkSettings handshake.
	 *
	 * Rationale: During a Mojang rolling update, clients on the old version continue
	 * connecting for days after a new version ships. Accepting one previous protocol
	 * version reduces "outdated server" disconnects during that window. Note that
	 * accepting an older protocol version means the binary packet structures for that
	 * version may differ slightly — only add a version here if the delta is small enough
	 * that the server handles it gracefully (typically: same protocol, different patch).
	 *
	 * Leave empty to accept only CURRENT_PROTOCOL (safest default).
	 *
	 * @var int[]
	 * @phpstan-var list<int>
	 */
	/**
	 * Protocols accepted in addition to CURRENT_PROTOCOL.
	 *
	 * 26.50 shipped as protocol 2192 (CloudburstMC codec / preview builds).
	 * 26.51 / 26.52 hotfix shipped as protocol 2193.
	 *
	 * These versions differ from 26.44 (protocol 2169) in:
	 *   - Block network IDs: 2192+ uses hashed IDs, 2169 uses numeric runtime IDs
	 *   - bedrock-data: canonical_block_states, required_item_list, block_state_meta_map
	 *     must be the 1.26.50-versioned files
	 *   - JigsawStructureDataPacket: real NBT from jigsaw_structures-1.26.50.json required
	 *
	 * Per-protocol chunk encoding is handled in ChunkCache (keyed by protocolId),
	 * and broadcast encoding is grouped by (compressor, protocolId) in StandardPacketBroadcaster.
	 */
	public const SUPPORTED_PROTOCOLS = [
		2192, // Bedrock 26.50
		2193, // Bedrock 26.51 / 26.52 hotfix
	];

	/**
	 * Returns true if the given client protocol version is one this server can handle.
	 * Used by SessionStartPacketHandler to decide whether to proceed or send a
	 * disconnectIncompatibleProtocol response.
	 */
	public static function isProtocolSupported(int $protocolVersion) : bool{
		if($protocolVersion === self::CURRENT_PROTOCOL){
			return true;
		}
		return \in_array($protocolVersion, self::SUPPORTED_PROTOCOLS, true);
	}

	/**
	 * Returns a descriptive string for logging/disconnect messages when a client
	 * connects with an unsupported protocol version.
	 */
	public static function describeProtocolMismatch(int $clientProtocol) : string{
		return \sprintf(
			"Client protocol %d is not supported (server supports %d%s)",
			$clientProtocol,
			self::CURRENT_PROTOCOL,
			self::SUPPORTED_PROTOCOLS !== []
				? ", also accepts: " . \implode(", ", self::SUPPORTED_PROTOCOLS)
				: ""
		);
	}

	private function __construct(){
		// static utility class — no instances
	}
}
