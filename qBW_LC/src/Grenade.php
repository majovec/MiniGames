<?php
use pocketmine\level\format\FullChunk;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\Network;
use pocketmine\network\protocol\AddItemEntityPacket;
use pocketmine\Player;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;

use pocketmine\item\Item;
use pocketmine\event\entity\EntityCombustByEntityEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\scheduler\PluginTask;
use pocketmine\level\particle\{ExplodeParticle, DustParticle};
use pocketmine\level\sound\{FizzSound,DoorBumpSound};
class Grenade extends NUDAKNAXYUCOCUYMEN9{
	const NETWORK_ID = 64;
	public $width = 0.25;
	public $length = 0.25;
	public $height = 0.25;
	protected $gravity = 0.04;
	protected $drag = 0.02;
	public $server;
	public $plugin;
	public function __construct($plugin, $server, FullChunk $chunk, CompoundTag $nbt, Entity $shootingEntity = null){
		parent::__construct($chunk, $nbt, $shootingEntity);
		$this->server = $server;
		$this->plugin = $plugin;
	}
	public function onUpdate($currentTick){
		if($this->closed){
			return false;
		}
		$this->timings->startTiming();
		$hasUpdate = parent::onUpdate($currentTick);
		if($this->age > 1200 or $this->isCollided){
			$this->server->getScheduler()->scheduleRepeatingTask(new GrenadeTask($this->plugin, $this->getLocation()), 20);
			$this->kill();
			$hasUpdate = true;
		}
		$this->timings->stopTiming();
		return $hasUpdate;
	}
	public function spawnTo(Player $player){
		$pk = new AddItemEntityPacket();
		$pk->type = self::NETWORK_ID;
		$pk->eid = $this->getId();
		$pk->x = $this->x;
		$pk->y = $this->y;
		$pk->z = $this->z;
		$pk->speedX = $this->motionX;
		$pk->speedY = $this->motionY;
		$pk->speedZ = $this->motionZ;
		$pk->item = Item::get(Item::CLAY_BLOCK, 0, 1);
		$player->dataPacket($pk);
		$this->sendData($player);
		parent::spawnTo($player);
	}
}

class GrenadeTask extends PluginTask {

	private $pos;
	private $tick = 0;
	
	function __construct($plugin, $pos) {
		parent::__construct($plugin, $pos);
		$this->pos = $pos;
	}
	
	function onRun($c) {
		if($this->tick >= 80)
			return;
		$this->tick += 20;
	$level = $this->pos->getLevel();
	$center = new Vector3($this->pos->getX(), $this->pos->getY(), $this->pos->getZ());
	for($x = $center->getX() - 5; $x <= $center->getX() + 5; $x += 1) {
		for($y = $center->getY() - 5; $y <= $center->getY() + 5; $y += 1) {
			for($z = $center->getZ() - 5; $z <= $center->getZ() + 5; $z += 1) {
				$level->addParticle(new ExplodeParticle(new Vector3($x, $y, $z)));
			}
		}
	}
	$level->addSound(new FizzSound($center));
}
}

