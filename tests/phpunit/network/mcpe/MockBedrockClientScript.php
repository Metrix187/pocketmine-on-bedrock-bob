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

/**
 * MockBedrockClientScript — Bedrock handshake simulation harness.
 *
 * PURPOSE
 * -------
 * This script drives a synthetic in-process simulation of the first two
 * packets exchanged between a modern Bedrock client and the modernized server.
 * It is NOT a live network client — it wires the handler and session stubs
 * together in-process and walks the complete handshake state machine.
 *
 * WHAT IS SIMULATED
 * -----------------
 *  Phase A — RequestNetworkSettings (client → server)
 *    The "client" injects a RequestNetworkSettingsPacket carrying the current
 *    protocol version into SessionStartPacketHandler. The handler is expected
 *    to respond with NetworkSettingsPacket and invoke the $onSuccess closure.
 *
 *  Phase B — Mismatch rejection
 *    The same handler is fed a packet with an unsupported protocol number.
 *    The handler must call disconnectIncompatibleProtocol() and must NOT
 *    invoke $onSuccess.
 *
 * HOW TO RUN
 * ----------
 *  php -d auto_prepend_file=vendor/autoload.php tests/phpunit/network/mcpe/MockBedrockClientScript.php
 *
 * Expected output (success):
 *  [Phase A] ✓ NetworkSettingsPacket sent to client
 *  [Phase A] ✓ onSuccess closure invoked
 *  [Phase B] ✓ disconnectIncompatibleProtocol(99999) called
 *  [Phase B] ✓ onSuccess NOT invoked (correct)
 *  Handshake simulation: ALL CHECKS PASSED
 *
 * EXIT CODE
 *  0 = all assertions passed
 *  1 = at least one assertion failed
 */

use pocketmine\network\mcpe\compression\Compressor;
use pocketmine\network\mcpe\handler\SessionStartPacketHandler;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\NetworkSettingsPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\RequestNetworkSettingsPacket;
use pocketmine\network\mcpe\ProtocolCompatibilityLayer;

require_once __DIR__ . '/../../../../vendor/autoload.php';

// ─── Minimal stubs ──────────────────────────────────────────────────────────

/**
 * A minimal Compressor that reports CompressionAlgorithm::ZLIB (2) and does nothing.
 *
 * @internal test harness only
 */
final class MockCompressor implements Compressor{
	public function decompress(string $payload) : string{ return $payload; }
	public function compress(string $payload) : string{ return $payload; }
	public function getNetworkId() : int{ return 2; } // CompressionAlgorithm::ZLIB
	public function getCompressionThreshold() : ?int{ return null; }
}

/**
 * Thin session spy: records all packets sent and disconnect calls.
 *
 * @internal test harness only
 */
final class SpyNetworkSession{
	/** @var ClientboundPacket[] */
	public array $sentPackets = [];
	public ?int $disconnectedProtocol = null;
	public array $logMessages = [];

	public function sendDataPacket(ClientboundPacket $packet, bool $immediate = false) : bool{
		$this->sentPackets[] = $packet;
		return true;
	}

	public function disconnectIncompatibleProtocol(int $protocolVersion) : void{
		$this->disconnectedProtocol = $protocolVersion;
	}

	public function getLogger() : \Logger{
		$logger = new class($this) extends \AttachableLogger{
			public function __construct(private SpyNetworkSession $session){}
			public function debug($message, array $context = []) : void{
				$this->session->logMessages[] = "[debug] $message";
			}
			public function info($message, array $context = []) : void{}
			public function notice($message, array $context = []) : void{}
			public function warning($message, array $context = []) : void{}
			public function error($message, array $context = []) : void{}
			public function critical($message, array $context = []) : void{}
			public function alert($message, array $context = []) : void{}
			public function emergency($message, array $context = []) : void{}
			public function log($level, $message, array $context = []) : void{}
		};
		return $logger;
	}

	public function getCompressor() : Compressor{
		return new MockCompressor();
	}
}

// ─── Assertion helpers ───────────────────────────────────────────────────────

$failures = 0;

