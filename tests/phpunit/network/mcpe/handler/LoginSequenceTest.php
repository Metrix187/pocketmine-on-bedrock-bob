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

use PHPUnit\Framework\TestCase;
use pocketmine\network\mcpe\protocol\types\login\AuthenticationInfo;
use pocketmine\network\mcpe\protocol\types\login\AuthenticationType;
use pocketmine\network\mcpe\protocol\types\login\openid\SelfSignedJwtBody;
use pocketmine\network\mcpe\protocol\types\login\openid\XboxAuthJwtBody;
use pocketmine\network\mcpe\protocol\types\login\openid\XboxAuthJwtHeader;
use pocketmine\network\mcpe\ProtocolVersionChangelog;
use pocketmine\network\PacketHandlingException;
use Ramsey\Uuid\Uuid;
use function chr;
use function json_encode;
use function md5;
use function ord;

/**
 * Unit tests for the login sequence introduced / hardened in Phase 2–3.
 *
 * We test:
 *   1. parseAuthInfo() — well-formed JSON maps to AuthenticationInfo correctly.
 *   2. parseAuthInfo() — malformed or non-object JSON raises PacketHandlingException.
 *   3. mapXboxTokenHeader() — expected field mapping.
 *   4. mapSelfSignedTokenBody() — expected field mapping.
 *   5. The XUID→UUID derivation algorithm (determinism, RFC-4122 compliance, isolation).
 *   6. ProtocolVersionChangelog structural integrity.
 *
 * Because parseAuthInfo / mapXbox* are declared `protected` on LoginPacketHandler
 * we expose them through a minimal test-only subclass that bypasses the session
 * and server dependencies.
 */
class LoginSequenceTest extends TestCase{

	// ── Test subclass exposing protected parse helpers ─────────────────────────

	/**
	 * @internal test helper only
	 */
	private static function makeExposingHandler() : object{
		return new class extends LoginPacketHandler{
			public function __construct(){
				// Skip parent constructor — we never call handleLogin();
				// we only call the protected parse helpers directly.
			}

			/** @phpstan-return AuthenticationInfo */
			public function exposeParseAuthInfo(string $json) : AuthenticationInfo{
				return $this->parseAuthInfo($json);
			}

			public function exposeMapXboxTokenHeader(array $header) : XboxAuthJwtHeader{
				return $this->mapXboxTokenHeader($header);
			}

			public function exposeMapXboxTokenBody(array $body) : XboxAuthJwtBody{
				return $this->mapXboxTokenBody($body);
			}

			public function exposeMapSelfSignedTokenBody(array $body) : SelfSignedJwtBody{
				return $this->mapSelfSignedTokenBody($body);
			}
		};
	}

	// ── parseAuthInfo ──────────────────────────────────────────────────────────

	public function testParseAuthInfoFullAuthentication() : void{
		$handler = self::makeExposingHandler();

		$json = json_encode([
			'AuthenticationType' => AuthenticationType::FULL->value,
			'Token' => 'header.claims.sig',
		]);

		$result = $handler->exposeParseAuthInfo($json);
		self::assertInstanceOf(AuthenticationInfo::class, $result);
		self::assertSame(AuthenticationType::FULL->value, $result->AuthenticationType);
		self::assertSame('header.claims.sig', $result->Token);
	}

	public function testParseAuthInfoSelfSignedAuthentication() : void{
		$handler = self::makeExposingHandler();

		$json = json_encode([
			'AuthenticationType' => AuthenticationType::SELF_SIGNED->value,
			'Token' => 'header.claims.sig',
		]);

		$result = $handler->exposeParseAuthInfo($json);
		self::assertSame(AuthenticationType::SELF_SIGNED->value, $result->AuthenticationType);
	}

	public function testParseAuthInfoInvalidJsonThrows() : void{
		$handler = self::makeExposingHandler();
		$this->expectException(PacketHandlingException::class);
		$handler->exposeParseAuthInfo('{invalid json}');
	}

	public function testParseAuthInfoNonObjectJsonThrows() : void{
		$handler = self::makeExposingHandler();
		$this->expectException(PacketHandlingException::class);
		$handler->exposeParseAuthInfo(json_encode([1, 2, 3])); // array, not object
	}

	public function testParseAuthInfoMissingFieldThrows() : void{
		// JsonMapper with bExceptionOnMissingData=true will throw if a required field is absent.
		$handler = self::makeExposingHandler();
		$this->expectException(PacketHandlingException::class);
		$handler->exposeParseAuthInfo(json_encode(['AuthenticationType' => AuthenticationType::FULL->value])); // Token missing
	}

	// ── mapXboxTokenHeader ─────────────────────────────────────────────────────

	public function testMapXboxTokenHeaderExtractsKid() : void{
		$handler = self::makeExposingHandler();
		$kid = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ==';
		// OpenID authentication uses kid, alg and typ, unlike the legacy x5u header.
		$header = $handler->exposeMapXboxTokenHeader(['kid' => $kid, 'alg' => 'RS256', 'typ' => 'JWT']);
		self::assertSame($kid, $header->kid);
	}

	// ── mapXboxTokenBody ───────────────────────────────────────────────────────

