<?php

use pocketmine\utils\TextFormat as T;
use pocketmine\math\Vector3;
use pocketmine\utils\Config;
use pocketmine\level\Position;
use pocketmine\block\Block;
use pocketmine\Player;
use pocketmine\level\particle\FloatingTextParticle;
use pocketmine\scheduler\PluginTask;
use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\entity\Creature;
use pocketmine\network\protocol\SetEntityDataPacket;

class ReloadTask extends PluginTask {

private $stop = false;
private $room;
	function __construct(Room $room) {
		parent::__construct($room->plugin, $room);
		$this->room = $room;
	}
	function onRun($currentTick) {
	if($this->stop)
		return;
		foreach($this->room->plugin->getServer()->getLevelByName($this->room->name)->getEntities() as $entity) {
			if(!($entity instanceof Human)) {
				$entity->close();
			}
		}
		$this->stop = true;
		foreach($this->room->plugin->getJoinSigns() as $arena => $sign) {
				if($arena == $this->room->name) {
					$block = $this->room->plugin->getServer()->getDefaultLevel()->getTile(new Vector3($sign["x"], $sign["y"], $sign["z"]));
					$block->setText($block->getText()[0], $block->getText()[1], $block->getText()[2], T::AQUA . "0 игроков из " . $this->room->neededPlayers);
				}
			}
		foreach($this->room->players as $player) {
			$player->sendMessage("§eАрена перезапусуается! §aТелепортация на спавн...");
			$player->teleport($this->room->plugin->getServer()->getDefaultLevel()->getSafeSpawn());
			$player->setSpawn($this->room->plugin->getServer()->getDefaultLevel()->getSafeSpawn());
			$player->getInventory()->clearAll();
			foreach($this->room->players as $player2) {
				$pk = new SetEntityDataPacket();
				$pk->eid = $player2->getId();
				$pk->metadata = [0 => [0, 0], 1 => [1, 300], 2 => [4, $player2->getName()], 3 => [0, 1], 4 => [0, 0], 15 => [0, 0]];
				$player->dataPacket($pk);
			}
		}
		$this->room->detonatorTask->stop = true;
		unset($this->room->detonatorTask);
		$this->room->materialTask->stop = true;
		unset($this->room->materialTask);
		$this->room->popupTask->stop = true;
		unset($this->room->popupTask);
		$this->room->warningTask->stop = true;
		unset($this->room->warningTask);
		foreach($this->room->activeShops as $shop) {
			$shop->__destruct();
		}
		$this->room->plugin->getServer()->unloadLevel($this->room->plugin->getServer()->getLevelByName($this->room->name));
		$this->room->plugin->reloadRoom($this->room);
	}
}

class Room {

	/** @var qBW */
	public $plugin;
	
	/** @var string */
	public $name;
	
	/** @var int */
	public $teamsCount;
	public $neededPlayers;
	public $time = 10;

	/** @var array */
	public $beds;
	public $shops;
	public $players = array();
	public $hub;
	public $eliminated = [];
	public $teams;
	public $spawn;
	public $activeShops = array();
	public $detonators = [];
	public $timers = [];
	public $materials = [];

	public $start = false;
	public $preStart = false;
	public $detonatorTask;
	public $materialTask;
	public $popupTask;
	public $warningTask;
	public $teleportTask;
	
	function tick($task) {
		$this->time--;
		if($this->time == 0) {
			if(count($this->players) == 0) {
				$this->reloadArena();
				return;
			}
			$this->startGame();
			$task->__destruct();
		} elseif($this->time > 0) {
			foreach($this->players as $player) {
				$player->sendPopup(T::GREEN . $this->time . " секунд до начала...");
			}
		}
	}
	
	function __construct(qBW $pl, $n, $t, $p, array $b, array $s, array $h, array $sp, array $m) {
		$this->plugin = $pl;
		$this->name = $n;
		$this->teamsCount = $t;
		$this->neededPlayers = $p;
		$this->beds = $b;
		$this->shops = $s;
		$this->hub = $h;
		$this->spawn = $sp;
		$this->teams = array();
		$this->materials = $m;
		foreach($b as $team => $bed) {
			$this->detonators[$team] = $bed["detonator"];
		}
		for($i = 0; $i < $t; $i++) {
			$this->teams[$i] = array();
		}
		$this->plugin->getServer()->loadLevel($this->name);
		foreach($this->plugin->getServer()->getLevelByName($this->name)->getEntities() as $entity) {
			if(!($entity instanceof Human)) {
				$entity->close();
			}
		}
        $this->plugin->getServer()->getLevelByName($this->name)->setAutoSave(false);
	}
	
	function __destruct() {
		
	}
	
	public function changeTeam(Player $player, int $team) {
		$max = $this->neededPlayers / $this->teamsCount;
		if(count($this->teams[$team]) >= $max)
			return false;
		foreach($this->teams as $number => $t) {
			$i = 0;
			foreach($t as $p) {
				if($player == $p) {
					unset($this->teams[$number][$i]);
				}
				$i++;
			}
		}
		$this->teams[$team][] = $player;
	}
	
