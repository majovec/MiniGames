<?php

use pocketmine\event\{
	Listener,
	block\SignChangeEvent,
	block\BlockBreakEvent,
	block\BlockPlaceEvent,
	player\PlayerInteractEvent,
	player\PlayerQuitEvent,
	player\PlayerJoinEvent,
	player\PlayerMoveEvent,
	player\PlayerDeathEvent,
	player\PlayerRespawnEvent,
	player\PlayerCommandPreprocessEvent,
	entity\EntityDamageByEntityEvent,
	entity\EntityDamageEvent,
	inventory\InventoryTransactionEvent,
	inventory\InventoryCloseEvent,
	server\DataPacketReceiveEvent
};
use pocketmine\utils\ {
	TextFormat as T, Color
};
use pocketmine\nbt\tag\{
	CompoundTag, DoubleTag, FloatTag, ListTag
	};
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\item\{
	Item, enchantment\Enchantment
};
use pocketmine\item\ {
	LeatherBoots, LeatherCap, LeatherPants, LeatherTunic
};
use pocketmine\block\Block;
use pocketmine\entity\{
	Villager, Effect
};
use pocketmine\math\Vector3;
use pocketmine\tile\Chest;
use pocketmine\level\particle\{ExplodeParticle, DustParticle};
use pocketmine\level\sound\{FizzSound,DoorBumpSound};
class EventListener implements Listener {

	/** @var qBW */
	private $p;
	
	function __construct(qBW $plugin) {
		$this->p = $plugin;
	}
	
	function onCommand(PlayerCommandPreprocessEvent $event) {
		$player = $event->getPlayer();
		if($this->p->isInGame($player) && $event->getMessage(){0} == "/") {
			$event->setCancelled();
			$player->sendMessage(T::RED . "Команды запрещены во время игры!");
		}
	}
	
	function onRespawn(PlayerRespawnEvent $event) {
		$player = $event->getPlayer();
		if(!$this->p->isInGame($player))
			return;
		$room = $this->p->getPlayerRoom($player);
		foreach($this->room->players as $player1) {
			foreach($this->room->players as $player2) {
				if($player2 != $player1 && $this->getTeam($player1) == $this->getTeam($player2)) {
					$pk = new SetEntityDataPacket();
					$pk->eid = $player2->getId();
					$pk->metadata = [0 => [0, 0], 1 => [1, 300], 2 => [4, (T::GREEN . "[" . $this->getTeam($player1) . "]" . $player2->getName())], 3 => [0, 1], 4 => [0, 0], 15 => [0, 0]];
					$player1->dataPacket($pk);
				} else {
					$pk = new SetEntityDataPacket();
					$pk->eid = $player2->getId();
					$pk->metadata = [0 => [0, 0], 1 => [1, 300], 2 => [4, (T::RED . "[" . $this->getTeam($player2) . "]" . $player2->getName())], 3 => [0, 1], 4 => [0, 0], 15 => [0, 0]];
					$player1->dataPacket($pk);
				}
			}
		}
	}
	
	function onJoin(PlayerJoinEvent $event) {
		$event->getPlayer()->teleport($this->p->getServer()->getDefaultLevel()->getSafeSpawn());
		$event->getPlayer()->getInventory()->clearAll();
	}
	
	function onDeath(PlayerDeathEvent $event) {
		if(!$this->p->isInGame($event->getPlayer()))
			return;
		$event->setDrops([]);
		$player = $event->getPlayer();
		$room = $this->p->getPlayerRoom($player);
		$room->kill($player);
		foreach($this->p->getJoinSigns() as $arena => $sign) {
				if($arena == $room->name) {
					$block = $this->p->getServer()->getDefaultLevel()->getTile(new Vector3($sign["x"], $sign["y"], $sign["z"]));
					$block->setText($block->getText()[0], $block->getText()[1], $block->getText()[2], (!$room->preStart ? T::AQUA : T::RED) . count($room->players) . " игроков из " . $room->neededPlayers);
				}
		}
	}
	
	function onMove(PlayerMoveEvent $event) {
		$player = $event->getPlayer();
		$level = $player->getLevel();
		$block = $level->getBlock($player->floor());
		if($block->getId() == Item::CARPET) {
			$effect = new Effect(1, "%potion.moveSpeed", 124, 175, 198);
			$effect->setDuration(25)->setAmplifier(3);
			$player->addEffect($effect);
		}
	}
	
