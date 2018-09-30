<?php

namespace naoki1510\BlockEditor;

use pocketmine\Player;
use pocketmine\Server;
use pocketmine\block\Block;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\scheduler\Task;
use pocketmine\scheduler\TaskHandler;
use pocketmine\utils\Config;
use pocketmine\command\Command;
use pocketmine\command\SimpleCommandMap;


class BlockEditTask extends Task
{


    /** @var bool */
    private $active;
    /** @var String */
    private $mode;
    /** @var Int */
    public $blockcount;
    private $index;
    /** @var Array */
    private $options, $default, $blockdata, $blocks, $short, $long;
    /** @var Player */
    public $player;
    /** @var Vector3 */
    private $max, $min, $start, $end, $now;
    /** @var Level */
    public $level;
    /** @var Block */
    public $place;
    public $search;
    /** @var Config */
    private $config;

    //set [ID] -s [SPEED]

    public function __construct(Player $player, Vector3 $start, Vector3 $end, Block $place = null, Block $search = null, array $options = [])
    {
        $this->config = Server::getInstance()->getPluginManager()->getPlugin('BlockEditor')->getConfig();

        $this->player = $player;
        $this->start = $start;
        $this->end = $end;
        $this->setMode('replace');
        empty($search) ? $this->setMode('set') : $this->search = $search;
        empty($place) ? $this->setMode('cut') : $this->place = $place;

        $this->blocks = [];
        $this->active = true;
        $this->min = new Vector3(min($start->x, $end->x), min($start->y, $end->y), min($start->z, $end->z));
        $this->max = new Vector3(max($start->x, $end->x), max($start->y, $end->y), max($start->z, $end->z));
        $this->now = clone $this->start;
        $this->blockcount = ($this->max->x - $this->min->x + 1) * ($this->max->y - $this->min->y + 1) * ($this->max->z - $this->min->z + 1);
        $this->level = $player->getLevel();

        foreach($this->config->getAll() as $key => $value){
            if(!empty($value['short_key']) && preg_match('/^[a-zA-Z]$/', $value['short_key'])) $this->short[$value['short_key']] = $value;
            if(!empty($value['long_key']) && preg_match('/^[a-zA-Z0-9_]+$/', $value['long_key'])) $this->short[$value['long_key']] = $value;
        }

        //Server::getInstance()->getCommandMap()->getCommand();

        $this->options = [
            "per_tick" => 1024,
            "tick_interval" => 5,
            "block_interval" => 1,
            "compare_meta" => false,
            'random' => false
        ];

        $this->default = $this->options;

        foreach ($options as $key => $value) {
            $this->options[$key] = $value;
        }

    }

    public function onRun(int $currentTick)
    {
        switch ($this->getMode()) {
            case 'set':
                if (empty($this->active)) return;
                for ($count = 1; $count <= $this->getOption('per_tick', 'int'); $count++) {
                    if ($this->active) {
                        array_push($this->blocks, [$this->PosToStr($this->now), $this->BlockToStr($this->level->getBlock($this->now))]);
                        $this->level->setBlock($this->now, $this->place);
                    }
                    if (!$this->nextPos($this->getOption('block_interval', 'int'))) {
                        break;
                    }
                }
                break;

            case 'replace':
                
                if (!$this->active) return;
                if (!empty($this->search)) {
                    for ($count = 1; $count <= $this->getOption('per_tick', 'int'); $count++) {
                        //置き換え対象かどうかをまとめて調査
                        do {
                            //$this->player->sendMessage("DEBUG:".$this->PosToStr($this->now));
                            //Server::getInstance()->getLogger()->info("DEBUG:" . $this->PosToStr($this->now));
                            if ($this->active && $this->level->getBlock($this->now)->getId() == $this->search->getId() && (empty($this->getOption("compare_meta")) || $this->level->getBlock($this->now)->getDamage() == $this->search->getDamage())) {
                                array_push($this->blocks, [$this->PosToStr($this->now), $this->BlockToStr($this->level->getBlock($this->now))]);
                                $this->level->setBlock($this->now, $this->place);
                                $this->nextPos($this->getOption('block_interval', 'int'));
                                break;
                            }
                        } while ($this->active && $this->nextPos($this->getOption('block_interval', 'int')));
                        if(!$this->active) break;
                    }
                }
                break;

            case 'cut':
                if (empty($this->active)) return;
                for ($count = 1; $count <= $this->getOption('per_tick', 'int'); $count++) {
                    if ($this->active) {
                        array_push($this->blocks, [$this->PosToStr($this->now), $this->BlockToStr($this->level->getBlock($this->now))]);
                        $this->level->setBlock($this->now, Block::get(0));
                    }
                    if (!$this->nextPos($this->getOption('block_interval', 'int'))) {
                        break;
                    }
                }
                break;

            case 'undo':
                if (empty($this->active)) return;
                for ($count = 1; $count <= $this->getOption('per_tick', 'int'); $count++) {
                    if (!empty($this->blockdata[$this->index])) {
                        $data = $this->blockdata[$this->index++];
                        array_push($this->blocks, [$data[0], $this->BlockToStr($this->level->getBlock($this->StrToPos($data[0])))]);
                        $this->level->setBlock($this->StrToPos($data[0]), $this->StrToBlock($data[1]));
                    } else {
                        $this->active = false;
                        Server::getInstance()->getPluginManager()->getPlugin("BlockEditor")->getScheduler()->cancelTask($this->getTaskId());
                    }
                }
            break;

            default:
                Server::getInstance()->getLogger()->info("Invaild mode.");
                break;
        }
    }

