<?php

use pocketmine\scheduler\PluginTask;

class TimerTask extends PluginTask {
	
	/** @var object */
	private $objectToTick;
	
	public function __construct(qBW $plugin, $objectToTick = null) {
		parent::__construct($plugin);
		$this->objectToTick = $objectToTick;
	}
	
	function __destruct() {
		
	}
	
	public function onRun($currentTick) {
		$this->objectToTick->tick($this);
	}
}