<?php

use pocketmine\scheduler\PluginTask;
use pocketmine\utils\TextFormat as T;

class TeleportTask extends PluginTask {
	
	/** @var qBW */
	private $plugin;
	
	/** @var Room */
	private $room;
	
	public $stop = false;
	public $players = [];
	
	public function __construct(qBW $plugin, Room $room) {
		parent::__construct($plugin, $room);
		$this->plugin = $plugin;
		$this->room =  $room;
	}
	
	function __destruct() {
		
	}
	
	public function onRun($currentTick) {
		if($this->stop)
			return;
		foreach($this->players as $num => $arr) {
			if($arr[0] <= 0) {
				$player = $arr[1];
				$team = $this->room->getTeam($player);
				$player->teleport($player->getSpawn());
				unset($this->players[$num]);
			} else {
				$this->players[$num][0] -= 10;
				$time = round($this->players[$num][0] / 20);
				$this->players[$num][1]->sendPopup(T::GRAY . "Вы будете телепортированы через " . $time . " секунд...");
			}
		}
	}
}