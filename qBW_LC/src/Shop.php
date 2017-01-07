<?php

use pocketmine\tile\Tile;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\item\{
	Item, enchantment\Enchantment
};
use pocketmine\math\Vector3;
use pocketmine\entity\Entity;
use pocketmine\tile\{
	Chest, Dropper
};
use pocketmine\Player;
use pocketmine\utils\TextFormat as T;

class Shop {

	/** @var float */
	private $x;
	private $y;
	private $z;
	
	/** @var Level */
	private $level;
	
	/** @var Entity */
	private $entity;
	
	/** @var array */
	public $coords = [];
	
	/** @var int[] */
	private $goods = array(
		Item::SANDSTONE => array(array(Item::SANDSTONE, 0, 16, 32), array(Item::END_STONE, 0, 7, 1), array(Item::SANDSTONE_STAIRS, 0, 16, 32), array(Item::SLIME_BLOCK, 0, 32, 1)),
		Item::CHAIN_CHESTPLATE => array(array(Item::LEATHER_CAP, 0, 1, 1), array(Item::LEATHER_PANTS, 0, 1, 1), array(Item::LEATHER_BOOTS, 0, 1, 1), array(Item::CHAIN_CHESTPLATE, 1, 1, 1), array(Item::IRON_CHESTPLATE, 1, 3, 1), array(Item::DIAMOND_CHESTPLATE, 1, 7, 1)),
		Item::WOODEN_PICKAXE => array(array(Item::WOODEN_PICKAXE, 0, 4, 1), array(Item::STONE_PICKAXE, 1, 2, 1), array(Item::IRON_PICKAXE, 2, 1, 1), array(Item::DIAMOND_PICKAXE, 2, 4, 1)),
		Item::GOLD_SWORD => array(array(Item::STICK, 0, 8, 1), array(Item::WOODEN_SWORD, 1, 1, 1), array(Item::STONE_SWORD, 1, 3, 1), array(Item::IRON_SWORD, 2, 5, 1), array(Item::DIAMOND_SWORD, 2, 13, 1)),
		Item::COOKED_PORKCHOP => array(array(Item::COOKED_PORKCHOP, 0, 12, 6), array(Item::GOLDEN_APPLE, 2, 2, 1)),
		Item::WORKBENCH => array(array(Item::WORKBENCH, 0, 64, 1)),
		Item::CARPET => array(array(Item::CARPET, 0, 10, 1)),
		Item::CHEST => array(array(Item::CHEST, 1, 1, 1)),
		Item::TNT => array(array(Item::LADDER, 0, 1, 1), array(Item::GUNPOWDER, 1, 3, 1), array(Item::CLAY, 1, 15, 1), array(Item::FISHING_ROD, 1, 7, 1), array(Item::POTION, 2, 2, 1), array(Item::BLAZE_ROD, 2, 3, 1)),
		Item::FURNACE => array(array(Item::FURNACE, 2, 6, 1), array(Item::GLOWSTONE_DUST, 2, 8, 1))
	);
	
	/** @var Player[] */
	public $shopMain = array();
	public $inShop = array();
	public $page = array();
	
	function __construct($x, $y, $z, $level) {
		$this->x = $x;
		$this->y = $y;
		$this->z = $z;
		$this->level = $level;
		$this->init();
	}
	
	function __destruct() {
		
	}
	
	function getX() {
		return $this->x;
	}
	function getY() {
		return $this->y;
	}
	function getZ() {
		return $this->z;
	}
	
	function init() {
        $nbt = new CompoundTag;
        $nbt->Pos = new ListTag("Pos", [
            new DoubleTag("", $this->x + 0.5),
            new DoubleTag("", $this->y),
            new DoubleTag("", $this->z + 0.5)
        ]);
        $nbt->Rotation = new ListTag("Rotation", [
            new FloatTag("", 0),
            new FloatTag("", 0)
        ]);
        $nbt->Health = new ShortTag("Health", 1);
        $nbt->CustomName = new StringTag("CustomName", T::GOLD . "Shop");
        $nbt->CustomNameVisible = new ByteTag("CustomNameVisible", 1);
        $this->entity = Entity::createEntity("Villager", $this->level->getChunk($this->x >> 4, $this->y >> 4), $nbt);
        $this->entity->spawnToAll();
	}
	
