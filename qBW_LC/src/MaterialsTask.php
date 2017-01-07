<?php

use pocketmine\scheduler\PluginTask;
use pocketmine\math\Vector3;
use pocketmine\item\Item;

class MaterialsTask extends PluginTask {
	
	/** @var qBW */
	private $plugin;
	
	/** @var Room */
	private $room;
	
	/** @var array */
	private $materials;
	
	private $iron = 8;
	private $gold = 16;
	private $clock = 12;
	public $stop = false;
	
	public function __construct(qBW $plugin, array $materials, Room $room) {
		parent::__construct($plugin, $materials, $room);
		$this->materials = $materials;
		$this->plugin = $plugin;
		$this->room =  $room;
	}
	
	function __destruct() {
		
	}
	
	public function onRun($currentTick) {
		if($this->stop)
			return;
		$level = $this->plugin->getServer()->getLevelByName($this->room->name);
		$this->iron--;
		$this->gold--;
		$this->clock--;
		foreach($this->materials as $material => $value) {
			foreach($value as $coords) {
				$v3 = new Vector3($coords[0], $coords[1], $coords[2]);
				if($material == "bronze") {
					$item = Item::get(Item::BRICK, 0, 1);
					$level->dropItem($v3, $item);
					continue;
				} elseif($material == "iron" && $this->iron <= 0) {
					$item = Item::get(Item::IRON_INGOT, 0, 1);
					$level->dropItem($v3, $item);
					continue;
				} elseif($material == "gold" && $this->gold <= 0) {
					$item = Item::get(Item::GOLD_INGOT, 0, 1);
					$level->dropItem($v3, $item);
					continue;
				} elseif($material == "clock" && $this->clock <= 0) {
					$item = Item::get(Item::CLOCK, 0, 1);
					$level->dropItem($v3, $item);
					continue;
				}
			}
		}
		if($this->iron <= 0)
			$this->iron = 8;
		if($this->gold <= 0)
			$this->gold = 16;
		if($this->clock <= 0)
			$this->clock = 12;
	}
}