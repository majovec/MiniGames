<?php

use pocketmine\scheduler\PluginTask;
use pocketmine\utils\TextFormat as T;

class WarningTask extends PluginTask {
	
	/** @var qBW */
	private $plugin;
	
	/** @var Room */
	private $room;
	
	public $stop = false;
	private $color = [];
	
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
		foreach($this->room->teams as $team => $players) {
			foreach($players as $player) {
				$time = $this->room->getTime($team);
				if($time <= 30 && $this->room->hasBedTeam($team)) {
					if(!isset($this->color[$player->getName()]))
						$this->color[$player->getName()] = false;
					$player->sendPopup(($this->color[$player->getName()] ? (T::RED) : (T::DARK_RED)) . "До взрыва вашей кровати осталось " . date("i:s", $time) . "!");
					if($this->color[$player->getName()])
						$this->color[$player->getName()] = false;
					else
						$this->color[$player->getName()] = true;
				}
			}
		}
	}
}