<?php

namespace Minifixio\onevsone\model;

use Minifixio\onevsone\OneVsOne;
use Minifixio\onevsone\utils\PluginUtils;

use pocketmine\Player;
use pocketmine\Server;
use pocketmine\level\Position;
use pocketmine\item\Item;
use pocketmine\utils\TextFormat;
use pocketmine\entity\Effect;
use pocketmine\entity\InstantEffect;
use pocketmine\math\Vector3;
use pocketmine\level\particle\SmokeParticle;
use pocketmine\block\Block;
use pocketmine\level\particle\DestroyBlockParticle;
use pocketmine\level\Location;

use \DateTime;
use Minifixio\onevsone\ArenaManager;

class Arena{

	public $active = FALSE;
	
	public $startTime;
	
	public $players = array();
	
	/** @var Position */
	public $position;
	
	/** @var ArenaManager */
	private $manager;
	
	// Roound duration (3min)
	const ROUND_DURATION = 180;
	
	const PLAYER_1_OFFSET_X = 8;
	const PLAYER_2_OFFSET_X = -8;
	
	// Variable for stop the round's timer
	private $taskHandler;
	private $countdownTaskHandler;

	/**
	 * Build a new Arena
	 * @param Position position Base position of the Arena
	 */
	public function __construct($position, ArenaManager $manager){
		$this->position = $position;
		$this->manager = $manager;
		$this->active = FALSE;
	}
	
	/** 
	 * Demarre un match.
	 * @param Player[] $players
	 */
	public function startRound(array $players){
		
		// Set active to prevent new players
		$this->active = TRUE;
		
		// Set players
		$this->players = $players;
		$player1 = $players[0];
		$player2 = $players[1];
		
		$player1->sendMessage(OneVsOne::getMessage("duel_against") . $player2->getName());
		$player2->sendMessage(OneVsOne::getMessage("duel_against") . $player1->getName());

		// Create a new countdowntask
		$task = new CountDownToDuelTask(OneVsOne::getInstance(), $this);
		$this->countdownTaskHandler = Server::getInstance()->getScheduler()->scheduleDelayedRepeatingTask($task, 20, 20);	
	}
	
	/**
	 * Really starts the duel after countdown
	 */
	public function startDuel(){
		
		Server::getInstance()->getScheduler()->cancelTask($this->countdownTaskHandler->getTaskId());
		
		$player1 = $this->players[0];
		$player2 = $this->players[1];
		
		$pos_player1 = Position::fromObject($this->position, $this->position->getLevel());
		$pos_player1->x += self::PLAYER_1_OFFSET_X;
		
		$pos_player2 = Position::fromObject($this->position, $this->position->getLevel());
		$pos_player2->x += self::PLAYER_2_OFFSET_X;
		
		$player1->teleport($pos_player1, 90, 0);
		$player2->teleport($pos_player2, -90, 0);
		
		$this->sparyParticle($player1);
		$this->sparyParticle($player2);
		
		$player1->setGamemode(2);
		$player2->setGamemode(2);
		
		// Give kit
		foreach ($this->players as $player){
			$this->giveKit($player);
		}
		
		// Fix start time
		$this->startTime = new DateTime('now');
		
		$player1->sendTip(OneVsOne::getMessage("duel_tip"));
		$player1->sendMessage(OneVsOne::getMessage("duel_start"));
		
		$player2->sendTip(OneVsOne::getMessage("duel_tip"));
		$player2->sendMessage(OneVsOne::getMessage("duel_start"));
		
		// Launch the end round task
		$task = new RoundCheckTask(OneVsOne::getInstance());
		$task->arena = $this;
		$this->taskHandler = Server::getInstance()->getScheduler()->scheduleDelayedTask($task, self::ROUND_DURATION * 20);
	}
	
	/**
	 * Abort duel during countdown if one of the players has quit
	 */
	public function abortDuel(){
		Server::getInstance()->getScheduler()->cancelTask($this->countdownTaskHandler->getTaskId());
	}
	
	private function giveKit(Player $player){

		$player->getInventory()->clearAll();
		$player->removeAllEffects();
		$player->setGamemode(2);
		$player->setHealth(20);
		$player->setFood(20);
		
		$player->getInventory()->setItem(0, Item::get(267)); //i sword
		$player->getInventory()->setItem(1, Item::get(258)); //i axe
		$player->getInventory()->setItem(2, Item::get(261)); //Bow
		
		$player->getInventory()->setItem(4, Item::get(322, 0, 5)); //enchanted gold apple
		
		$player->getInventory()->setItem(7, Item::get(262, 0, 32)); //i axe
		$player->getInventory()->setItem(8, Item::get(262, 0, 32)); //i axe

		$player->getArmorInventory()->setHelmet( Item::get(310) );
		$player->getArmorInventory()->setChestplate( Item::get(311) );
		$player->getArmorInventory()->setLeggings( Item::get(312) );
		$player->getArmorInventory()->setBoots( Item::get(313) );
		$player->getArmorInventory()->sendContents( $player );
		
		$player->addTitle("§l§7[§41 vs 1§7]", "§7Godspeed and Goodluck");
   }
   
