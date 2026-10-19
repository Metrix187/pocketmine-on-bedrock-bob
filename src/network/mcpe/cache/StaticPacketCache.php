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

namespace pocketmine\network\mcpe\cache;

use pocketmine\color\Color;
use pocketmine\data\bedrock\BedrockDataFiles;
use pocketmine\data\SavedDataLoadingException;
use pocketmine\network\mcpe\convert\BlockStateDictionary;
use pocketmine\network\mcpe\convert\BlockStateDictionaryEntry;
use pocketmine\network\mcpe\protocol\AvailableActorIdentifiersPacket;
use pocketmine\network\mcpe\protocol\BiomeDefinitionListPacket;
use pocketmine\network\mcpe\protocol\JigsawStructureDataPacket;
use pocketmine\network\mcpe\protocol\serializer\NetworkNbtSerializer;
use pocketmine\network\mcpe\protocol\types\biome\BiomeDefinitionEntry;
use pocketmine\network\mcpe\protocol\types\BlockPaletteEntry;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\utils\Filesystem;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\Utils;
use pocketmine\world\biome\model\BiomeDefinitionEntryData;
use function base64_decode;
use function count;
use function get_debug_type;
use function is_array;
use function json_decode;

class StaticPacketCache{
	use SingletonTrait;

	/**
	 * @phpstan-return CacheableNbt<\pocketmine\nbt\tag\CompoundTag>
	 */
	private static function loadCompoundFromFile(string $filePath) : CacheableNbt{
		return new CacheableNbt((new NetworkNbtSerializer())->read(Filesystem::fileGetContents($filePath))->mustGetCompoundTag());
	}

	/**
	 * Loads JigsawStructureDataPacket from the versioned jigsaw_structures JSON.
	 * The JSON contains a single "nbtB64" key whose value is a base64-encoded
	 * network-little-endian NBT compound (the same encoding used on the wire).
	 * We decode it and hand the raw bytes straight to CacheableNbt so they are
	 * never re-serialised — the bytes are sent verbatim per-session.
	 *
	 * @phpstan-return CacheableNbt<\pocketmine\nbt\tag\CompoundTag>
	 */
	private static function loadJigsawNbt() : CacheableNbt{
		$path = \pocketmine\BEDROCK_DATA_PATH . '/jigsaw_structures-1.26.50.json';
		$json = json_decode(Filesystem::fileGetContents($path), associative: true);
		if(!is_array($json) || !isset($json['nbtB64']) || !\is_string($json['nbtB64'])){
			throw new SavedDataLoadingException("$path must have a string 'nbtB64' key");
		}
		$raw = base64_decode($json['nbtB64'], strict: true);
		if($raw === false){
			throw new SavedDataLoadingException("$path: 'nbtB64' is not valid base64");
		}
		$compound = (new NetworkNbtSerializer())->read($raw)->mustGetCompoundTag();
		return new CacheableNbt($compound);
	}

