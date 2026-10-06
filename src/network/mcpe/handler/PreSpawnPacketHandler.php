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

			$this->session->sendDataPacket(StartGamePacket::create(
				// entityUniqueId / entityRuntimeId: both set to the player's server-side entity
				// ID. The runtime ID is used for fast in-packet entity lookups; the unique ID
				// is used for persistent cross-session references.
				$this->player->getId(),
				$this->player->getId(),
				$typeConverter->coreGameModeToProtocol($this->player->getGamemode()),
				$this->player->getOffsetPosition($location),
				$location->pitch,
				$location->yaw,
				// playerPropertyData: opaque NBT compound for experimental property system.
				// Sending an empty compound is correct; non-empty values require the matching
				// experiment flag to be set above.
				new CacheableNbt(CompoundTag::create()),
				$levelSettings,
				// levelId: arbitrary opaque string; PM uses empty string (vanilla uses a hash).
				"",
				$this->server->getMotd(),
				// premiumWorldTemplateId: empty = not a Marketplace template world.
				"",
				// isTrial: false = full server, not a trial/demo mode session.
				false,
				// PlayerMovementSettings:
				//   movementType=0 (SERVER_AUTH_WITH_REWIND is type 2, legacy server-auth is 0)
				//   rewindHistorySize=true (unused in type 0, retained for forward compat)
				// @protocol-version-sensitive: movement type constants may change.
				new PlayerMovementSettings(0, true),
				// currentTick: server world tick — 0 is acceptable at spawn; the client will
				// resync via subsequent packets.
				0,
				// enchantmentSeed: used for enchantment table randomisation. 0 = let client
				// seed itself. PM does not implement server-side enchantment seeds.
				0,
				// multiplayerCorrelationId: empty string is valid for non-Xbox Live sessions.
				"",
				// enableNewInventorySystem: true enables the stack-based ItemStackRequest
				// inventory protocol (required since 1.16.100; must be true).
				true,
				sprintf("%s %s", VersionInfo::NAME, VersionInfo::VERSION()->getFullVersion(true)),
				// serverBlockStateChecksum: nil UUID = no checksum enforcement. When PM
				// implements strict block-state validation this should match the canonical
				// block state hash from bedrock-data.
				Uuid::fromString(Uuid::NIL),
				// worldTemplateOptionsLockedBehaviorPack / worldTemplateOptionsLockedResourcePack:
				// both false = player may use their own resource packs on top of server packs.
				false,
				false,
				new NetworkPermissions(disableClientSounds: true),
				// isEditorMode: true enables the in-game world editor UI — not supported by PM.
				true,
				null,
				new ServerTelemetryData("", "", "", ""),
				[],
				0,
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
