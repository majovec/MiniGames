<?php

use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat as T;
use pocketmine\math\Vector3;
use pocketmine\utils\Config;
use pocketmine\level\Position;
use pocketmine\scheduler\PluginTask;
use pocketmine\block\Block;
use pocketmine\Player;

class qBW extends PluginBase {

	public $log;

	/** @var Room[] */
	public $rooms = array();

	public function getRoomByName($name) {
		foreach($this->rooms as $room) {
			if(strtolower($room->name) == strtolower($name))
				return $room;
		}
		return false;
	}
	
	public function addJoinSign($arenaName, Position $coordinates) {
		$config = new Config($this->getDataFolder() . "signs.json");
		$config->set(strtolower($arenaName), array("x" => $coordinates->x, "y" => $coordinates->y, "z" => $coordinates->z));
		$config->save();
	}
	
	public function addLeaveSign(Position $coordinates) {
		$config = new Config($this->getDataFolder() . "leavesigns.json");
		$arr = $config->getNested("signs");
		$arr[] = array("x" => $coordinates->x, "y" => $coordinates->y, "z" => $coordinates->z);
		$config->set("signs", $arr);
		$config->save();
	}
	
	public function getJoinSigns() {
		$config = new Config($this->getDataFolder() . "signs.json");
		return $config->getAll();
	}
	
	public function getLeaveSigns() {
		$config = new Config($this->getDataFolder() . "leavesigns.json");
		return $config->getNested("signs");
	}
	
	public function isInGame(Player $player) {
		foreach($this->rooms as $room) {
			foreach($room->players as $p) {
				if($player == $p)
					return true;
			}
		}
		return false;
	}
	
	public function getPlayerRoom(Player $player) {
		foreach($this->rooms as $room) {
			foreach($room->players as $p) {
				if($player == $p)
					return $room;
			}
		}
		return false;
	}
	
	public function reloadRoom(Room $room) {
		$name = $room->name;
		foreach($this->rooms as $key => $r) {
			if($room == $r)
				unset($this->rooms[$key]);
		}
		$arenaConfig = new Config($this->getDataFolder() . "arenas/" . $name . "/config.json", Config::JSON);
		$this->rooms[] = new Room($this, $arenaConfig->getNested("name"), $arenaConfig->getNested("teams"), $arenaConfig->getNested("players"), $arenaConfig->getNested("beds"), $arenaConfig->getNested("shops"), $arenaConfig->getNested("hub"), $arenaConfig->getNested("spawn"), $arenaConfig->getNested("materials"));
	}
	
	function onEnable() {
		$this->log = $this->getServer()->getLogger();
		$this->log->info(T::AQUA . "qBW by vk.com/" . T::RED . "lacky_craft");
		$this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
		@mkdir($this->getDataFolder());
		@mkdir($this->getDataFolder() . "arenas");
		$files = array_slice(scandir($this->getDataFolder() . "arenas"), 2);
		foreach($files as $file) {
			$arenaConfig = new Config($this->getDataFolder() . "arenas/" . $file . "/config.json", Config::JSON, array("name" => "bw", "teams" => 1, "players" => 1, "beds" => array(array("x" => 0, "y" => 70, "z" => 0, "x2" => 1, "y2" => 70, "z2" => 0, "detonator" => array("x" => 1, "y" => 70, "z" => 1))), "shops" => array(array("x" => -10, "y" => 62, "z" => 1)), "hub" => array("x" => 0, "y" => 70, "z" => 0), "spawn" => array(array("x" => 1, "y" => 80, "z" => 5)), "materials" => array("bronze" => array(array(0, 70, 0), array(2, 70, 0)), "iron" => array(array(4, 80, 4)), "gold" => array(array(1, 70, 1)), "clock" => array(array(0, 70, 0))) /* */));
			$arenaConfig->save();
			$this->rooms[] = new Room($this, $arenaConfig->getNested("name"), $arenaConfig->getNested("teams"), $arenaConfig->getNested("players"), $arenaConfig->getNested("beds"), $arenaConfig->getNested("shops"), $arenaConfig->getNested("hub"), $arenaConfig->getNested("spawn"), $arenaConfig->getNested("materials"));
		}
		$this->log->info("Registered " . count($this->rooms) . " rooms.");
	}
}