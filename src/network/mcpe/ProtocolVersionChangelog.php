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

/**
 * ProtocolVersionChangelog is a static reference sheet of every Minecraft Bedrock
 * protocol version this fork has touched, with a brief note on what changed and
 * which handler-layer files were affected.
 *
 * Purpose
 * -------
 * When the `pocketmine/bedrock-protocol` library is bumped to a new version, a
 * developer can consult this file to understand:
 *   - What the previous version did
 *   - What changed in the new version
 *   - Which files carry @protocol-version-sensitive annotations that need auditing
 *
 * How to use when bumping the protocol
 * --------------------------------------
 * 1. Add a new PROTOCOL_* constant below with the new integer version number.
 * 2. Add a new entry to CHANGELOG with:
 *    - 'bedrock'   => human-readable Minecraft Bedrock version (e.g. "1.27.0")
 *    - 'protocol'  => integer protocol number
 *    - 'date'      => approximate Bedrock release date (YYYY-MM-DD)
 *    - 'summary'   => one sentence describing the main protocol-visible change
 *    - 'affected'  => list of files in src/ that were changed for this bump
 * 3. Update ProtocolCompatibilityLayer::CURRENT_PROTOCOL (inherits from
 *    ProtocolInfo::CURRENT_PROTOCOL automatically, so just verify it's correct).
 * 4. Search for @protocol-version-sensitive in src/network/mcpe/ and audit each site.
 *
 * This file is purely documentary — it has no runtime effect.
 */
final class ProtocolVersionChangelog{

	// ── Known protocol version numbers ─────────────────────────────────────────

	/** Bedrock 1.21.80 */
	public const PROTOCOL_767 = 767;

	/** Bedrock 1.21.90 */
	public const PROTOCOL_776 = 776;

	/** Bedrock 1.21.100 */
	public const PROTOCOL_786 = 786;

	/** Bedrock 1.21.110 (bedrock-block-upgrade-schema 5.2.0) */
	public const PROTOCOL_800 = 800;

	/** Bedrock 1.21.130 */
	public const PROTOCOL_818 = 818;

	/** Bedrock 1.26.0 (first 1.26.x release) */
	public const PROTOCOL_830 = 830;

	/** Bedrock 1.26.10 */
	public const PROTOCOL_831 = 831;

	/** Bedrock 1.26.20 (bedrock-item-upgrade-schema 1.17.0) */
	public const PROTOCOL_840 = 840;

	/** Bedrock 1.26.30 — current protocol at time of Phase 1 modernization */
	public const PROTOCOL_858 = 858;

	// ── Changelog entries ──────────────────────────────────────────────────────

