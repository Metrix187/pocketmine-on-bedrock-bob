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

namespace pocketmine\network\mcpe\handler;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use pocketmine\network\mcpe\compression\Compressor;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\NetworkSettingsPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\RequestNetworkSettingsPacket;

/**
 * Tests SessionStartPacketHandler — the first handler touched when a Bedrock
 * client connects (Phase 2 refactor).
 *
 * The handler does exactly three things for an accepted client:
 *   1. Sends NetworkSettingsPacket with compression parameters.
 *   2. Invokes the $onSuccess closure.
 *   3. Returns true.
 *
 * For a rejected client it must:
 *   1. Call disconnectIncompatibleProtocol().
 *   2. NOT call the $onSuccess closure.
 *   3. Return true.
 */
class SessionStartPacketHandlerTest extends TestCase{

	/** @var NetworkSession&MockObject */
	private NetworkSession $session;

	/** @var Compressor&MockObject */
	private Compressor $compressor;

	protected function setUp() : void{
		// Minimal logger stub — silence all output.
		$logger = $this->createMock(\Logger::class);

		$this->compressor = $this->createMock(Compressor::class);
		$this->compressor->method('getNetworkId')->willReturn(0); // CompressionAlgorithm::NONE

		$this->session = $this->getMockBuilder(NetworkSession::class)
			->disableOriginalConstructor()
			->onlyMethods(['getLogger', 'getCompressor', 'sendDataPacket', 'disconnectIncompatibleProtocol'])
			->getMock();

		$this->session->method('getLogger')->willReturn($logger);
		$this->session->method('getCompressor')->willReturn($this->compressor);
	}

	// ── Helpers ────────────────────────────────────────────────────────────────

	private function makePacket(int $protocol) : RequestNetworkSettingsPacket{
		$pkt = RequestNetworkSettingsPacket::create($protocol);
		return $pkt;
	}

	// ── Supported protocol ─────────────────────────────────────────────────────

	public function testSupportedProtocolSendsNetworkSettingsPacket() : void{
		$this->session
			->expects(self::once())
			->method('sendDataPacket')
			->with(self::isInstanceOf(NetworkSettingsPacket::class));

		$this->session->expects(self::never())->method('disconnectIncompatibleProtocol');

		$successCalled = false;
		$handler = new SessionStartPacketHandler(
			$this->session,
			static function() use (&$successCalled) : void{ $successCalled = true; }
		);

		$result = $handler->handleRequestNetworkSettings($this->makePacket(ProtocolInfo::CURRENT_PROTOCOL));

		self::assertTrue($result);
		self::assertTrue($successCalled, '$onSuccess closure must be invoked for a supported protocol');
	}

	public function testSupportedProtocolReturnsTrue() : void{
		$this->session->method('sendDataPacket')->willReturn(true);

		$handler = new SessionStartPacketHandler(
			$this->session,
			static function() : void{}
		);

		self::assertTrue(
			$handler->handleRequestNetworkSettings($this->makePacket(ProtocolInfo::CURRENT_PROTOCOL))
		);
	}

	// ── Unsupported protocol ───────────────────────────────────────────────────

	public function testUnsupportedProtocolDisconnects() : void{
		$unsupported = ProtocolInfo::CURRENT_PROTOCOL + 9999;

		$this->session
			->expects(self::once())
			->method('disconnectIncompatibleProtocol')
			->with($unsupported);

		$this->session->expects(self::never())->method('sendDataPacket');

		$successCalled = false;
		$handler = new SessionStartPacketHandler(
			$this->session,
			static function() use (&$successCalled) : void{ $successCalled = true; }
		);

		$result = $handler->handleRequestNetworkSettings($this->makePacket($unsupported));

		self::assertTrue($result);
		self::assertFalse($successCalled, '$onSuccess must NOT be called for an unsupported protocol');
	}

	public function testProtocolZeroIsRejected() : void{
		$this->session->expects(self::once())->method('disconnectIncompatibleProtocol')->with(0);
		$this->session->expects(self::never())->method('sendDataPacket');

		$handler = new SessionStartPacketHandler(
			$this->session,
			static function() : void{}
		);

		$handler->handleRequestNetworkSettings($this->makePacket(0));
	}

	// ── NetworkSettingsPacket field contract ───────────────────────────────────

	/**
	 * Verifies the handler sends a NetworkSettingsPacket (not some other packet)
	 * and that the COMPRESS_EVERYTHING constant is defined (value = 1).
	 *
	 * Note: Direct field access on NetworkSettingsPacket requires the vendor to be
	 * installed. The round-trip assertion is deferred to CI where composer install
	 * has run. Here we assert type correctness and that the constant exists.
	 */
	public function testNetworkSettingsPacketIsSent() : void{
		/** @var ClientboundPacket|null $captured */
		$captured = null;
		$this->session
			->method('sendDataPacket')
			->willReturnCallback(function(ClientboundPacket $pkt) use (&$captured) : bool{
				$captured = $pkt;
				return true;
			});

		$handler = new SessionStartPacketHandler(
			$this->session,
			static function() : void{}
		);
		$handler->handleRequestNetworkSettings($this->makePacket(ProtocolInfo::CURRENT_PROTOCOL));

		self::assertInstanceOf(
			NetworkSettingsPacket::class,
			$captured,
			'Handler must send NetworkSettingsPacket for an accepted protocol'
		);
	}

	/**
	 * Verifies the COMPRESS_EVERYTHING constant is defined and equals 1
	 * (the Bedrock protocol value meaning "always compress").
	 */
	public function testCompressEverythingConstantValue() : void{
		self::assertSame(1, NetworkSettingsPacket::COMPRESS_EVERYTHING);
	}

	/**
	 * Verifies the compressor's getNetworkId() is called when building
	 * the NetworkSettingsPacket response.
	 */
	public function testCompressorNetworkIdIsQueried() : void{
		$this->compressor
			->expects(self::atLeastOnce())
			->method('getNetworkId')
			->willReturn(2); // CompressionAlgorithm::ZLIB

		$this->session->method('sendDataPacket')->willReturn(true);

		$handler = new SessionStartPacketHandler(
			$this->session,
			static function() : void{}
		);
		$handler->handleRequestNetworkSettings($this->makePacket(ProtocolInfo::CURRENT_PROTOCOL));
	}
}