	function onClose(InventoryCloseEvent $event) {
		$player = $event->getPlayer();
		$room = $this->p->getPlayerRoom($player);
		if(!$room)
			return;
		$room->closeShop($player);
	}
	
	function onQuit(PlayerQuitEvent $event) {
		$player = $event->getPlayer();
		if($this->p->isInGame($player)) {
			$room = $this->p->getPlayerRoom($player);
			$room->removePlayer($player);
			foreach($this->p->getJoinSigns() as $arena => $sign) {
				if($arena == $room->name) {
					$block = $this->p->getServer()->getDefaultLevel()->getTile(new Vector3($sign["x"], $sign["y"], $sign["z"]));
					$block->setText($block->getText()[0], $block->getText()[1], $block->getText()[2], (!$room->preStart ? T::AQUA : T::RED) . count($room->players) . " игроков из " . $room->neededPlayers);
				}
			}
		}
	}
	
	function onReceive(DataPacketReceiveEvent $event) {
		if($event->getPacket() instanceof pocketmine\network\protocol\ContainerSetSlotPacket) {
			$player = $event->getPlayer();
			if(!$this->p->isInGame($player))
				return;
			if($event->getPacket()->windowid != 0)
				return;
			$name = explode(" (", substr($event->getPacket()->item, 5))[0];
			if($name == "White Carpet")
				$name = "Carpet";
			$room = $this->p->getPlayerRoom($player);
			if($room->isInShopMain($player)) {
				$pk = new pocketmine\network\protocol\ContainerSetSlotPacket;
				$pk->windowid = 0;
				$pk->slot = $event->getPacket()->slot;
				$pk->item = Item::get(0);
				//$player->dataPacket($pk);
				$item = Item::fromString($name);
				$room->showShopCategory($player, $item->getId());
			} elseif($room->isInShop($player)) {
				$pk = new pocketmine\network\protocol\ContainerSetSlotPacket;
				$pk->windowid = 0;
				$pk->slot = $event->getPacket()->slot;
				$pk->item = Item::get(0, 0, 0);
				//$player->dataPacket($pk);
				$item = Item::fromString($name);
				if($item->getId() == Item::ARROW) {
					$room->showShopCategory($player, $room->getShopCategory($player), $room->getPage($player) + 1);
				} elseif($item->getId() == Item::BOW) {
					$room->showShopCategory($player, $room->getShopCategory($player), $room->getPage($player) - 1);
				} elseif($item->getId() == Item::EMERALD_BLOCK) {
					$room->buy($player);
					$room->showShopCategory($player, $room->getShopCategory($player), $room->getPage($player));
				}
			}
		}
	}
	
/*	function onTransaction(InventoryTransactionEvent $event) {
		echo "T";
		$transactions = $event->getTransaction()->getTransactions();
		$inventories = $event->getTransaction()->getInventories();
		$player = null;
		foreach ($transactions as $transaction) {
			foreach ($inventories as $inventory) {
				$p = $inventory->getHolder();
				if($p instanceof Player) {
					$player = $p;
					$trans = $transaction;
				}
			}
		}
		if(!$this->p->isInGame($player))
			return;
		echo "Buy\n";
		
	}*/
	
	function onAtack(EntityDamageEvent $event) {
		if(!$event instanceof EntityDamageByEntityEvent)
			return;
		$player = $event->getDamager();
		if(!$this->p->isInGame($player)) {
			$event->setCancelled();
			return;
		}
		if($player instanceof Player && $this->p->isInGame($player)) {
			$room = $this->p->getPlayerRoom($player);
			if(!$room->start) {
				$event->setCancelled();
				return;
			}
			$entity = $event->getEntity();
			if($entity instanceof Player && $room->getTeam($player) === $room->getTeam($entity)) {
				$event->setCancelled();
				return;
			}
			if($entity instanceof Villager) {
				foreach($room->activeShops as $shop) {
					$event->setCancelled();
					$shop->show($player);
					break;
				}
			}
		}
	}
	
