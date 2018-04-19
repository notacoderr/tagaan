<?php

namespace Minifixio\onevsone\command;

use pocketmine\command\Command;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\command\CommandSender;
use pocketmine\level\Level;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

use Minifixio\onevsone\OneVsOne;
use Minifixio\onevsone\ArenaManager;

class JoinCommand extends Command implements PluginIdentifiableCommand{

	private $plugin;
	private $arenaManager;
	public $commandName = "match";

	public function __construct(OneVsOne $plugin, ArenaManager $arenaManager){
		parent::__construct($this->commandName, "Join 1vs1 queue !");
		$this->setUsage("/$this->commandName");
		
		$this->plugin = $plugin;
		$this->arenaManager = $arenaManager;
	}

	public function getPlugin() : Plugin{
		return $this->plugin;
	}

	public function execute(CommandSender $sender, string $label, array $params) : bool{
		if(!$this->plugin->isEnabled()){
			return false;
		}

		if(!$sender instanceof Player){
			$sender->sendMessage("Please use the command in-game");
			return true;
		}
		
		//$this->arenaManager->addNewPlayerToQueue($sender);
		$this->confirmForm($sender);
		return true;
	}
	
	public function confirmForm(Player $player) {
        $form = Server::getInstance()->getPluginManager()->getPlugin("FormAPI")->createSimpleForm(function (Player $player, array $data)
		{
            if (isset($data[0]))
			{
                $button = $data[0];
                switch ($button)
				{
					case 0: return $this->arenaManager->addNewPlayerToQueue($player);
					break;
					case 1: return;
					break;
				}
            }
        });
		$form->setTitle("§l§0[§c1 vs 1§0]§7 - §fP§bC§fP");
		$s = "";
		$s .= "§f§l• §bGuidelines to be followed§f •§r\n";
		$s .= "§l§f-§a Inventory will be wiped before and after the match.§r\n";
		$s .= "§l§f-§a Disconnecting means defeat§r\n";
		$s .= "§l§f-§a Teleporting results to Temporary Ban§r\n";
		$s .= "§l§f-§a Cheating results to Permanent Ban§r\n";
        $form->setContent($s);
		
        $form->addButton("§lConfirm", 1, "https://cdn1.iconfinder.com/data/icons/unigrid-bluetone-military/60/002_022_military_battle_attack_swords-128.png");
		$form->addButton("§lCancel", 1, "https://cdn1.iconfinder.com/data/icons/materia-arrows-symbols-vol-8/24/018_317_door_exit_logout-128.png");
        $form->sendToPlayer($player);
    }
}