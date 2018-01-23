<?php

namespace naoki1510\BlockEditor;

use pocketmine\Player;
use pocketmine\Server;
use pocketmine\item\Item;
use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\utils\Config;
use pocketmine\event\Listener;
use pocketmine\scheduler\Task;
use pocketmine\command\Command;
use pocketmine\event\Cancellable;
use pocketmine\plugin\PluginBase;
use pocketmine\block\BlockFactory;
use pocketmine\command\CommandSender;
use pocketmine\scheduler\TaskHandler;
use pocketmine\inventory\PlayerInventory;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerInteractEvent;

class Main extends PluginBase implements Listener{

	/** @var Int */
	public  $digit = 0;
	private $conf_id;
	/** @var Array */
	private $_pos = [];
	/** @var BlockEditTask[] */
	private $tasks = [];

	/** @var Config */
	//private $config;

	public function onEnable(){
		// 起動時のメッセージ
		$this->getLogger()->info("§aThank you for using this plugin.");

		// コンフィグ作成
		/*
		if (!file_exists($this->getDataFolder())) @mkdir($this->getDataFolder());
		$this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
		if(!$this->config->exists("item")){
			$this->config->set('item',270);
		}
		*/
		// 代わりに $this->getConfig() でできるようです。

		//Item の初期設定
		try {
			if ($this->getConfig()->exists('Item')) {
				$conf_id = Item::get($this->getConfig()->get('Item'))->getId();
			} else {
				$conf_id = 270;
				$this->getConfig()->set('Item', $conf_id);
				$this->getConfig()->save();
			}
		} catch (\InvalidArgumentException $e) {
			//Item IDが不正な時
			$this->getServer()->getLogger()->warning($e->getMessage());
			$conf_id = 270;
			$this->getConfig()->set('Item', $conf_id);
			$this->getConfig()->save();
		}
		$this->conf_id = $conf_id;

		// イベントリスナー登録
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		//Commands: pos1 pos2 (pos) set cut replace undo redo stop clear

		if($sender instanceof Player){
			$player = $sender;
			//コマンド送信者がプレイヤーな時のみ
			switch ($command->getName()) {
				case "/pos1":
					//pos1,pos2をまとめて関数にしてもいいかも
					$num = 1;
					if (empty($args[0])) {
						if ($this->setPos($player, $player, $num)) {
							return true;
						}
					} else {
						switch ($args[0]) {
							case 'show':
								if ($this->issetPos($player, $num)) {
									$player->sendMessage('pos' . $num . ' is set to ' . $this->PostoStr($this->getPos($player, $num)));
								} else {
									$player->sendMessage('pos' . $num . ' is not set.');
								}
								break;

							case 'tp':
								$player->teleport($this->getPos($player, $num));
								$player->sendMessage('You was teleported to ' . $this->PostoStr($this->getPos($player, $num)));
								break;
						}
					}
					return false;
					break;

				case "/pos2":
					$num = 2;
					if (empty($args[0])) {
						if ($this->setPos($player, $player, $num)) {
							return true;
						}
					} else {
						switch ($args[0]) {
							case 'show':
								if ($this->issetPos($player, $num)) {
									$player->sendMessage('pos' . $num . ' is set to ' . $this->PostoStr($this->getPos($player, $num)));
								} else {
									$player->sendMessage('pos' . $num . ' is not set.');
								}
								break;

							case 'tp':
								$player->teleport($this->getPos($player, $num));
								$player->sendMessage('You was teleported to ' . $this->PostoStr($this->getPos($player, $num)));
								break;
						}
					}
					return false;
					break;

				case '/set':
				case '/s':
					switch (false) {
						case isset($args[0]):
							$player->sendMessage("§6Please set a block.");
							return false;
							break;

						case $this->issetPos($player, 1):
							$player->sendMessage("§6Please set pos1 before.");
							return true;
							break;

						case $this->issetPos($player, 2):
							$player->sendMessage("§6Please set pos2 before.");
							return true;
							break;
					}
					try {
						$id_meta = explode(':', array_shift($args));
						$b = BlockFactory::get((int)$id_meta[0], (int)($id_meta[1]) ?? 0);
					} catch (\InvalidArgumentException $e) {
						$player->sendMessage("§e" . $e->getMessage());
						return true;
					}
					$task = new BlockEditTask($player, $this->getPos($player, 1), $this->getPos($player, 2), $b);
					$task->setOptions($args, $task);
					$taskId = $this->getServer()->getScheduler()->scheduleRepeatingTask($task, $task->getOption("tick_interval", 'int'))->getTaskId();
					$this->tasks[$taskId] = $task;
					
					//TODO: このメッセージをコンフィグで設定可能に
					Server::getInstance()->broadcastMessage($player->getName() . "'s §bset§f(TaskID: §c" . $task->getTaskId() . "§f, §a" . $task->blockcount . "§f Blocks) started.");
					return true;
					break;

				case '/cut':
				case '/c':
					switch (false) {
						case $this->issetPos($player, 1):
							$player->sendMessage("§6Please set pos1 before.");
							return true;
							break;

						case $this->issetPos($player, 2):
							$player->sendMessage("§6Please set pos2 before.");
							return true;
							break;
					}
					$task = new BlockEditTask($player, $this->getPos($player, 1), $this->getPos($player, 2));
					$task->setOptions($args, $task);
					$taskId = $this->getServer()->getScheduler()->scheduleRepeatingTask($task, $task->getOption("tick_interval", 'int'))->getTaskId();
					$this->tasks[$taskId] = $task;
					
					//TODO: このメッセージをコンフィグで設定可能に
					Server::getInstance()->broadcastMessage($player->getName() . "'s §bcut§f(TaskID: §c" . $task->getTaskId() . "§f, §a" . $task->blockcount . "§f Blocks) started.");

					return true;
					break;

				case '/replace':
				case '/r':
					switch (false) {
						case isset($args[0]):
							$player->sendMessage("§6Please set a block(search).");
							return false;
							break;

						case isset($args[1]):
							$player->sendMessage("§6Please set a block(place).");
							return false;
							break;

						case $this->issetPos($player):
							if ($this->issetPos($player, 1)) {
								$num = 1;
							}elseif ($this->issetPos($player, 2)) {
								$num = 2;
							}else {
								return false;
							}
							$player->sendMessage("§6Please set pos$num first.");
							return true;
							break;
					}
					try {
						$id_meta_s = explode(':', array_shift($args));
						$b_s = BlockFactory::get((int)$id_meta_s[0], (int)@($id_meta_s[1]) ? : 0);
						$id_meta_p = explode(':', array_shift($args));
						$b_p = BlockFactory::get((int)$id_meta_p[0], (int)@($id_meta_p[1]) ? : 0);
					} catch (\InvalidArgumentException $e) {
						$player->sendMessage("§e" . $e->getMessage());
						return true;
					}

					$task = new BlockEditTask($player, $this->getPos($player, 1), $this->getPos($player, 2), $b_p, $b_s);
					$task->setOptions($args, $task);
					$task->setOption("compare_meta", isset($id_meta_s[1]));
					$taskId = $this->getServer()->getScheduler()->scheduleRepeatingTask($task, $task->getOption("tick_interval", 'int'))->getTaskId();
					$this->tasks[$taskId] = $task;

					Server::getInstance()->broadcastMessage($player->getName() . "'s §breplace§f(TaskID: §c" . $task->getTaskId() . "§f, §a" . $task->blockcount . "§f Blocks) started.");

					return true;
					break;

				case '/stop':
					if (!empty($args[0]) && is_numeric($args[0])) {
						$this->getServer()->getScheduler()->cancelTask(intval($args[0]));;
						return true;
					}
					break;

				case '/undo':
				case '/u':
					if (!empty($args[0]) && !empty($this->tasks[intval($args[0])])) {
						$taskid = (int)array_shift($args);
						$task = $this->tasks[$taskid];
						if ($task instanceof BlockEditTask) {
							$task->setPlayer($player);
							$newtask = $task->undo($args);
							$this->tasks[$newtask->getTaskId()] = $newtask;
							Server::getInstance()->broadcastMessage($player->getName() . "'s §bundo§f(TaskID: §c" . $newtask->getTaskId() . "§f for §d" . $taskid . "§f) started.");
							return true;
						} else {

						}
					}
					break;

				case '/redo':
					if (!empty($args[0]) && !empty($this->tasks[intval($args[0])])) {
						$taskid = (int)array_shift($args);
						$task = $this->tasks[$taskid];
						if ($task instanceof BlockEditTask) {
							$newtask = $task->redo($args);
							$newtask->setPlayer($player);
							$this->tasks[$newtask->getTaskId()] = $newtask;
							Server::getInstance()->broadcastMessage($player->getName() . "'s §bredo§f(TaskID: §c" . $newtask->getTaskId() . "§f copy of §d" . $taskid . " §a" . $newtask->blockcount . "§f Blocks) started.");
							return true;
						}
					}
					break;

				case '/clear':
					$this->removePos($player);
					foreach ($this->getTasks($player) as $taskId => $task) {
						unset($this->tasks[$taskId]);
						$player->sendMessage("TaskID: §c" . $taskId . "§f was removed.");
					}
					return true;
					break;

				default:
					$player->sendMessage("§bComming soon or it was not found");
					return false;
			}
			return false;
		}else {
			return false;
		}
		
	}