	function onBreak(BlockBreakEvent $event) {
		$player = $event->getPlayer();
		$block = $event->getBlock();
		if($this->p->isInGame($player) && $block->getId() != Item::SANDSTONE && $block->getId() != Item::END_STONE && $block->getId() != Item::SLIME_BLOCK && $block->getId() != Item::CARPET && $block->getId() != Item::SANDSTONE_STAIRS && $block->getId() != Item::LADDER && $block->getId() != Item::CHEST)
			$event->setCancelled();
		if($this->p->isInGame($player) && $block->getId() == Item::BED_BLOCK) {
			$this->p->getPlayerRoom($player)->breakBed($player, $block);
			$event->setCancelled();
			}
	}
	
	function onPlace(BlockPlaceEvent $event) {
		$player = $event->getPlayer();
		$block = $event->getBlock();
		if($this->p->isInGame($player) && $block->getId() != Item::SANDSTONE && $block->getId() != Item::END_STONE && $block->getId() != Item::SLIME_BLOCK && $block->getId() != Item::CARPET && $block->getId() != Item::SANDSTONE_STAIRS && $block->getId() != Item::LADDER && $block->getId() != Item::CHEST)
			$event->setCancelled();
	}
	
	function onTap(PlayerInteractEvent $event) {
		$player = $event->getPlayer();
		$block = $event->getBlock();
		$room = $this->p->getPlayerRoom($player);
		foreach($this->p->getLeaveSigns() as $array) {
			if($block->x == $array["x"] && $block->y == $array["y"] && $block->z == $array["z"]) {
				if(!$this->p->isInGame($player))
					return;
				$room = $this->p->getPlayerRoom($player);
				if($room->start)
					return;
				$room->removePlayer($player);
				foreach($this->p->getJoinSigns() as $arena => $sign) {
					if($arena == $room->name) {
						$block = $this->p->getServer()->getDefaultLevel()->getTile(new Vector3($sign["x"], $sign["y"], $sign["z"]));
						$block->setText($block->getText()[0], $block->getText()[1], $block->getText()[2], (!$room->preStart ? T::AQUA : T::RED) . count($room->players) . " игроков из " . $room->neededPlayers);
					}
				}
				return;
			}
		}
		if(!$room) {
			foreach($this->p->getJoinSigns() as $arena => $array) {
				if($block->x == $array["x"] && $block->y == $array["y"] && $block->z == $array["z"]) {
					$room = $this->p->getRoomByName($arena);
					if(!$room) {
						$player->sendMessage(T::RED . "Произошла ошибка, пожалуйста обратитесь к разработчику.");
						return;
					}
					if($room->start) {
						$player->sendMessage("§cИгра уже началась!");
						return;
					}
					if(count($room->players) == $room->neededPlayers) {
						$player->sendMessage("§cАрена полная!");
						return;
					}
					
					$player->sendMessage("§aТелепортация на арену§6 " . $arena . "...");
					$player->teleport(new Position($room->hub["x"], $room->hub["y"], $room->hub["z"], $this->p->getServer()->getLevelByName($room->name)));
					$room->addPlayer($player);
					$tile = $this->p->getServer()->getDefaultLevel()->getTile(new Vector3($block->x, $block->y, $block->z));
					$tile->setText($tile->getText()[0], $tile->getText()[1], $tile->getText()[2], (!$room->preStart ? T::AQUA : T::RED) . count($room->players) . " игроков из " . $room->neededPlayers);
					return;
				}
			}
		} elseif($block->getId() == Item::TNT) {
			$item = $event->getItem();
			if($item->getId() == Item::CLOCK) {
				$room->more($player);
				$i = $player->getInventory()->getItemInHand();
				$i->setCount($i->getCount() - 1);
				$player->getInventory()->setItemInHand($i->getCount() > 0 ? $i : Item::get(Item::AIR));
			}
		}
		$item = $event->getItem();
		if($item->getId() == Item::GUNPOWDER) {
				$i = $player->getInventory()->getItemInHand();
				$i->setCount($i->getCount() - 1);
				$player->getInventory()->setItemInHand($i->getCount() > 0 ? $i : Item::get(Item::AIR));
				$room = $this->p->getPlayerRoom($player);
				if(!$room)
					return;
				$room->teleportTask->players[] = array(6 * 20, $player);
			} elseif($item->getId() == Item::BLAZE_ROD) {
				if($player->getLevel()->getBlock($player->floor()->subtract(0, 4, 0))->getId() == Item::AIR) {
					$player->getLevel()->setBlock($player->floor()->subtract(0, 4, 0), new Block(Item::SLIME_BLOCK));
					$i = $player->getInventory()->getItemInHand();
					$i->setCount($i->getCount() - 1);
					$player->getInventory()->setItemInHand($i->getCount() > 0 ? $i : Item::get(Item::AIR));
				}
			} elseif($item->getId() == Item::CLAY) {
				$i = $player->getInventory()->getItemInHand();
				$i->setCount($i->getCount() - 1);
				$player->getInventory()->setItemInHand($i->getCount() > 0 ? $i : Item::get(Item::AIR));
				$nbt = new CompoundTag("", [
				"Pos" => new ListTag("Pos", [
					new DoubleTag("", $player->getX()),
					new DoubleTag("", $player->getY() + $player->getEyeHeight()),
					new DoubleTag("", $player->getZ())
					]),
				"Motion" => new ListTag("Motion", [
					new DoubleTag("", -sin($player->getYaw() / 180 * M_PI) * cos($player->getPitch() / 180 * M_PI)),
					new DoubleTag("", -sin($player->getPitch() / 180 * M_PI)),
					new DoubleTag("", cos($player->getYaw() / 180 * M_PI) * cos($player->getPitch() / 180 * M_PI))
					]),
				"Rotation" => new ListTag("Rotation", [
					new FloatTag("", $player->getYaw()),
					new FloatTag("", $player->getPitch())
					])
			]);
			$f = 1.5;
			$i = new Grenade($this->p, $this->p->getServer(), $player->chunk, $nbt);
			$i->setMotion($i->getMotion()->multiply($f));
			$i->spawnToAll();
			} elseif($item->getId() == Item::GLOWSTONE_DUST) {
				$room = $this->p->getPlayerRoom($player);
				if(!$room)
					return;
				$i = $player->getInventory()->getItemInHand();
				$i->setCount($i->getCount() - 1);
				$level = $this->p->getServer()->getLevelByName($room->name);
				$player->getInventory()->setItemInHand($i->getCount() > 0 ? $i : Item::get(Item::AIR));
				$effect = new Effect(14, "%potion.invisibility", 127, 131, 146);
				$effect->setDuration(5 * 20)->setAmplifier(1)->setVisible(false);
				$player->addEffect($effect);
				$effect = new Effect(1, "%potion.moveSpeed", 124, 175, 198);
				$effect->setDuration(5 * 20)->setAmplifier(5)->setVisible(false);
				$player->addEffect($effect);
				$center = new Vector3($player->getX(), $player->getY(), $player->getZ());
				$radius = 2.52834;
				$count = 1000;
				$particle = new ExplodeParticle($center);
				for($i = 0; $i < $count; $i++){
					$pitch = (mt_rand() / mt_getrandmax() - 0.5) * M_PI;
					$yaw = mt_rand() / mt_getrandmax() * 2 * M_PI;
					$y = -sin($pitch);
					$delta = cos($pitch);
					$x = -sin($yaw) * $delta;
					$z = cos($yaw) * $delta;
					$v = new Vector3($x, $y, $z);
					$p = $center->add($v->normalize()->multiply($radius));
					$particle->setComponents($p->x, $p->y, $p->z);
					$level->addParticle($particle);
				}
				$radius = 2.2;
				$count = 1000;
				$particle = new DustParticle($center, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
				for($i = 0; $i < $count; $i++){
					$pitch = (mt_rand() / mt_getrandmax() - 0.5) * M_PI;
					$yaw = mt_rand() / mt_getrandmax() * 2 * M_PI;
					$y = -sin($pitch);
					$delta = cos($pitch);
					$x = -sin($yaw) * $delta;
					$z = cos($yaw) * $delta;
					$v = new Vector3($x, $y, $z);
					$p = $center->add($v->normalize()->multiply($radius));
					$particle->setComponents($p->x, $p->y, $p->z);
					$level->addParticle($particle);
				}
				$this->p->getServer()->getLevelByName($room->name)->addSound(new DoorBumpSound($player->floor()));
			} elseif($item->getId() == Item::WORKBENCH) {
				$i = $player->getInventory()->getItemInHand();
				$i->setCount($i->getCount() - 1);
				$player->getInventory()->setItemInHand($i->getCount() > 0 ? $i : Item::get(Item::AIR));
				$item = Item::get(Item::SANDSTONE, 0, mt_rand(4, 32));
				$player->getInventory()->addItem($item);
				if(mt_rand(0, 1) == 0) {
					$item = Item::get(Item::WOODEN_PICKAXE, 0, 1);
					$player->getInventory()->addItem($item);
					}
				if(mt_rand(0, 1) == 0) {
					$item = Item::get(Item::STICK, 0, 1);
					$item->addEnchantment(Enchantment::getEnchantment(12));
					$player->getInventory()->addItem($item);
					}
				$item = Item::get(Item::COOKED_PORKCHOP, 0, 4);
				$player->getInventory()->addItem($item);
				$item = Item::get(Item::LEATHER_CAP, 0, 1);
				$player->getInventory()->addItem($item);
				$item = Item::get(Item::LEATHER_BOOTS, 0, 1);
				$player->getInventory()->addItem($item);
				$item = Item::get(Item::LEATHER_PANTS, 0, 1);
				$player->getInventory()->addItem($item);
			} elseif($item->getId() == Item::FURNACE) {
				$i = $player->getInventory()->getItemInHand();
				$i->setCount($i->getCount() - 1);
				$player->getInventory()->setItemInHand($i->getCount() > 0 ? $i : Item::get(Item::AIR));
				$item = Item::get(Item::SANDSTONE, 0, mt_rand(48, 64));
				$player->getInventory()->addItem($item);
				if($player->hasPermission("qbw.donate.vip"))
					$level = 1;
				elseif($player->hasPermission("qbw.donate.mvp"))
					$level = 2;
				else
					$level = 3;
				$rand = mt_rand(1, $level);
				if($rand == 1)
					$item = Item::get(Item::STONE_PICKAXE, 0, 1);
				elseif($rand == 2)
					$item = Item::get(Item::IRON_PICKAXE, 0, 1);
				else
					$item = Item::get(Item::DIAMOND_PICKAXE, 0, 1);
				$player->getInventory()->addItem($item);
				$rand = mt_rand(1, $level);
				if($rand == 1)
					$item = Item::get(Item::STONE_SWORD, 0, 1);
				elseif($rand == 2)
					$item = Item::get(Item::IRON_SWORD, 0, 1);
				else
					$item = Item::get(Item::DIAMOND_SWORD, 0, 1);
				$player->getInventory()->addItem($item);
				$item = Item::get(Item::COOKED_PORKCHOP, 0, 12);
				$player->getInventory()->addItem($item);
				$color = new Color(mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
				$item = new LeatherBoots();
				$item->setCustomColor($color);
				$player->getInventory()->addItem($item);
				$item = new LeatherPants();
				$item->setCustomColor($color);
				$player->getInventory()->addItem($item);
				$item = new LeatherCap();
				$item->setCustomColor($color);
				$player->getInventory()->addItem($item);
			}
	}
	
	function onSignChange(SignChangeEvent $event) {
		$player = $event->getPlayer();
		$lines = $event->getLines();
		if(!$player->hasPermission("qbw.sign.create"))
			return;
		if(strtolower($lines[0]) == "[qbw]") {
			foreach($this->p->rooms as $number => $room) {
				if(strtolower($room->name) == strtolower($lines[1])) {
					$this->p->addJoinSign($room->name, $event->getBlock());
					$player->sendMessage("Табличка для входа на арену " . T::RED . $room->name . T::WHITE . " успешно создана.");
					$event->setLine(2, $room->teamsCount . " команды по " . ($room->neededPlayers / $room->teamsCount) . " игроков");
					$event->setLine(3, T::AQUA . "0 игроков из " . $room->neededPlayers);
					$event->setLine(0, T::BLACK . "[".T::GOLD."q".T::GREEN."BW".T::BLACK."]");
					return;
				}
			}
			$event->setLine(1, T::RED . "Нет такой арены!");
		} elseif(strtolower($lines[0]) == "[leave]") {
			$this->p->addLeaveSign($event->getBlock());
			$player->sendMessage("Табличка для выхода с арены успешно создана.");
			$event->setLine(0, T::BLACK . "[".T::GOLD."q".T::GREEN."BW".T::BLACK."]");
		}
	}
}