	/**
	 * @return list<BiomeDefinitionEntry>
	 */
	private static function loadBiomeDefinitionModel(string $filePath) : array{
		$biomeEntries = json_decode(Filesystem::fileGetContents($filePath), associative: true);
		if(!is_array($biomeEntries)){
			throw new SavedDataLoadingException("$filePath root should be an array, got " . get_debug_type($biomeEntries));
		}

		$jsonMapper = new \JsonMapper();
		$jsonMapper->bExceptionOnMissingData = true;
		$jsonMapper->bStrictObjectTypeChecking = true;
		$jsonMapper->bEnforceMapType = false;

		$entries = [];
		foreach(Utils::promoteKeys($biomeEntries) as $biomeName => $entry){
			if(!is_array($entry)){
				throw new SavedDataLoadingException("$filePath should be an array of objects, got " . get_debug_type($entry));
			}

			try{
				$biomeDefinition = $jsonMapper->map($entry, new BiomeDefinitionEntryData());

				$mapWaterColour = $biomeDefinition->mapWaterColour;
				$entries[] = new BiomeDefinitionEntry(
					biomeName: (string) $biomeName,
					id: $biomeDefinition->id,
					temperature: $biomeDefinition->temperature,
					downfall: $biomeDefinition->downfall,
					redSporeDensity: 0.0,
					blueSporeDensity: 0.0,
					ashDensity: 0.0,
					whiteAshDensity: 0.0,
					foliageSnow: $biomeDefinition->foliageSnow,
					depth: $biomeDefinition->depth,
					scale: $biomeDefinition->scale,
					mapWaterColor: new Color(
						$mapWaterColour->r,
						$mapWaterColour->g,
						$mapWaterColour->b,
						$mapWaterColour->a
					),
					rain: $biomeDefinition->rain,
					tags: count($biomeDefinition->tags) > 0 ? $biomeDefinition->tags : null,
				);
			}catch(\JsonMapper_Exception $e){
				throw new \RuntimeException($e->getMessage(), 0, $e);
			}
		}

		return $entries;
	}

	/**
	 * Builds a BlockPaletteEntry list from the canonical block states NBT.
	 *
	 * Each entry in canonical_block_states.nbt is an ordered palette slot: the index is the
	 * numeric runtime ID (sent when blockNetworkIdsAreHashes is false) and the name+states
	 * identify the block to the client when hashes are used. Sending the full palette in
	 * StartGamePacket is required for ALL protocol versions — the client uses it to map
	 * runtime IDs to block names for rendering.
	 *
	 * @return BlockPaletteEntry[]
	 * @phpstan-return list<BlockPaletteEntry>
	 */
	private static function loadBlockPalette() : array{
		$raw = Filesystem::fileGetContents(BedrockDataFiles::CANONICAL_BLOCK_STATES_NBT);
		$metaRaw = Filesystem::fileGetContents(BedrockDataFiles::BLOCK_STATE_META_MAP_JSON);
		$dict = BlockStateDictionary::loadFromString($raw, $metaRaw);

		$palette = [];
		foreach($dict->getStates() as $entry){
			$stateProperties = BlockStateDictionaryEntry::decodeStateProperties($entry->getRawStateProperties());
			$stateTag = new CompoundTag();
			foreach($stateProperties as $k => $v){
				$stateTag->setTag($k, $v);
			}
			$palette[] = new BlockPaletteEntry($entry->getStateName(), new CacheableNbt($stateTag));
		}
		return $palette;
	}

	private static function make() : self{
		return new self(
			BiomeDefinitionListPacket::fromDefinitions(self::loadBiomeDefinitionModel(BedrockDataFiles::BIOME_DEFINITIONS_JSON)),
			AvailableActorIdentifiersPacket::create(self::loadCompoundFromFile(BedrockDataFiles::ENTITY_IDENTIFIERS_NBT)),
			JigsawStructureDataPacket::create(self::loadJigsawNbt()),
			self::loadBlockPalette()
		);
	}

	/**
	 * @param BlockPaletteEntry[] $blockPalette
	 * @phpstan-param list<BlockPaletteEntry> $blockPalette
	 */
	public function __construct(
		private BiomeDefinitionListPacket $biomeDefs,
		private AvailableActorIdentifiersPacket $availableActorIdentifiers,
		private JigsawStructureDataPacket $jigsawStructureData,
		private array $blockPalette
	){}

	public function getBiomeDefs() : BiomeDefinitionListPacket{
		return $this->biomeDefs;
	}

	public function getAvailableActorIdentifiers() : AvailableActorIdentifiersPacket{
		return $this->availableActorIdentifiers;
	}

	public function getJigsawStructureData() : JigsawStructureDataPacket{
		return $this->jigsawStructureData;
	}

	/**
	 * @return BlockPaletteEntry[]
	 * @phpstan-return list<BlockPaletteEntry>
	 */
	public function getBlockPalette() : array{
		return $this->blockPalette;
	}
}