abstract class NUDAKNAXYUCOCUYMEN9 extends Entity{
	/** @var Entity */
	public $shootingEntity = null;
	protected $damage = 0;
	public $hadCollision = false;
	public function __construct(FullChunk $chunk, CompoundTag $nbt, Entity $shootingEntity = null){
		$this->shootingEntity = $shootingEntity;
		parent::__construct($chunk, $nbt);
	}
	public function attack($damage, EntityDamageEvent $source){
		if($source->getCause() === EntityDamageEvent::CAUSE_VOID){
			parent::attack($damage, $source);
		}
	}
	protected function initEntity(){
		parent::initEntity();
		$this->setMaxHealth(1);
		$this->setHealth(1);
		if(isset($this->namedtag->Age)){
			$this->age = $this->namedtag["Age"];
		}
	}
	public function canCollideWith(Entity $entity){
		return $entity instanceof Living and !$this->onGround;
	}
	public function saveNBT(){
		parent::saveNBT();
		$this->namedtag->Age = new ShortTag("Age", $this->age);
	}
	public function onUpdate($currentTick){
		if($this->closed){
			return false;
		}
		$tickDiff = $currentTick - $this->lastUpdate;
		if($tickDiff <= 0 and !$this->justCreated){
			return true;
		}
		$this->lastUpdate = $currentTick;
		$hasUpdate = $this->entityBaseTick($tickDiff);
		if($this->isAlive()){
			$movingObjectPosition = null;
			if(!$this->isCollided){
				$this->motionY -= $this->gravity;
			}
			$moveVector = new Vector3($this->x + $this->motionX, $this->y + $this->motionY, $this->z + $this->motionZ);
			$list = $this->getLevel()->getCollidingEntities($this->boundingBox->addCoord($this->motionX, $this->motionY, $this->motionZ)->expand(1, 1, 1), $this);
			$nearDistance = PHP_INT_MAX;
			$nearEntity = null;
			foreach($list as $entity){
				if(/*!$entity->canCollideWith($this) or */
				($entity === $this->shootingEntity and $this->ticksLived < 5)
				){
					continue;
				}
				$axisalignedbb = $entity->boundingBox->grow(0.3, 0.3, 0.3);
				$ob = $axisalignedbb->calculateIntercept($this, $moveVector);
				if($ob === null){
					continue;
				}
				$distance = $this->distanceSquared($ob->hitVector);
				if($distance < $nearDistance){
					$nearDistance = $distance;
					$nearEntity = $entity;
				}
			}
			if($nearEntity !== null){
				$movingObjectPosition = MovingObjectPosition::fromEntity($nearEntity);
			}
			if($movingObjectPosition !== null){
				if($movingObjectPosition->entityHit !== null){
					$this->server->getPluginManager()->callEvent(new ProjectileHitEvent($this));
					$motion = sqrt($this->motionX ** 2 + $this->motionY ** 2 + $this->motionZ ** 2);
					$damage = ceil($motion * $this->damage);
					if($this instanceof Arrow and $this->isCritical){
						$damage += mt_rand(0, (int) ($damage / 2) + 1);
					}
					if($this->shootingEntity === null){
						$ev = new EntityDamageByEntityEvent($this, $movingObjectPosition->entityHit, EntityDamageEvent::CAUSE_PROJECTILE, $damage);
					}else{
						$ev = new EntityDamageByChildEntityEvent($this->shootingEntity, $this, $movingObjectPosition->entityHit, EntityDamageEvent::CAUSE_PROJECTILE, $damage);
					}
					$movingObjectPosition->entityHit->attack($ev->getFinalDamage(), $ev);
					$this->hadCollision = true;
					if($this->fireTicks > 0){
						$ev = new EntityCombustByEntityEvent($this, $movingObjectPosition->entityHit, 5);
						$this->server->getPluginManager()->callEvent($ev);
						if(!$ev->isCancelled()){
							$movingObjectPosition->entityHit->setOnFire($ev->getDuration());
						}
					}
					$this->kill();
					return true;
				}
			}
			$this->move($this->motionX, $this->motionY, $this->motionZ);
			if($this->isCollided and !$this->hadCollision){
				$this->hadCollision = true;
				$this->motionX = 0;
				$this->motionY = 0;
				$this->motionZ = 0;
			}elseif(!$this->isCollided and $this->hadCollision){
				$this->hadCollision = false;
			}
			if(!$this->onGround or abs($this->motionX) > 0.00001 or abs($this->motionY) > 0.00001 or abs($this->motionZ) > 0.00001){
				$f = sqrt(($this->motionX ** 2) + ($this->motionZ ** 2));
				$this->yaw = (atan2($this->motionX, $this->motionZ) * 180 / M_PI);
				$this->pitch = (atan2($this->motionY, $f) * 180 / M_PI);
				$hasUpdate = true;
			}
			$this->updateMovement();
		}
		return $hasUpdate;
	}
}