    public function onCancel()
    {
        Server::getInstance()->broadcastMessage($this->player->getName() . "'s §b" . $this->getMode() . "§f(TaskID: §c" . $this->getTaskId() . "§f) " . (!$this->active ? "finished." : "cancelled."));
        $this->active = false;
    }

    private function nextPos(Int $interval = 1) : bool
    {
        if(!$this->active) return false;
        if($this->getOption('random')){
            if($interval < -100000 || 100000 > $interval){
                $interval = 1;
            }
            $rand = rand(0, max($interval + 25, -(5 * $interval + 63)));;
            if($rand < max($interval + 15, 1)) {
                $this->nextPos($interval);
            }
        }
        ($this->start->z == $this->min->z) ? $this->now->z++: $this->now->z--;

        //$this->max->z < $this->now->z || $this->now->z < $this->min->z 
        //($this->start->z == $this->min->z) ? $this->now->z > $this->end->z : $this->now->z < $this->end->z

        if ($this->max->z < $this->now->z || $this->now->z < $this->min->z) {
            $this->now->z = $this->start->z;
            ($this->start->y == $this->min->y) ? $this->now->y++ : $this->now->y--;
        }

        if ($this->max->y < $this->now->y || $this->now->y < $this->min->y) {
            $this->now->y = $this->start->y;
            ($this->start->x == $this->min->x) ? $this->now->x++ : $this->now->x--;
        }

        if ($this->max->x < $this->now->x || $this->now->x < $this->min->x) {
            $this->active = false;
            Server::getInstance()->getPluginManager()->getPlugin("BlockEditor")->getScheduler()->cancelTask($this->getTaskId());
            $this->now->x = $this->start->x;
            $this->now->y = $this->start->y;
            $this->now->z = $this->start->z;
            return false;
        }

        return ($interval > 1 && !$this->getOption('random')) ? $this->nextPos($interval - 1) : true;
    }

    private function debug_log(string $data)
    {
        if (false) {
            $filename = Server::getInstance()->getPluginManager()->getPlugin("BlockEditor")->getDataFolder() . "/BlockEditor.log";
            if (!file_exists($filename)) {
                touch($filename);
            }
            $log = file_get_contents($filename);
            $log .= date("Y/m/d H:i:s ") . substr($t = microtime(), strrpos($t, ' ') + 1, 6) . ' ' . $data . " Info: x:" . $this->now->x . " y:" . $this->now->y . " z:" . $this->now->z . "\n";
            file_put_contents($filename, $log);
        }
    }

    private function PosToStr(Vector3 $pos)
    {
        return implode(":", [$pos->x, $pos->y, $pos->z]);
    }

    private function BlockToStr(Block $block)
    {
        return implode(":", [$block->getId(), $block->getDamage()]);
    }

    private function StrToPos(string $pos) : Vector3
    {
        $pos_ar = explode(":", $pos);
        return new Vector3(@($pos_ar[0]) ? : 0, @($pos_ar[1]) ? : 0, @($pos_ar[2]) ? : 0);
    }

    private function StrToBlock(string $id_meta) : Block
    {
        $id_meta_ar = explode(":", $id_meta);
        return Block::get((int)@($id_meta_ar[0]) ? : 0, (int)@($id_meta_ar[1]) ? : 0);
    }

    public function getOption(string $name, string $cast = "")
    {
        switch (strtolower($cast)) {
            case 'string':
                if (empty($this->options[$name])) return '';
                return strval($this->options[$name]);
                break;

            case 'int':
                if (empty($this->options[$name])) return 1;
                return is_numeric($this->options[$name]) ? intval($this->options[$name]) : intval($this->default[$name]);
            
            default:
                if(empty($this->options[$name])) return false;
                return $this->options[$name];
                break;
        }

        return false;
    }

