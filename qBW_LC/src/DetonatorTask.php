<?php

use pocketmine\scheduler\PluginTask;
use pocketmine\utils\TextFormat as T;
use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\level\particle\HugeExplodeParticle;
use pocketmine\level\sound\{ExplodeSound, ClickSound, TNTPrimeSound};

class DetonatorTask extends PluginTask {
	
	/** @var qBW */
	private $plugin;
	
	/** @var Room */
	private $room;
	
	/** @var array */
	public $timers;
	
	public $stop = false;
	
	public function __construct(qBW $plugin, array $timers, Room $room) {
		parent::__construct($plugin, $timers, $room);
		$this->timers = $timers;
		$this->plugin = $plugin;
		$this->room =  $room;
	}
	
	function add($team, $value) {
		$this->timers[$team][1] += $value;
	}
	
	public function onRun($currentTick) {
		if($this->stop)
			return;
		foreach($this->timers as $team => $timer) {
			if(isset($this->room->eliminated[$team]))
				continue;
			$this->timers[$team][1]--;
			if($this->timers[$team][1] == 0) {
				$this->timers[$team][0]->setTitle(T::RED . "00:00");
				foreach($this->timers[$team][0]->encode() as $pk) {
					foreach($this->plugin->getServer()->getLevelByName($this->room->name)->getPlayers() as $player) {
						$player->dataPacket($pk);
					}
				}
				$bed = $this->room->beds[$team];
				$block = new Block(0);
				$block->x = $bed["x"];
				$block->y = $bed["y"];
				$block->z = $bed["z"];
				$this->room->breakBed(null, $block);
				$this->plugin->getServer()->getLevelByName($this->room->name)->setBlock($timer[2], new Block(Block::OBSIDIAN));
				$this->plugin->getServer()->getLevelByName($this->room->name)->addParticle(new HugeExplodeParticle($timer[2]));
				$this->plugin->getServer()->getLevelByName($this->room->name)->addSound(new ExplodeSound($timer[2]));
				continue;
			}
			if($this->timers[$team][1] <= 30) {
				$this->plugin->getServer()->getLevelByName($this->room->name)->addSound(new ClickSound($this->timers[$team][2]));
				$color = T::RED;
			} else {
				$color = T::YELLOW;
			}
			if($this->timers[$team][1] == 3)
				$this->plugin->getServer()->getLevelByName($this->room->name)->addSound(new TNTPrimeSound($this->timers[$team][2]));
			$this->timers[$team][0]->setTitle($color . date("i:s", $this->timers[$team][1]));
			foreach($this->timers[$team][0]->encode() as $pk) {
				foreach($this->plugin->getServer()->getLevelByName($this->room->name)->getPlayers() as $player) {
					$player->dataPacket($pk);
				}
			}
		}
	}
}