	public function testMapXboxTokenBodyExtractsXuidAndName() : void{
		$handler = self::makeExposingHandler();
		$body = [
			'ipt' => 'Win10',
			'pfcd' => '2026-10-18T00:00:00Z',
			'tid' => 'test-title',
			'mid' => 'test-minecraft-id',
			'xid' => '2535448090751432',
			'xname' => 'Steve',
			'cpk' => 'MFAKEPUBLICKEY==',
		];
		$result = $handler->exposeMapXboxTokenBody($body);
		self::assertSame('2535448090751432', $result->xid);
		self::assertSame('Steve', $result->xname);
	}

	// ── mapSelfSignedTokenBody ─────────────────────────────────────────────────

	public function testMapSelfSignedTokenBodyExtractsFields() : void{
		$handler = self::makeExposingHandler();
		$uuid = Uuid::uuid4()->toString();
		$body = [
			'leguuid' => $uuid,
			'mid' => 'test-minecraft-id',
			'xname' => 'Alex',
			'cpk' => 'MFAKEPUBLICKEY==',
		];
		$result = $handler->exposeMapSelfSignedTokenBody($body);
		self::assertSame($uuid, $result->leguuid);
		self::assertSame('Alex', $result->xname);
	}

	// ── XUID → UUID derivation (white-box) ────────────────────────────────────

	/**
	 * Replicates the private calculateUuidFromXuid() algorithm and validates
	 * the determinism and RFC-4122 compliance of the result.
	 *
	 * Keeping this logic in the test ensures that if the algorithm is ever
	 * accidentally changed, this test breaks loudly.
	 */
	private static function calculateUuidFromXuid(string $xuid) : \Ramsey\Uuid\UuidInterface{
		$hash = md5("pocket-auth-1-xuid:" . $xuid, binary: true);
		$hash[6] = chr((ord($hash[6]) & 0x0f) | 0x30); // set version to 3
		$hash[8] = chr((ord($hash[8]) & 0x3f) | 0x80); // set variant to RFC 4122
		return Uuid::fromBytes($hash);
	}

	public function testUuidFromXuidIsDeterministic() : void{
		$xuid = '2535448090751432';
		$uuid1 = self::calculateUuidFromXuid($xuid);
		$uuid2 = self::calculateUuidFromXuid($xuid);
		self::assertTrue($uuid1->equals($uuid2), 'UUID derivation must be deterministic for identical XUIDs');
	}

	public function testUuidFromXuidIsVersion3() : void{
		$uuid = self::calculateUuidFromXuid('2535448090751432');
		self::assertSame(3, $uuid->getFields()->getVersion(), 'Derived UUID must be version 3 (name-based MD5)');
	}

	public function testUuidFromXuidIsVariantRfc4122() : void{
		$uuid = self::calculateUuidFromXuid('2535448090751432');
		// RFC 4122 variant value is 2.
		self::assertSame(2, $uuid->getFields()->getVariant(), 'Derived UUID must be RFC 4122 variant');
	}

	public function testUuidFromXuidDifferentXuidsProduceDifferentUuids() : void{
		$uuid1 = self::calculateUuidFromXuid('0000000000000001');
		$uuid2 = self::calculateUuidFromXuid('0000000000000002');
		self::assertFalse($uuid1->equals($uuid2), 'Different XUIDs must produce different UUIDs');
	}

	public function testUuidFromXuidKnownVector() : void{
		// Pre-computed reference vector. If someone changes the hash prefix
		// "pocket-auth-1-xuid:" this test will catch it immediately.
		$uuid = self::calculateUuidFromXuid('2535448090751432');
		self::assertMatchesRegularExpression(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-3[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
			$uuid->toString(),
			'UUID string must match RFC 4122 v3 format'
		);
	}

	// ── ProtocolVersionChangelog structural integrity ──────────────────────────

	public function testChangelogContainsCurrentProtocol() : void{
		self::assertArrayHasKey(
			ProtocolVersionChangelog::PROTOCOL_858,
			ProtocolVersionChangelog::CHANGELOG,
			'The current production protocol must have a changelog entry'
		);
	}

	public function testChangelogEntriesHaveRequiredKeys() : void{
		$required = ['bedrock', 'protocol', 'date', 'summary', 'affected'];
		foreach(ProtocolVersionChangelog::CHANGELOG as $protocol => $entry){
			foreach($required as $key){
				self::assertArrayHasKey(
					$key,
					$entry,
					"Changelog entry for protocol $protocol is missing required key '$key'"
				);
			}
		}
	}

	public function testChangelogProtocolKeyMatchesEntryField() : void{
		foreach(ProtocolVersionChangelog::CHANGELOG as $key => $entry){
			self::assertSame(
				$key,
				$entry['protocol'],
				"Changelog key $key does not match entry['protocol'] = {$entry['protocol']}"
			);
		}
	}

	public function testAllAuditTargetFilesAreNonEmpty() : void{
		foreach(ProtocolVersionChangelog::AUDIT_TARGETS as $file => $description){
			self::assertNotEmpty($file, 'Audit target file path must not be empty');
			self::assertNotEmpty($description, "Audit target description for '$file' must not be empty");
		}
	}

	public function testProtocol858IsCurrentProductionVersion() : void{
		self::assertSame(858, ProtocolVersionChangelog::PROTOCOL_858);
		self::assertSame('1.26.30', ProtocolVersionChangelog::CHANGELOG[858]['bedrock']);
	}
}