	public function more($player) {
		$team = $this->getTeam($player);
		$this->detonatorTask->add($team, 10);
	}
	
	public function getTeam($player) {
		foreach($this->teams as $number => $t) {
			foreach($t as $p) {
				if($p == $player)
					return $number;
			}
		}
		return false;
	}
	
	public function getTime($team) {
		foreach($this->detonatorTask->timers as $t => $timer) {
			if($team == $t)
				return $timer[1];
		}
		return 0;
	}
	
	public function addPlayer(Player $player) {
	$player->setGamemode(0);
		foreach($this->players as $joinedPlayer) {
			$joinedPlayer->sendMessage("§aИгрок " . $player->getName() . " §bзашел! [" . (count($this->players) + 1) . "/" . $this->neededPlayers . "]");
		}
		$this->players[] = $player;
		if(count($this->players) == $this->neededPlayers) {
			$this->preStart = true;
			$this->startGameTimer();
		}
	}
	
	public function removePlayer(Player $player) {
		foreach($this->players as $key => $joinedPlayer) {
			$joinedPlayer->sendMessage("§aИгрок " . $player->getName() . " §bвышел. [" . (count($this->players) - 1) . "/" . $this->neededPlayers . "]");
			if($joinedPlayer == $player)
				unset($this->players[$key]);
		}
		$player->getInventory()->clearAll();
		foreach($this->teams as $team => $players) {
			foreach($players as $num => $pl) {
				if($pl == $player) {
					unset($this->teams[$team][$num]);
				}
			}
		}
		$player->teleport($this->plugin->getServer()->getDefaultLevel()->getSafeSpawn());
		$player->setSpawn($this->plugin->getServer()->getDefaultLevel()->getSafeSpawn());
		if(count($this->players) == 0) {
				$this->reloadArena();
				return;
			}
		if($this->checkWin() !== false)
			$this->endGame();
	}
	
	public function endGame() {
		$this->plugin->getServer()->broadcastMessage("§eКоманда " . $this->checkWin() . " §aВыиграла на арене " . $this->name . "! Congrats!");
		$this->reloadArena();
	}
	
	public function checkWin() {
		$alive = [];
		foreach($this->teams as $team => $players) {
			if(count($this->teams[$team]) != 0)
				$alive[] = $team;
		}
		if(count($alive) == 1)
			return $alive[0];
		else
			return false;
	}
	
	public function kill(Player $player) {
		if(!$this->hasBed($player)) {
			$this->removePlayer($player);
		}
	}
	
	public function reloadArena() {
		$this->plugin->getServer()->getScheduler()->scheduleRepeatingTask(new ReloadTask($this), 20 * 4);
	}
	
	private function startGameTimer() {
		foreach($this->players as $joinedPlayer) {
			$joinedPlayer->sendMessage("§aВсе игроки зашли, §bигра начнётся через §610 §bсекунд!");
		}
		$this->plugin->getServer()->getScheduler()->scheduleRepeatingTask(new TimerTask($this->plugin, $this), 20);
	}
	
	public function breakBed($player, Block $block) {
		foreach($this->beds as $number => $bed) {
			if(($bed["x"] == $block->x && $bed["y"] == $block->y && $bed["z"] == $block->z) || ($bed["x2"] == $block->x && $bed["y2"] == $block->y && $bed["z2"] == $block->z)) {
				if($player != null && $number == $this->getTeam($player)) {
					$player->sendPopup("§cВы не можете ломать свою кровать!");
					return false;
				}
				foreach($this->players as $p) {
					if($player != null)
						$p->sendMessage("§aКровать команды " . $number . " §aбыла §cсломана " . $player->getName() . "!");
					else
						$p->sendMessage("§aКровать команды " . $number . " §cвзорвалась!");
				}
				foreach($this->teams[$number] as $p) {
					$p->sendPopup(T::RED . "Ваша кровать " . ($player != null ? ("была сломана игроком" . $player->getName() . "!") : "взорвалась!"));
				}
				$this->plugin->getServer()->getLevelByName($this->name)->setBlock(new Vector3($bed["x"], $bed["y"], $bed["z"]), new Block(0));
				$this->plugin->getServer()->getLevelByName($this->name)->setBlock(new Vector3($bed["x2"], $bed["y2"], $bed["z2"]), new Block(0));
				$this->eliminated[$number] = true;
			}
		}
	}
	
	public function hasBed(Player $player) {
		if(isset($this->eliminated[$this->getTeam($player)]))
			return false;
		return true;
	}
	
	public function hasBedTeam($team) {
		if(isset($this->eliminated[$team]))
			return false;
		return true;
	}
	
	public function showShopCategory(Player $player, int $id, $page = 0) {
		foreach($this->activeShops as $shop) {
			foreach($shop->inShop as $players) {
				foreach($players as $pl) {
					if($player == $pl) {
						$shop->updateShop($player, $id, $page, false);
						return;
					}
				}
			}
			foreach($shop->shopMain as $playerInShop) {
				if($player == $playerInShop) {
					$shop->updateShop($player, $id, $page, false);
				}
			}
		}
	}
	
