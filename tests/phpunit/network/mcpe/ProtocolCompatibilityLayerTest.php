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

use PHPUnit\Framework\TestCase;
use pocketmine\network\mcpe\protocol\ProtocolInfo;

/**
 * Unit tests for ProtocolCompatibilityLayer (Phase 2 addition).
 *
 * These tests are purely in-process — no I/O, no server, no RakLib.
 * They validate:
 *   - The current protocol is always accepted.
 *   - Protocols not listed in SUPPORTED_PROTOCOLS are rejected.
 *   - describeProtocolMismatch() produces the expected string shape.
 *   - The CURRENT_PROTOCOL constant correctly mirrors ProtocolInfo.
 */
class ProtocolCompatibilityLayerTest extends TestCase{

	// ── CURRENT_PROTOCOL delegation ────────────────────────────────────────────

	public function testCurrentProtocolMirrorsProtocolInfo() : void{
		self::assertSame(
			ProtocolInfo::CURRENT_PROTOCOL,
			ProtocolCompatibilityLayer::CURRENT_PROTOCOL,
			'CURRENT_PROTOCOL must delegate to ProtocolInfo::CURRENT_PROTOCOL with no extra indirection'
		);
	}

	public function testMinecraftVersionDisplayMirrorsProtocolInfo() : void{
		self::assertSame(
			ProtocolInfo::MINECRAFT_VERSION_NETWORK,
			ProtocolCompatibilityLayer::MINECRAFT_VERSION_DISPLAY,
			'MINECRAFT_VERSION_DISPLAY must delegate to ProtocolInfo::MINECRAFT_VERSION_NETWORK'
		);
	}

	// ── isProtocolSupported ────────────────────────────────────────────────────

	public function testCurrentProtocolIsAlwaysSupported() : void{
		self::assertTrue(
			ProtocolCompatibilityLayer::isProtocolSupported(ProtocolInfo::CURRENT_PROTOCOL),
			'The current protocol must always be accepted'
		);
	}

	public function testZeroIsNotSupported() : void{
		// Protocol 0 is never a valid Bedrock version.
		self::assertFalse(ProtocolCompatibilityLayer::isProtocolSupported(0));
	}

	public function testNegativeProtocolIsNotSupported() : void{
		self::assertFalse(ProtocolCompatibilityLayer::isProtocolSupported(-1));
	}

	public function testArbitraryOldProtocolIsNotSupported() : void{
		// Protocol 100 predates any modern Bedrock version.
		if(ProtocolInfo::CURRENT_PROTOCOL === 100){
			$this->markTestSkipped('Protocol 100 is current — not a useful "old" version for this test');
		}
		self::assertFalse(ProtocolCompatibilityLayer::isProtocolSupported(100));
	}

	public function testFutureProtocolIsNotSupported() : void{
		$future = ProtocolInfo::CURRENT_PROTOCOL + 9999;
		self::assertFalse(ProtocolCompatibilityLayer::isProtocolSupported($future));
	}

	/**
	 * Validates that every version listed in SUPPORTED_PROTOCOLS is actually accepted.
	 */
	public function testAllSupportedProtocolsAreAccepted() : void{
		foreach(ProtocolCompatibilityLayer::SUPPORTED_PROTOCOLS as $proto){
			self::assertTrue(
				ProtocolCompatibilityLayer::isProtocolSupported($proto),
				"Protocol $proto is listed in SUPPORTED_PROTOCOLS but isProtocolSupported() returns false"
			);
		}
	}

	// ── describeProtocolMismatch ───────────────────────────────────────────────

	public function testMismatchDescriptionContainsClientVersion() : void{
		$client = 12345;
		$desc = ProtocolCompatibilityLayer::describeProtocolMismatch($client);
		self::assertStringContainsString((string) $client, $desc);
	}

	public function testMismatchDescriptionContainsCurrentVersion() : void{
		$desc = ProtocolCompatibilityLayer::describeProtocolMismatch(0);
		self::assertStringContainsString((string) ProtocolInfo::CURRENT_PROTOCOL, $desc);
	}

	public function testMismatchDescriptionWithNoSupportedProtocolsOmitsAlsoAccepts() : void{
		// SUPPORTED_PROTOCOLS is empty in default config — the "also accepts:" suffix must be absent.
		if(ProtocolCompatibilityLayer::SUPPORTED_PROTOCOLS !== []){
			$this->markTestSkipped('SUPPORTED_PROTOCOLS is non-empty; skipping empty-list branch test');
		}
		$desc = ProtocolCompatibilityLayer::describeProtocolMismatch(0);
		self::assertStringNotContainsString('also accepts', $desc);
	}
}