function assertCheck(bool $condition, string $label, string $phase) : void{
	global $failures;
	if($condition){
		echo "[$phase] ✓ $label\n";
	}else{
		echo "[$phase] ✗ FAIL: $label\n";
		$failures++;
	}
}

// ─── Phase A: Supported protocol handshake ───────────────────────────────────

$sessionA = new SpyNetworkSession();
// We need to cast to NetworkSession for the handler constructor type-hint, but
// since SessionStartPacketHandler only calls the three methods we've stubbed we
// use a anonymous class that presents the same interface.
$handlerSessionA = new class($sessionA) extends \stdClass{
	public function __construct(private SpyNetworkSession $spy){}
	public function __call(string $name, array $args) : mixed{
		return $this->spy->$name(...$args);
	}
};

$successCalledA = false;
$handlerA = new SessionStartPacketHandler(
	// @phpstan-ignore-next-line (test harness — duck typing)
	$handlerSessionA,
	static function() use (&$successCalledA) : void{ $successCalledA = true; }
);

$pktA = RequestNetworkSettingsPacket::create(ProtocolInfo::CURRENT_PROTOCOL);
$handlerA->handleRequestNetworkSettings($pktA);

$sentNetworkSettings = false;
foreach($sessionA->sentPackets as $pkt){
	if($pkt instanceof NetworkSettingsPacket){
		$sentNetworkSettings = true;
		break;
	}
}

assertCheck($sentNetworkSettings,    'NetworkSettingsPacket sent to client',       'Phase A');
assertCheck($successCalledA,          'onSuccess closure invoked',                  'Phase A');
assertCheck($sessionA->disconnectedProtocol === null, 'No disconnect called',      'Phase A');

// ─── Phase B: Unsupported protocol rejection ──────────────────────────────────

$sessionB = new SpyNetworkSession();
$handlerSessionB = new class($sessionB) extends \stdClass{
	public function __construct(private SpyNetworkSession $spy){}
	public function __call(string $name, array $args) : mixed{
		return $this->spy->$name(...$args);
	}
};

$successCalledB = false;
$handlerB = new SessionStartPacketHandler(
	// @phpstan-ignore-next-line (test harness — duck typing)
	$handlerSessionB,
	static function() use (&$successCalledB) : void{ $successCalledB = true; }
);

$unsupportedProto = ProtocolInfo::CURRENT_PROTOCOL + 99999;
$pktB = RequestNetworkSettingsPacket::create($unsupportedProto);
$handlerB->handleRequestNetworkSettings($pktB);

assertCheck($sessionB->disconnectedProtocol === $unsupportedProto, "disconnectIncompatibleProtocol($unsupportedProto) called", 'Phase B');
assertCheck(!$successCalledB, 'onSuccess NOT invoked (correct)', 'Phase B');
assertCheck(count($sessionB->sentPackets) === 0, 'No packets sent to rejected client', 'Phase B');

// ─── Phase C: ProtocolCompatibilityLayer smoke-checks ─────────────────────────

assertCheck(
	ProtocolCompatibilityLayer::isProtocolSupported(ProtocolInfo::CURRENT_PROTOCOL),
	sprintf('isProtocolSupported(%d) returns true', ProtocolInfo::CURRENT_PROTOCOL),
	'Phase C'
);
assertCheck(
	!ProtocolCompatibilityLayer::isProtocolSupported(0),
	'isProtocolSupported(0) returns false',
	'Phase C'
);
$desc = ProtocolCompatibilityLayer::describeProtocolMismatch(12345);
assertCheck(
	str_contains($desc, '12345'),
	'describeProtocolMismatch includes the client protocol number',
	'Phase C'
);
assertCheck(
	str_contains($desc, (string) ProtocolInfo::CURRENT_PROTOCOL),
	'describeProtocolMismatch includes the server protocol number',
	'Phase C'
);

// ─── Summary ─────────────────────────────────────────────────────────────────

echo "\n";
if($failures === 0){
	echo "Handshake simulation: ALL CHECKS PASSED\n";
	exit(0);
}else{
	echo "Handshake simulation: $failures CHECK(S) FAILED\n";
	exit(1);
}
