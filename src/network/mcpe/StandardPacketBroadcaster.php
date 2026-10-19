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

use pmmp\encoding\ByteBufferWriter;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\serializer\PacketBatch;
use pocketmine\Server;
use pocketmine\timings\Timings;
use function count;
use function explode;
use function log;
use function spl_object_id;
use function strlen;

final class StandardPacketBroadcaster implements PacketBroadcaster{
	public function __construct(
		private Server $server
	){}

	public function broadcastPackets(array $recipients, array $packets) : void{
		//TODO: this shouldn't really be called here, since the broadcaster might be replaced by an alternative
		//implementation that doesn't fire events
		if(DataPacketSendEvent::hasHandlers()){
			$ev = new DataPacketSendEvent($recipients, $packets);
			$ev->call();
			if($ev->isCancelled()){
				return;
			}
			$packets = $ev->getPackets();
		}

		$compressors = [];

		// Group recipients by (compressorId, protocolId) so each group gets identically-encoded packets.
		// Key format: "{compressorId}:{protocolId}"
		$targetsByGroup = [];
		foreach($recipients as $recipient){
			$compressor = $recipient->getCompressor();
			$compressorId = spl_object_id($compressor);
			$compressors[$compressorId] = $compressor;
			$protocolId = $recipient->getProtocolId();
			$targetsByGroup[$compressorId . ':' . $protocolId][] = $recipient;
		}

		$writer = new ByteBufferWriter();

		foreach($targetsByGroup as $groupKey => $groupTargets){
			// Derive compressorId and protocolId from the group key.
			[$compressorId, $protocolId] = explode(':', $groupKey, 2);
			$compressorId = (int) $compressorId;
			$protocolId = (int) $protocolId;
			$compressor = $compressors[$compressorId];

			$totalLength = 0;
			$packetBuffers = [];
			foreach($packets as $packet){
				$writer->clear(); //memory reuse let's gooooo
				$buffer = NetworkSession::encodePacketTimed($writer, $packet, $protocolId);
				//varint length prefix + packet buffer
				$totalLength += (((int) log(strlen($buffer), 128)) + 1) + strlen($buffer);
				$packetBuffers[] = $buffer;
			}

			$threshold = $compressor->getCompressionThreshold();
			if(count($groupTargets) > 1 && $threshold !== null && $totalLength >= $threshold){
				//do not prepare shared batch unless we're sure it will be compressed
				$stream = new ByteBufferWriter();
				PacketBatch::encodeRaw($stream, $packetBuffers);
				$batchBuffer = $stream->getData();

				$batch = $this->server->prepareBatch($batchBuffer, $compressor, timings: Timings::$playerNetworkSendCompressBroadcast);
				foreach($groupTargets as $target){
					$target->queueCompressed($batch);
				}
			}else{
				foreach($groupTargets as $target){
					foreach($packetBuffers as $packetBuffer){
						$target->addToSendBuffer($packetBuffer);
					}
				}
			}
		}
	}
}
