<?php

use pocketmine\scheduler\PluginTask;
use pocketmine\utils\TextFormat as T;

class PopupTask extends PluginTask {
	
	/** @var qBW */
	private $plugin;
	
	/** @var Room */
	private $room;
	
	public $stop = false;
	
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
		foreach($this->room->players as $player) {
			$team = $this->room->getTeam($player);
			$teams = "";
			foreach($this->room->teams as $t => $players) {
				$time = $this->room->getTime($t);
				if($t != $team) {
					$teams .= T::DARK_RED . "Команда " . $t . " - " . count($players) . " игроков, " . ($this->room->hasBedTeam($t) ? (($time <= 30 ? T::RED : T::YELLOW) . date("i:s", $time) . " до взрыва\n") : ("кровати нет.\n"));
				} else {
					$teams .= T::GREEN . "Ваша команда(" . $t . ") - " . count($players) . " игроков, " . ($this->room->hasBedTeam($t) ? (($time <= 30 ? T::RED : T::YELLOW) . date("i:s", $time) . " до взрыва\n") : ("кровати нет.\n"));
				}
			}
			$player->sendMessage(T::BLACK . "------------------------------\n" . $teams . T::BLACK . "------------------------------");
		}
	}
}