	public function onTouch(PlayerInteractEvent $e){
		$num = 1;
		$player = $e->getPlayer();
		$item = $player->getInventory()->getItemInHand();

		if ($item->getId() == $this->conf_id) {
			$b = $e->getBlock();
			if($b->x == 0 && $b->y == 0 && $b->z == 0 && $b->getId() == 0){
				//ブロックが壊されたとき
				//ブロックの座標を取得できないためBlockBreakEventの代わりには使えなさそう
			}else{
				$this->setPos($player, $b, $num, "Block info: " . $this->BlocktoStr($b));
			}
		}
	}

	public function onBreak(BlockBreakEvent $e){
		$num = 2;
		$player = $e->getPlayer();
		$item = $player->getInventory()->getItemInHand();

		if ($item->getId() == $this->conf_id) {
			$b = $e->getBlock();
			if ($b->x == 0 && $b->y == 0 && $b->z == 0 && $b->getId() == 0) {
				//Something Error
				$this->getLogger()->info("Error in BlockBreakEvent. Please try again later.");
			} else {
				$this->setPos($player, $b, $num, "Block info: " . $this->BlocktoStr($b));
				if($e instanceof Cancellable){
					//イベントのキャンセル
					$e->setCancelled(true);
				}
			}
		}

		
	}