   public function onPlayerDeath(Player $loser) {
 
   		if($loser == $this->players[0]){
   			$winner = $this->players[1];
   		} else {
   			$winner = $this->players[0];
   		} 
		$loser->teleport( $loser->getSpawn() );
		//SEND THE FORM FIRST SO THAT EVERYTHING ELSE IS DONE ON BACKGROUND
		$hangal = Server::getInstance()->getPluginManager()->getPlugin("CoolCrates")->getSessionManager()->getSession($winner);
		$hangal->addCrateKey("common.crate", 2);
		
		$plr = strtolower( $winner->getName() );
		$xp = mt_rand(27, 42); $rp = mt_rand(18, 22);
		
		Server::getInstance()->getPluginManager()->getPlugin("LevelAPI")->addVal($plr, "exp", $xp);
		Server::getInstance()->getPluginManager()->getPlugin("LevelAPI")->addVal($plr, "respect", $rp);
		
		$this->resultForm($winner, $xp, $rp, 2);
		//FORM SHITS END HERE
   		$loser->removeAllEffects();
		$gm = strtolower($loser->getName());
   		Server::getInstance()->getPluginManager()->getPlugin("LevelAPI")->addVal($gm, "respect", -18);
		
		$winner->removeAllEffects();
   		$winner->teleport( $winner->getSpawn() );

   		Server::getInstance()->broadcastMessage(TextFormat::GREEN . TextFormat::BOLD . "» " . TextFormat::GOLD . $winner->getName() . TextFormat::WHITE . OneVsOne::getMessage("duel_broadcast") . TextFormat::RED . $loser->getName() . TextFormat::WHITE . " !");
   		
   		$this->reset();
	}
   
    public function resultForm(Player $player, int $xp,int $rp,int $key)
    {
        $form = Server::getInstance()->getPluginManager()->getPlugin("FormAPI")->createSimpleForm(function (Player $player, array $data)
		{
            if (isset($data[0]))
			{
                $button = $data[0];
                switch ($button)
				{
					case 0: $this->manager->addNewPlayerToQueue($player);

					break;
					case 1: Server::getInstance()->dispatchCommand($player, "top");
					
					break;
					
					default: $player->getInventory()->clearAll();
				}
				return true;
            }
        });
		$form->setTitle(" §l§0[§c1 vs 1§0]§7 Post battle result");
		
		$levelapi = Server::getInstance()->getPluginManager()->getPlugin("LevelAPI");
		$rank = $levelapi->getVal($player->getName(), "rank");
		$div = $levelapi->getVal($player->getName(), "div");
		$resp = $levelapi->getVal($player->getName(), "respect");
		$s = "";
		$s .= "§l§f• Experience points: +§a".$xp."§r\n";
		$s .= "§l§f• Respect points: +§c". $rp. "§r\n";
		$s .= "§l§f• Bonus: +§e". $key. "§f common crate keys§r\n";
		$s .= "§l§f• Current ELO: §b".$rank." ".$div." §f| RP: §7[§c".$resp."§7] §f•§r\n";
		$s .= "§r\n";
        $form->setContent($s);
		
		$form->addButton("§lMatch again", 1, "https://cdn1.iconfinder.com/data/icons/unigrid-bluetone-military/60/002_022_military_battle_attack_swords-128.png");
        $form->addButton("§lCheck Rankings", 1, "https://cdn4.iconfinder.com/data/icons/we-re-the-best/512/best-badge-cup-gold-medal-game-win-winner-gamification-first-award-acknowledge-acknowledgement-prize-victory-reward-conquest-premium-rank-ranking-gold-hero-star-quality-challenge-trophy-praise-victory-success-128.png");
		$form->addButton("§lGG I Quit", 1, "https://cdn1.iconfinder.com/data/icons/materia-arrows-symbols-vol-8/24/018_317_door_exit_logout-128.png");
		$form->sendToPlayer($player);
    }

   /**
    * Reset the Arena to current state
    */
   private function reset(){
   		// Put active a rena after the duel
   		$this->active = FALSE;
   		foreach ($this->players as $winner) { //winner is just a variable
		
   			$winner->getInventory()->clearAll();
			$winner->getInventory()->setItemInHand( Item::get(0) );
			$winner->getArmorInventory()->setHelmet( Item::get(0) );
			$winner->getArmorInventory()->setChestplate( Item::get(0) );
			$winner->getArmorInventory()->setLeggings( Item::get(0) );
			$winner->getArmorInventory()->setBoots( Item::get(0) );
			$winner->getArmorInventory()->sendContents( $winner );
			
			$winner->setHealth(20); 
			$winner->setFood(20);
			$winner->setGamemode(2);
			
   		}
   		$this->players = array();
   		$this->startTime = NULL;
   		if($this->taskHandler != NULL){
   			Server::getInstance()->getScheduler()->cancelTask($this->taskHandler->getTaskId());
   			$this->manager->notifyEndOfRound($this);
   		}
   }
   
   /**
    * When a player quit the game
    * @param Player $loser
    */
   public function onPlayerQuit(Player $loser){
   		// Finish the duel when a player quit
   		// With onPlayerDeath() function
   		$this->onPlayerDeath($loser);
   }
   
   /**
    * When maximum round time is reached
    */
   public function onRoundEnd(){
   		foreach ($this->players as $player){
   			$player->teleport($player->getSpawn());
   			$player->sendMessage(TextFormat::BOLD . "++++++++•++++++++");
   			$player->sendMessage(OneVsOne::getMessage("duel_timeover"));
   			$player->sendMessage(TextFormat::BOLD . "++++++++•++++++++");
   			$player->removeAllEffects();
   		}
   		
   		// Reset arena
   		$this->reset();   		
	 }
	 
	 public function isPlayerInArena(Player $player){
	 	return in_array($player, $this->players);
	 }
	 
	 public function sparyParticle(Player $player){
		$particle = new DestroyBlockParticle(new Vector3($player->getX(), $player->getY(), $player->getZ()), Block::get(8));
	 	$player->getLevel()->addParticle($particle);
    }
}



