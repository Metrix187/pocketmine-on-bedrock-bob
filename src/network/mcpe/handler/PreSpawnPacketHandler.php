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

use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\cache\CraftingDataCache;
use pocketmine\network\mcpe\cache\StaticPacketCache;
use pocketmine\network\mcpe\InventoryManager;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ItemRegistryPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\RequestChunkRadiusPacket;
use pocketmine\network\mcpe\protocol\ServerboundLoadingScreenPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\BoolGameRule;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\network\mcpe\protocol\types\Experiments;
use pocketmine\network\mcpe\protocol\types\LevelSettings;
use pocketmine\network\mcpe\protocol\types\NetworkPermissions;
use pocketmine\network\mcpe\protocol\types\PlayerMovementSettings;
use pocketmine\network\mcpe\protocol\types\ServerAuthMovementMode;
use pocketmine\network\mcpe\protocol\types\ServerTelemetryData;
use pocketmine\network\mcpe\protocol\types\SpawnSettings;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\timings\Timings;
use pocketmine\VersionInfo;
use Ramsey\Uuid\Uuid;
use function sprintf;

/**
 * Handler used for the pre-spawn phase of the session.
 */
#[SilentDiscard(PlayerAuthInputPacket::class, comment: "Spammed after StartGame even though player has no controls")]
#[SilentDiscard(ServerboundLoadingScreenPacket::class, "Not needed")]
class PreSpawnPacketHandler extends PacketHandler{
	public function __construct(
		private Server $server,
		private Player $player,
		private NetworkSession $session,
		private InventoryManager $inventoryManager
	){}