	private function setPos(Player $player, Vector3 $playeros, Int $num, string $additionalmessage = NULL) : bool{
		$this->_pos[$player->getName()][$num] = ['x' => round($playeros->x, $this->digit), 'y' => round($playeros->y, $this->digit), 'z' => round($playeros->z, $this->digit)];
		$player->sendMessage('pos' . $num . ' is set to ' . $this->PostoStr($playeros) . "§f " . ($additionalmessage) ?: '');
		return true;
	}

	public function getPos(Player $player, Int $num) : Vector3{
		if($this->issetPos($player, $num)){
			$playeros = $this->_pos[$player->getName()][$num];
			$v = new Vector3($playeros['x'], $playeros['y'], $playeros['z']);
		}else{
			//データがないときには 0,0,0を返すので注意
			$v = new Vector3();
		}
		return $v;
	}

	public function removePos(Player $player, $num = 0) : bool{
		if($num == 0){
			$this->_pos[$player->getName()] = null;
			$player->sendMessage("Pos were deleted");
		}else{ 
			$this->_pos[$player->getName()][$num] = null;
			$player->sendMessage("Pos". $num ." was deleted");
		}
		return true;
	}

	private function issetPos(Player $player, int $num = 0) : bool{
		if ($num == 0) {
			return $this->issetPos($player, 1) && $this->issetPos($player, 2);
		}
		return isset($this->_pos[$player->getName()][$num]);
	}

	public function PostoStr(Vector3 $playeros) : string{
		return '§ax:' . round($playeros->x, $this->digit) . ' §by:' . round($playeros->y, $this->digit) . ' §cz:' . round($playeros->z, $this->digit);
	}

	public function BlocktoStr(Block $block) : string
	{
		return '§a' . implode("§f:§b", [$block->getId(), $block->getDamage()]);
	}

	/** @return BlockEditTask[] */
	public function getTasks(Player $player)
	{
		$result = [];
		foreach ($this->tasks as $taskId => $task) {
			if(!empty($task) && $task->getPlayer()->getName() == $player->getName()) $result[$taskId] = $task;
		}

		return $result;
	}
}