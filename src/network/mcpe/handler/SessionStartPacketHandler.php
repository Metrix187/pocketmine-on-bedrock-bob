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

use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\NetworkSettingsPacket;
use pocketmine\network\mcpe\protocol\RequestNetworkSettingsPacket;
use pocketmine\network\mcpe\ProtocolCompatibilityLayer;
use function sprintf;

final class SessionStartPacketHandler extends PacketHandler{

	/**
	 * @phpstan-param \Closure() : void $onSuccess
	 */
	public function __construct(
		private NetworkSession $session,
		private \Closure $onSuccess
	){}

	public function handleRequestNetworkSettings(RequestNetworkSettingsPacket $packet) : bool{
		$protocolVersion = $packet->getProtocolVersion();
		if(!ProtocolCompatibilityLayer::isProtocolSupported($protocolVersion)){
			$this->session->getLogger()->debug(sprintf(
				"[Bob-Network] %s",
				ProtocolCompatibilityLayer::describeProtocolMismatch($protocolVersion)
			));
			$this->session->disconnectIncompatibleProtocol($protocolVersion);

			return true;
		}

		// Store the negotiated protocol ID so encode/decode use the correct version.
		$this->session->setProtocolId($protocolVersion);

		// NetworkSettingsPacket fields (as of bedrock-protocol ~58.x / bedrock-1.26.30):
		// - compressionThreshold: COMPRESS_EVERYTHING = 1 (compress all game packets)
		// - compressionAlgorithm: negotiated from server-side Compressor implementation
		// - clientThrottleEnabled: false (server-side throttle only for now)
		// - clientThrottleThreshold: 0 (unused when throttle disabled)
		// - clientThrottleScalar: 0.0 (unused when throttle disabled)
		// @protocol-version-sensitive — audit when bumping CURRENT_PROTOCOL
		$this->session->sendDataPacket(NetworkSettingsPacket::create(
			NetworkSettingsPacket::COMPRESS_EVERYTHING,
			$this->session->getCompressor()->getNetworkId(),
			false,
			0,
			0
		));
		($this->onSuccess)();

		return true;
	}
}