    public function setOption(string $name, $value) : bool
    {
        $this->options[$name] = $value;
        return true;
    }
    public function setOptions(array $args) : array
    {
        $options = [];
        foreach ($args as $index => $value) {
            if (empty($require_val_f) && preg_match("/^-([A-Za-z])$/", $value, $result)) {
                $optionName = @($result[1]) ? : '';
                //ここをコンフィグで設定可能に

                switch ($optionName) {
                    case 'n':
                    case 'i':
                        $require_val_f = true;
                        break;

                    case 'r':
                        $this->setOption('random', true);
                    case 't':
                    case 's':
                        $require_val = true;
                        break;

                    default:
                        $this->player->sendMessage("§6Unknown option -$optionName");
                        break;
                }
            } elseif (empty($require_val_f) && preg_match("/^--([A-Za-z0-9_]+)$/", $value, $result)) {
                $optionName = @($result[1]) ? : '';
                switch ($optionName) {
                    case 's':
                    case 't':
                    case 'n':
                    case 'i':
                        $require_val_f = true;
                        break;

                    case 'r':
                        $this->setOption('random', true);
                        break;

                    default:
                        $this->player->sendMessage("§6Unknown option -$optionName");
                        break;
                }
            } elseif (!empty($require_val_f) || !empty($require_val)) {
                switch ($optionName) {
                    case 's':
                        switch (@($value) ? : 'normal') {
                            //コンフィグで設定可能に
                            
                            case 'slowest':
                                $tick = 15;
                                $per_tick = 1;
                            case 'slower':
                                $tick = 2;
                                $per_tick = 1;
                                break;
                            case 'slow':
                                $tick = 2;
                                $per_tick = 1;
                                break;
                            case 'standard':
                                $tick = 1;
                                $per_tick = 10;
                                break;
                            case 'fast':
                                $tick = 5;
                                $per_tick = 2048;
                                break;
                            case 'faster':
                                $tick = 5;
                                $per_tick = 4096;
                                break;
                            case 'fastest':
                                $tick = 10;
                                $per_tick = 16384;
                                break;
                            case 'normal':
                            default:
                                $tick = 5;
                                $per_tick = 1024;
                                break;
                        }

                        $this->setOption("per_tick", $per_tick);
                        $this->setOption("tick_interval", $tick);
                        $options["per_tick"] = $per_tick;
                        $options["tick_interval"] = $tick;
                        break;

                    case 't':
                        $this->setOption("tick_interval", $tick = intval((1 <= $value && $value <= 20) ? $value : 5));
                        $options["tick_interval"] = $tick;
                        break;

                    case 'n':
                        $this->setOption("per_tick", $per_tick = intval((1 <= $value) ? $value : 1024));
                        $options["per_tick"] = $per_tick;
                        break;

                    case 'i':
                        $this->setOption("block_interval", $interval = intval((1 <= $value) ? $value : 1));
                        $options["block_interval"] = $interval;
                        break;
                }
                $require_val_f = false;
            } else {
                $this->player->sendMessage("§6Unknown value $value");
            }
        }
        return $options;
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function getBlock(bool $search = false)
    {
        return ($search) ? $this->search : $this->place;
    }

    public function setBlock(Block $block, bool $search = false)
    {
        ($search) ? $this->search = $block : $this->place = $block;
        return true;
    }

    public function getPlayer() : Player
    {
        return $this->player;
    }

    public function setPlayer(Player $player)
    {
        $this->player = $player;
    }

    public function getMode()
    {
        switch ($this->mode) {
            case 0:
                return 'set';
                break;
            
            case 1:
                return 'replace';
                break;

            case 2:
                return 'cut';
                break;

            case 3:
                return 'undo';
                break;
        }
        return 'undefined';
    }

    public function setMode(String $mode) : bool
    {
        switch ($mode) {
            case 'set':
                $this->mode = 0;
                break;

            case 'replace':
                $this->mode = 1;
                break;

            case 'cut':
                $this->mode = 2;
                break;

            case 'undo':
                $this->mode = 3;
                break;
            
            default:
                return false;
                break;
        }
        return true;
    }

    public function autoMode(bool $undo = false){

        $this->setMode('replace');
        if(empty($this->search)) $this->setMode('set');
        if(empty($this->place)) $this->setMode('cut');
        if($undo) $this->setMode('undo');

    }

    public function undo(array $args) : BlockEditTask
    {
        Server::getInstance()->getPluginManager()->getPlugin("BlockEditor")->getScheduler()->cancelTask($this->getTaskId());
        $this->setMode('undo');
        //初期化
        $this->active = true;
        $this->index = 0;
        $this->blockdata = $this->blocks;
        $this->blocks = [];

        $options = $this->setOptions($args, $this);
        Server::getInstance()->getPluginManager()->getPlugin("BlockEditor")->getScheduler()->scheduleRepeatingTask($this, $this->getOption("tick_interval", 'int'));
        return $this;
    }

    public function redo(array $args) : BlockEditTask
    {
        $task = new BlockEditTask($this->player, $this->start, $this->end, $this->place, $this->search, $this->options);
        $task->setOptions($args);

        Server::getInstance()->getPluginManager()->getPlugin("BlockEditor")->getScheduler()->scheduleRepeatingTask($task, $task->getOption("tick_interval", 'int'));
        return $task;
    }

    public function clear(){
        $this->blocks = [];
        $this->blockdata = [];
    }
}