	function buy(Player $player, $category) {
		foreach($this->page as $num => $players) {
			foreach($players as $p) {
				if($player == $p) {
					$item = Item::get($this->goods[$category][$num][0], 0, $this->goods[$category][$num][3]);
					$id = Item::BRICK;
					if($this->goods[$category][$num][1] == 1)
						$id = Item::IRON_INGOT;
					elseif($this->goods[$category][$num][1] == 2)
						$id = Item::GOLD_INGOT;
					foreach($player->getInventory()->getContents() as $money) {
						if($money->getId() == $id) {
							$money->setCount($money->getCount() - $this->goods[$category][$num][2]);
							break;
						}
					}
					if($item->getId() == Item::POTION) {
						$item->setDamage(22);
					} elseif($item->getId() == Item::STICK) {
						$item->addEnchantment(Enchantment::getEnchantment(12));
					}
					$player->getInventory()->addItem($item);
					return;
				}
			}
		}
	}
	
	function updateShop($player, $category = null, $page = 0, $set = true) {
		$i = 0;
		$position = $player->floor()->subtract(0, 4, 0);
		foreach($this->coords as $num => $arr) {
			if($arr[0] == $player) {
				$position = $arr[1];
				$i = 1;
				break;
			}
		}
		if($i === 0)
			$this->coords[] = array($player, $player->floor()->subtract(0, 4, 0));
		$chestBlock = new \pocketmine\block\Chest();
		if($set) {
			$this->level->setBlock($player->floor()->add(0, -4, 0), $chestBlock, true, true);
			$nbt = new CompoundTag("", [
						new ListTag("Items", []),
						new StringTag("id", Tile::CHEST),
						new IntTag("x", $position->getX()),
						new IntTag("y", $position->getY()),
						new IntTag("z", $position->getZ()),
						new StringTag("CustomName", T::GOLD . "Shop")
					]);
			$nbt->Items->setTagType(NBT::TAG_Compound);
			$tile = new Chest($this->level->getChunk($position->getX() >> 4, $position->getZ() >> 4), $nbt);
			$inventory = $tile->getInventory();
		} else {
			$inventory = $player->getLevel()->getTile($position);
			if($inventory == null)
				return;
			else
				$inventory = $inventory->getInventory();
		}
		$i = 0;
		$inventory->clearAll();
		if($category != null) {
			foreach($this->page as $pg => $players) {
				foreach($players as $key => $pl) {
					if($player == $pl)
						unset($this->page[$pg][$key]);
				}
			}
			$this->page[$page][] = $player;
			if(($k = array_search($player, $this->shopMain)) !== false)
				unset($this->shopMain[$k]);
			foreach($this->inShop as $cat => $num) {
				foreach($num as $number => $p) {
					if($p == $player) {
						unset($this->inShop[$cat][$number]);
						}
				}
			}
			$this->inShop[$category][] = $player;
			$item = Item::get($this->goods[$category][$page][0], 0, $this->goods[$category][$page][3]);
			if($item->getId() == Item::POTION) {
				$item->setDamage(22);
			} elseif($item->getId() == Item::STICK) {
				$item->addEnchantment(Enchantment::getEnchantment(12));
			}
			$page == 0 ? $inventory->setItem(0, Item::get(Item::AIR, 0, 1)) : $inventory->setItem(0, Item::get(Item::BOW, 0, 1));
			$id = Item::BRICK;
			if($this->goods[$category][$page][1] == 1)
				$id = Item::IRON_INGOT;
			elseif($this->goods[$category][$page][1] == 2)
				$id = Item::GOLD_INGOT;
			$inventory->setItem(1, Item::get($id, 0, $this->goods[$category][$page][2]));
			$player->getInventory()->contains(Item::get($id, 0, $this->goods[$category][$page][2])) ? $inventory->setItem(2, Item::get(Item::EMERALD_BLOCK, 0, 1)) : $inventory->setItem(2, Item::get(Item::REDSTONE_BLOCK, 0, 1));
			$inventory->setItem(3, $item);
			$page == (count($this->goods[$category]) - 1) ? $inventory->setItem(4, Item::get(Item::AIR, 0, 1)) : $inventory->setItem(4, Item::get(Item::ARROW, 0, 1));
			return $inventory;
		}
		foreach($this->goods as $category => $items) {
			if($category == Item::FURNACE && !$player->hasPermission("qbw.donate.vip") && !$player->hasPermission("qbw.donate.mvp") && !$player->hasPermission("qbw.donate.deluxe"))
				continue;
			$item = Item::get($category, 0, 1);
			if($i > 0 && $i < 4)
				$item->addEnchantment(Enchantment::getEnchantment(-1));
			$inventory->setItem($i, $item);
			$i++;
		}
		return $inventory;
	}
	
	function show($player) {
		$this->shopMain[] = $player;
		$player->addWindow($this->updateShop($player));
	}
}