	public function buy(Player $player) {
		foreach($this->activeShops as $shop) {
			foreach($shop->inShop as $key => $value) {
				foreach($value as $k => $playerInShop)
					if($player == $playerInShop)
						$shop->buy($player, $key);
				}
		}
	}
	
	public function closeShop(Player $player) {
		$player->getLevel()->setBlock($player->floor()->add(0, -4, 0), new Block(0));
		foreach($this->activeShops as $shop) {
			foreach($shop->coords as $num => $arr) {
				if($arr[0] == $player) {
					unset($shop->coords[$num]);
					break;
				}
			}
			foreach($shop->inShop as $key => $value) {
				foreach($value as $k => $playerInShop)
					if($player == $playerInShop) {
						unset($shop->inShop[$key][$k]);
					}
				}
			foreach($shop->shopMain as $key => $playerInShop) {
				if($player == $playerInShop) {
					unset($shop->shopMain[$key]);
					}
			}
		}
	}
	
	public function getShopCategory(Player $player) {
		foreach($this->activeShops as $shop) {
			foreach($shop->inShop as $category => $players) {
				foreach($players as $playerInShop) {
					if($player == $playerInShop)
						return $category;
				}
			}
		}
		return false;
	}
	
	public function getPage(Player $player) {
		foreach($this->activeShops as $shop) {
			foreach($shop->page as $page => $p) {
				foreach($p as $pl) {
					if($player == $pl)
						return $page;
				}
			}
		}
		return false;
	}
	
	public function isInShopMain(Player $player) {
		foreach($this->activeShops as $shop) {
			foreach($shop->shopMain as $playerInShop) {
				if($player == $playerInShop)
					return true;
			}
		}
		return false;
	}
	
	public function isInShop(Player $player) {
		foreach($this->activeShops as $shop) {
			foreach($shop->inShop as $category => $players) {
				foreach($players as $playerInShop) {
					if($player == $playerInShop) {
						return true;
					}
				}
			}
		}
		return false;
	}
	
	public function startGame() {
		$this->start = true;
		foreach($this->detonators as $team => $detonator) {
			$part = new FloatingTextParticle(new Vector3($detonator["x"] + 0.5, $detonator["y"] + 1.1337 /* apasna \(._.)/ */, $detonator["z"] + 0.5), "", "");
			$this->plugin->getServer()->getLevelByName($this->name)->addParticle($part);
			$this->timers[$team] = array($part, (60 * 5), new Vector3($detonator["x"], $detonator["y"], $detonator["z"]));
		}
		$this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($this->detonatorTask = new DetonatorTask($this->plugin, $this->timers, $this), 20);
		$this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($this->materialTask = new MaterialsTask($this->plugin, $this->materials, $this), 20);
		$this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($this->popupTask = new PopupTask($this->plugin, $this), 20 * 30);
		$this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($this->warningTask = new WarningTask($this->plugin, $this), 8);
		$this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($this->teleportTask = new TeleportTask($this->plugin, $this), 10);
		foreach($this->shops as $shop) {
			$shop = new Shop($shop["x"], $shop["y"], $shop["z"], $this->plugin->getServer()->getLevelByName($this->name));
			$this->activeShops[] = $shop;
		}
		$level = $this->plugin->getServer()->getLevelByName($this->name);
		foreach($this->players as $player) {
			$player->setHealth(20);
			$player->sendMessage("§aИгра началась! §eУдачи!");
			foreach($this->teams as $number => $t) {
				$max = $this->neededPlayers / $this->teamsCount;
				if(count($t) < $max) {
					$team = $number;
					$this->changeTeam($player, $number);
					break;
				}
			}
			$player->teleport(new Vector3($this->spawn[$team]["x"], $this->spawn[$team]["y"], $this->spawn[$team]["z"]));
			$player->setSpawn(new Position($this->spawn[$team]["x"], $this->spawn[$team]["y"], $this->spawn[$team]["z"], $this->plugin->getServer()->getLevelByName($this->name)));
		}
		foreach($this->players as $player) {
			foreach($this->players as $player2) {
				if($player2 != $player && $this->getTeam($player) == $this->getTeam($player2)) {
					$pk = new SetEntityDataPacket();
					$pk->eid = $player2->getId();
					$pk->metadata = [0 => [0, 0], 1 => [1, 300], 2 => [4, (T::GREEN . "[" . $this->getTeam($player) . "]" . $player2->getName())], 3 => [0, 1], 4 => [0, 0], 15 => [0, 0]];
					$player->dataPacket($pk);
				} else {
					$pk = new SetEntityDataPacket();
					$pk->eid = $player2->getId();
					$pk->metadata = [0 => [0, 0], 1 => [1, 300], 2 => [4, (T::RED . "[" . $this->getTeam($player2) . "]" . $player2->getName())], 3 => [0, 1], 4 => [0, 0], 15 => [0, 0]];
					$player->dataPacket($pk);
				}
			}
		}
	}
}