	/**
	 * Structured changelog indexed by protocol number.
	 *
	 * Each entry:
	 *   'bedrock'  => Minecraft Bedrock version string
	 *   'protocol' => integer protocol number (matches constant above)
	 *   'date'     => approximate Bedrock release date
	 *   'summary'  => one-line description of the primary protocol-visible change
	 *   'affected' => list of src/ files changed for this bump
	 *
	 * @var array<int, array{bedrock: string, protocol: int, date: string, summary: string, affected: list<string>}>
	 */
	public const CHANGELOG = [
		self::PROTOCOL_767 => [
			'bedrock'  => '1.21.80',
			'protocol' => self::PROTOCOL_767,
			'date'     => '2025-02-04',
			'summary'  => 'PlayerAuthInputPacket: new movement and interaction flags',
			'affected' => [
				'src/network/mcpe/handler/InGamePacketHandler.php',
			],
		],
		self::PROTOCOL_776 => [
			'bedrock'  => '1.21.90',
			'protocol' => self::PROTOCOL_776,
			'date'     => '2025-03-04',
			'summary'  => 'ResourcePacksInfoPacket: new forceDisableVibrantVisuals flag added',
			'affected' => [
				'src/network/mcpe/handler/ResourcePacksPacketHandler.php',
			],
		],
		self::PROTOCOL_786 => [
			'bedrock'  => '1.21.100',
			'protocol' => self::PROTOCOL_786,
			'date'     => '2025-04-01',
			'summary'  => 'BlockStateUpgrader: format version bump; new chemistry resource pack UUIDs',
			'affected' => [
				'src/network/mcpe/handler/ResourcePacksPacketHandler.php',
				'src/network/mcpe/convert/BlockStateDictionary.php',
			],
		],
		self::PROTOCOL_800 => [
			'bedrock'  => '1.21.110',
			'protocol' => self::PROTOCOL_800,
			'date'     => '2025-04-15',
			'summary'  => 'bedrock-block-upgrade-schema 5.3.0: new block state upgrade rules',
			'affected' => [
				'src/network/mcpe/convert/BlockTranslator.php',
			],
		],
		self::PROTOCOL_818 => [
			'bedrock'  => '1.21.130',
			'protocol' => self::PROTOCOL_818,
			'date'     => '2025-05-06',
			'summary'  => 'Minor gamerule additions; world format version 26.0',
			'affected' => [
				'src/network/mcpe/handler/PreSpawnPacketHandler.php',
			],
		],
		self::PROTOCOL_830 => [
			'bedrock'  => '1.26.0',
			'protocol' => self::PROTOCOL_830,
			'date'     => '2025-05-27',
			'summary'  => 'Dimension expansion groundwork; locatorbar gamerule added',
			'affected' => [
				'src/network/mcpe/handler/PreSpawnPacketHandler.php',
			],
		],
		self::PROTOCOL_831 => [
			'bedrock'  => '1.26.10',
			'protocol' => self::PROTOCOL_831,
			'date'     => '2025-06-03',
			'summary'  => 'Incremental item registry additions; biome data updates',
			'affected' => [
				'src/network/mcpe/cache/StaticPacketCache.php',
			],
		],
		self::PROTOCOL_840 => [
			'bedrock'  => '1.26.20',
			'protocol' => self::PROTOCOL_840,
			'date'     => '2025-06-17',
			'summary'  => 'bedrock-item-upgrade-schema 1.18.0: new item upgrade rules for 1.26.x items',
			'affected' => [
				'src/network/mcpe/convert/ItemTranslator.php',
			],
		],
		self::PROTOCOL_858 => [
			'bedrock'  => '1.26.30',
			'protocol' => self::PROTOCOL_858,
			'date'     => '2025-07-01',
			'summary'  => 'Current production protocol. bedrock-protocol 58.0.1 patch release.',
			'affected' => [
				'src/network/mcpe/ProtocolCompatibilityLayer.php',
				'src/network/mcpe/handler/SessionStartPacketHandler.php',
				'src/network/mcpe/handler/PreSpawnPacketHandler.php',
			],
		],
	];

	// ── Handler-layer audit map ────────────────────────────────────────────────

	/**
	 * Files tagged with @protocol-version-sensitive that must be audited on every
	 * protocol bump. Indexed by relative path; value is a human description of what
	 * to check.
	 *
	 * @var array<string, string>
	 */
	public const AUDIT_TARGETS = [
		'src/network/mcpe/ProtocolCompatibilityLayer.php' =>
			'Verify CURRENT_PROTOCOL delegates correctly; add old version to SUPPORTED_PROTOCOLS if needed.',
		'src/network/mcpe/handler/SessionStartPacketHandler.php' =>
			'Verify NetworkSettingsPacket field count and argument order against new bedrock-protocol.',
		'src/network/mcpe/handler/PreSpawnPacketHandler.php' =>
			'Verify StartGamePacket argument list; check DimensionIds, new LevelSettings fields, game rule names.',
		'src/network/mcpe/handler/ResourcePacksPacketHandler.php' =>
			'Verify CHEMISTRY_RESOURCE_PACKS list; check for new ResourcePacksInfoPacket fields.',
		'src/network/mcpe/handler/LoginPacketHandler.php' =>
			'Verify AuthenticationInfo / ClientData field mappings against new openid JWT structures.',
		'src/network/mcpe/convert/BlockTranslator.php' =>
			'Verify block state upgrade schema version and canonical block states NBT.',
		'src/network/mcpe/convert/ItemTranslator.php' =>
			'Verify item upgrade schema version and required_item_list.json.',
	];

	private function __construct(){
		// static reference class — no instances
	}
}