	public function setUp() : void{
		Timings::$playerNetworkSendPreSpawnGameData->startTiming();
		try{
			$location = $this->player->getLocation();
			$world = $location->getWorld();

			$typeConverter = $this->session->getTypeConverter();

			$this->session->getLogger()->debug("Preparing StartGamePacket");
			$levelSettings = new LevelSettings();

			// Seed: PM does not expose a server-side seed to clients. -1 is the conventional
			// "no seed" value; the client only uses this for cosmetic display in the UI.
			$levelSettings->seed = -1;

			// SpawnSettings: BIOME_TYPE_DEFAULT + empty biome name = let the client pick its
			// ambient sounds from the dimension default. Overworld dimension ID matches the
			// single dimension PM currently supports.
			// @protocol-version-sensitive: DimensionIds enum values must match the current
			// bedrock-protocol release when bumping the protocol version.
			$levelSettings->spawnSettings = new SpawnSettings(SpawnSettings::BIOME_TYPE_DEFAULT, "", DimensionIds::OVERWORLD);

			$levelSettings->worldGamemode = $typeConverter->coreGameModeToProtocol($this->server->getGamemode());
			$levelSettings->difficulty = $world->getDifficulty();
			$levelSettings->spawnPosition = BlockPosition::fromVector3($world->getSpawnLocation());

			// Achievements must be disabled for servers: vanilla requires this flag to be true
			// or the client will refuse to connect to online-mode servers.
			$levelSettings->hasAchievementsDisabled = true;

			$levelSettings->time = $world->getTime();

			// eduEditionOffer: 0 = not an Education Edition world. Non-zero values unlock
			// chemistry blocks, NPC entities, etc. PM does not implement those features.
			$levelSettings->eduEditionOffer = 0;

			// Weather: PM does not implement rain/thunder yet. Both values must be sent as
			// float in [0.0, 1.0]; 0 = no weather. When weather is implemented, these should
			// come from World::getRainLevel() / World::getLightningLevel().
			$levelSettings->rainLevel = 0;
			$levelSettings->lightningLevel = 0;

			$levelSettings->commandsEnabled = true;

			// Game rules sent here take effect server-side on the client for the lifetime of
			// the session. Only rules that differ from vanilla defaults need to be listed.
			// @protocol-version-sensitive: rule names are protocol strings; verify against
			// the bedrock-data GameRules list when bumping the protocol version.
			$levelSettings->gameRules = [
				// Disable client-side natural health regeneration: PM handles regen server-side
				// via EffectManager to ensure consistent tick-rate behaviour.
				"naturalregeneration" => new BoolGameRule(false, false),
				// Disable the client-side locator bar (nearby-player tracking) introduced in
				// 1.21.x. The server does not send the required data, so hiding it avoids
				// a blank/broken HUD element.
				"locatorbar" => new BoolGameRule(false, false),
			];

			// Experiments: empty list + hasEverUsedExperiments = false. Enabling experiments
			// unlocks unfinished Mojang features that PM does not implement. Passing an
			// experiment that the client binary does not recognise causes a disconnect.
			$levelSettings->experiments = new Experiments([], false);

			// 26.50+ clients validate jigsaw structure data while processing StartGamePacket and
			// disconnect with "Missing structure data from server" if it hasn't arrived yet.
			if($this->session->getProtocolId() >= ProtocolInfo::PROTOCOL_1_26_50){
				$this->session->getLogger()->debug("Sending jigsaw structure data");
				$this->session->sendDataPacket(StaticPacketCache::getInstance()->getJigsawStructureData());
			}
			$this->session->sendDataPacket(StartGamePacket::create(
				actorUniqueId: $this->player->getId(),
				actorRuntimeId: $this->player->getId(),
				playerGamemode: $typeConverter->coreGameModeToProtocol($this->player->getGamemode()),
				playerPosition: $this->player->getOffsetPosition($location),
				pitch: $location->pitch,
				yaw: $location->yaw,
				// Opaque NBT compound for experimental property system — empty is correct.
				playerActorProperties: new CacheableNbt(CompoundTag::create()),
				levelSettings: $levelSettings,
				// Arbitrary opaque string; PM uses empty (vanilla uses a hash).
				levelId: "",
				worldName: $this->server->getMotd(),
				// Empty = not a Marketplace template world.
				premiumWorldTemplateId: "",
				// false = full server, not a trial/demo session.
				isTrial: false,
				// SERVER_AUTHORITATIVE_V3 = full server-auth movement.
				// @protocol-version-sensitive
				playerMovementSettings: new PlayerMovementSettings(ServerAuthMovementMode::SERVER_AUTHORITATIVE_V3, 0, true),
				// 0 is acceptable at spawn; client resyncs via subsequent packets.
				currentTick: 0,
				// 0 = let client seed itself; PM does not implement server-side enchantment seeds.
				enchantmentSeed: 0,
				// Empty string is valid for non-Xbox Live sessions.
				multiplayerCorrelationId: "",
				// true = stack-based ItemStackRequest inventory protocol (required since 1.16.100).
				enableNewInventorySystem: true,
				serverSoftwareVersion: sprintf("%s %s", VersionInfo::NAME, VersionInfo::VERSION()->getFullVersion(true)),
				// Nil UUID = no checksum enforcement.
				worldTemplateId: Uuid::fromString(Uuid::NIL),
				// false = client-side chunk gen disabled (server sends all chunks).
				enableClientSideChunkGeneration: false,
				// Protocol 2192+ (Bedrock 26.50+) requires hashed block network IDs.
				// Older sessions still use numeric runtime IDs.
				// BlockStateDictionary is loaded with hashed network IDs (FNV-1a), so every block ID we send is a hash.
				blockNetworkIdsAreHashes: true,
				// false = classic death handling.
				enableTickDeathSystems: false,
				networkPermissions: new NetworkPermissions(disableClientSounds: true),
				// false = do not log chat server-side.
				isLoggingChat: false,
				serverJoinInformation: null,
				serverTelemetryData: new ServerTelemetryData("", "", "", ""),
					// Full block palette from canonical_block_states.nbt — required for the client
					// to map runtime IDs to block names for rendering. An empty palette causes the
					// client to self-disconnect with "Missing structure data" immediately after spawn.
					// Custom (add-on) block definitions only; vanilla states must not be listed here.
					blockPalette: [],
					blockPaletteChecksum: 0,
				// Empty — ItemRegistryPacket sent separately carries the full list.
				itemTable: [],
			));

			$this->session->getLogger()->debug("Sending items");
			$this->session->sendDataPacket(ItemRegistryPacket::create($typeConverter->getItemTypeDictionary()->getEntries()));

			$this->session->getLogger()->debug("Sending actor identifiers");
			$this->session->sendDataPacket(StaticPacketCache::getInstance()->getAvailableActorIdentifiers());

			$this->session->getLogger()->debug("Sending biome definitions");
			$this->session->sendDataPacket(StaticPacketCache::getInstance()->getBiomeDefs());

			$this->session->getLogger()->debug("Sending attributes");
			$this->session->getEntityEventBroadcaster()->syncAttributes([$this->session], $this->player, $this->player->getAttributeMap()->getAll());

			$this->session->getLogger()->debug("Sending available commands");
			$this->session->syncAvailableCommands();

			$this->session->getLogger()->debug("Sending abilities");
			$this->session->syncAbilities($this->player);
			$this->session->syncAdventureSettings();

			$this->session->getLogger()->debug("Sending effects");
			foreach($this->player->getEffects()->all() as $effect){
				$this->session->getEntityEventBroadcaster()->onEntityEffectAdded([$this->session], $this->player, $effect, false);
			}

			$this->session->getLogger()->debug("Sending actor metadata");
			$this->player->sendData([$this->player]);

			$this->session->getLogger()->debug("Sending inventory");
			$this->inventoryManager->syncAll();
			$this->inventoryManager->syncSelectedHotbarSlot();

			$this->session->getLogger()->debug("Sending creative inventory data");
			$this->inventoryManager->syncCreative();

			$this->session->getLogger()->debug("Sending crafting data");
			$this->session->sendDataPacket(CraftingDataCache::getInstance()->getCache($this->server->getCraftingManager()));

			$this->session->getLogger()->debug("Sending player list");
			$this->session->syncPlayerList($this->server->getOnlinePlayers());
		}finally{
			Timings::$playerNetworkSendPreSpawnGameData->stopTiming();
		}
	}

	public function handleRequestChunkRadius(RequestChunkRadiusPacket $packet) : bool{
		$this->player->setViewDistance($packet->radius);

		return true;
	}
}
