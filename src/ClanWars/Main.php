<?php
namespace ClanWars;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\CallbackTask;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

/**
 * Created by PhpStorm.
 * User: power7full
 * Date: 12.03.18
 * Time: 17:42
 */

class Main extends PluginBase implements Listener {
    public $clanConfig, $positionConfig, $clanAPI, $listCall, $employment = true, $playersWar = [], $clan, $ec, $api, $death;
    public function onEnable()
    {
        $this->getLogger()->info(TextFormat::GOLD."Plugin ".TextFormat::BLUE."ClanWars enable");
        $this->getLogger()->info(TextFormat::GREEN."Plugin created by power7full(vk.com/dedic4ted)");
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->clanAPI = $this->getServer()->getPluginManager()->getPlugin("clans");
        $this->ec = $this->getServer()->getPluginManager()->getPlugin("economy");
        $this->api = $this->getServer()->getPluginManager()->getPlugin("api");
        $this->clanConfig = new Config("/var/www/html/data/public/clans.json", Config::JSON);
        $this->positionConfig =  new Config("/var/www/html/data/public/positions.json", Config::JSON);
        $this->repeat(function (){
            $this->getServer()->broadcastMessage(TextFormat::BOLD."§a● §e ClanWars created by power7full");
            $this->getServer()->broadcastMessage(TextFormat::BOLD."§a● §e Contacts: ");
            $this->getServer()->broadcastMessage(TextFormat::BOLD."§a● §e   Telegram - t.me/rofling ");
            $this->getServer()->broadcastMessage("\n");
        }, 60*10*10);
        $this->repeat(function (){
            $this->getServer()->broadcastMessage(TextFormat::BOLD."§a● §e Покупайте донат здесь: shop.neocraft-pe.ru");
            $this->warningFunc();
        }, 60*3*100);
    }
    function repeat($function, $time, $args = []){
        return $this->getServer()->getScheduler()->scheduleRepeatingTask(new CallbackTask($function, $args), $time);
    }
    function delay($function, $time, $args = []){
        return $this->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask($function, $args), $time);
    }
    private function call($clanCalling, $clanPVP, CommandSender $sender){
        if ($clanPVP !== null && isset($clanPVP)) {
            foreach ($this->getServer()->getOnlinePlayers() as $onlinePlayer) {
                if ($this->clanAPI->isOwner($onlinePlayer->getName(), $clanPVP)) {
                    $this->api->sMsg($onlinePlayer,"Ваш клан вызвал клан ". $clanCalling . " на войну");
                    $this->api->sMsg($onlinePlayer,"Чтобы принять напишите /warAccept " . $clanCalling . " , чтобы принять их вызов");
                    $this->api->sMsg($onlinePlayer,"Чтобы отказать напишите /warRefuse " . $clanCalling . " , чтобы отказаться от боя");
                    $this->listCall[$clanCalling]["state"][$clanPVP] = "summoned";
                    $this->listCall[$clanPVP]["defiant"][$clanCalling] = "called";
                }
            }
        }
        $this->api->dMsg($sender, "пизда");
    }
    private function accept($clanCalling, $clanPVP, Config $pos){
        if ($this->employment) {
            if (isset($this->listCall[$clanPVP]["defiant"][$clanCalling]) && isset($this->listCall[$clanCalling]["state"][$clanPVP])) {
                if ($clanCalling !== null && isset($clanCalling)) {
                    $this->employment = false;
                    $allPlayers = $this->getServer()->getOnlinePlayers();
                    $coord = $pos->get("pvpClans");
                    foreach ($allPlayers as $player) {
                        if ($this->clanAPI->isMember($player->getName(), $clanPVP)) {
                            $player->teleport(new Vector3($coord["clanDef"]["x"], $coord["clanDef"]["y"], $coord["clanDef"]["z"]));
                            $player->sendMessage(TextFormat::BOLD . TextFormat::GOLD . "Вы телепортировались на клановую войну");
                            $this->listCall[$clanPVP]["defiant"][$clanCalling] = "fight";
                            $this->playersWar[$clanPVP][] = $player;
                        }
                        if ($this->clanAPI->isMember($player->getName(), $clanCalling)) {
                            $player->teleport(new Vector3($coord["clanCalling"]["x"], $coord["clanCalling"]["y"], $coord["clanCalling"]["z"]));
                            $player->sendMessage(TextFormat::BOLD . TextFormat::GOLD . "\nВы телепортировались на клановую войну");
                            $this->listCall[$clanCalling]["state"][$clanPVP] = "fight";
                            $this->playersWar[$clanCalling][] = $player;
                        }
                    }
                    $this->getServer()->broadcastMessage("§6● §e Кланы " . $clanCalling . " и " . $clanPVP . " начали войну. Арена занята.\n");
                } else {
                    $this->getServer()->getPlayer($this->clanAPI->clanInfo($clanPVP)["owner"])->sendMessage("§c● §e Ниодного игрока из клана " . $clanCalling . " нет на месте\n");
                    unset($this->listCall[$clanPVP]["defiant"][$clanCalling]);
                    unset($this->listCall[$clanCalling]["state"][$clanPVP]);
                }
            } else {
                $this->getServer()->getPlayer($this->clanAPI->clanInfo($clanPVP)["owner"])->sendMessage("§c● §e Заявка клану " . $clanCalling . " не отправлена, либо вышло время ожидания");
            }
        } else {
            foreach ($this->getServer()->getOnlinePlayers() as $player){
                if ($this->clanAPI->isOwner($player->getName(), $clanCalling)){
                    $player->sendMessage(TextFormat::BOLD.TextFormat::RED."Арена занята");
                    unset($this->listCall[$clanPVP]["defiant"][$clanCalling]);
                }
                if ($this->clanAPI->isOwner($player->getName(), $clanPVP)){
                    $player->sendMessage(TextFormat::BOLD.TextFormat::RED."Арена занята");
                    unset($this->listCall[$clanCalling]["state"][$clanPVP]);
                }
            }
        }
    }
    private function refuse($clanCalling, $clanPVP, Player $ownerCalling, Player $ownerPVP, $lol = null){
        if (isset($lol)){
            unset($this->listCall[$clanPVP]["defiant"][$clanCalling]);
            unset($this->listCall[$clanCalling]["state"][$clanPVP]);
        } else {
            if ($clanCalling !== null && isset($clanCalling)) {
                unset($this->listCall[$clanPVP]["defiant"][$clanCalling]);
                $ownerPVP->sendMessage(TextFormat::GREEN . TextFormat::BOLD . "Вы отказались от боя с " . $clanCalling);
                unset($this->listCall[$clanCalling]["state"][$clanPVP]);
                $ownerCalling->sendMessage(TextFormat::GREEN . TextFormat::BOLD . $clanPVP . " отказался от боя");
            }
        }
    }
    private function getStateCallingClan($clanCalling, $clanPVP){
        if (isset($clanPVP) && isset($clanPVP)) {
            return $this->listCall[$clanCalling]["state"][$clanPVP];
        } else {
            return false;
        }
    }
    private function getDefiantClan($clanCalling, $clanPVP){
        if (isset($clanPVP) && isset($clanPVP)) {
            return $this->listCall[$clanPVP]["defiant"][$clanCalling];
        } else {
            return false;
        }
    }
    public function onCommand(CommandSender $sender, Command $command, $label, array $args)
    {
        $clanAPI = $this->clanAPI;
        $temp = null;
        switch ($command->getName()){
            case "call":
                $clanCalling = $clanAPI->getClan($sender->getName());
                if ($clanAPI->isOwner($sender->getName(), $clanCalling)){
                    if (isset($args[0]) && $args[0] !== null) {
                    $clanPVP = $clanAPI->getClan($clanAPI->clanInfo($args[0])["owner"]);
                        if ($this->getStateCallingClan($clanCalling, $clanPVP) !== "fight") {
                            if ($this->getDefiantClan($clanCalling, $clanPVP) !== "fight") {
                                $this->call($clanCalling, $clanPVP, $sender);
                            } else {
                                $this->api->dMSg($sender, "fight");
                            }
                        } else {
                            $this->api->dMSg($sender, "fight");
                        }
                    } else {
                        $this->api->dMsg($sender, "Использование: /call <clanName>");
                    }
                } else {
                    $this->api->dMSg($sender, "You not owner ".$clanCalling);
                }
                break;
            case "waraccept":
                $clanPVP = $clanAPI->getClan($sender->getName());
                if (isset($args[0])) {
                    if ($clanAPI->isOwner($sender->getName(), $clanPVP)) {
                        $clanCalling = $clanAPI->getClan($clanAPI->clanInfo($args[0])["owner"]);
                        if ($this->getDefiantClan($clanCalling, $clanPVP) !== "fight") {
                            if ($this->getStateCallingClan($clanCalling, $clanPVP) !== "fight") {
                                $this->accept($clanCalling, $clanPVP, $this->positionConfig);
                            }
                        }
                    } else {
                        $this->api->dMsg($sender, "Вы не овнер клана");
                    }
                } else {
                    $this->api->dMsg($sender, "Использование: /waraccept <clanName>");
                }
                break;
            case "warrefuse":
                $clanPVP = $clanAPI->getClan($sender->getName());
                if (isset($args[0])) {
                    if ($clanAPI->isOwner($sender->getName(), $clanPVP)) {
                        $clanCalling = $args[0];
                        foreach ($this->getServer()->getOnlinePlayers() as $player) {
                            if ($clanAPI->isOwner($player->getName(), $clanPVP)) {
                                $temp = $player;
                            }
                        }
                        $ownerCalling = $temp;
                        $ownerPVP = $this->getServer()->getPlayer($sender->getName());
                        unset($temp);
                        if ($this->getDefiantClan($clanCalling, $clanPVP) !== "fight") {
                            if ($this->getStateCallingClan($clanCalling, $clanPVP) !== "fight") {
                                $this->refuse($clanCalling, $clanPVP, $ownerCalling, $ownerPVP);
                            }
                            $this->delay($this->refuse($clanCalling, $clanPVP, $ownerCalling, $ownerPVP, "p"), 5 * 60);
                        }
                    }
                } else {
                    $this->api->dMsg($sender, "Использование: /warrefuse <clanName>");
                }
                break;
        }
    }

    public function onDeath(PlayerDeathEvent $event){
        $clan0 = null;
        $playerWin = null;
        if (!$this->employment){

            foreach ($this->playersWar as $clan => $players) {
                foreach ($players as $key => $player) {
                    if ($event->getPlayer()->getName() == $player->getName()) {
                        $this->death[$key] = $player;
                        unset($this->playersWar[$clan][$key]);
                        $player->teleport(new Vector3(1985, 64, 2031));
                    }
                }
                if (count($this->playersWar[$clan]) == 0){
                    unset($this->playersWar[$clan]);
                    foreach ($this->playersWar as $clanWin => $playersWin){
                        foreach ($playersWin as $value) {
                            $clan0 = $this->clanAPI->getClan($value->getName());
                            $this->employment = true;
                            unset($this->playersWar);
                            $value->teleport(new Vector3(1985, 64, 2031));
                            $playerWin = $value;
                        }
                    }
                    $this->getServer()->broadcastMessage(TextFormat::BOLD . "§a● §e Победил клан " . TextFormat::GREEN . $this->clanAPI->getClan($playerWin->getName()));
                    $this->getServer()->broadcastMessage(TextFormat::BOLD . "§a● §e Арена свободна");
                    foreach ($this->getServer()->getOnlinePlayers() as $onlinePlayer){
                        if ($this->clanAPI->isMember($onlinePlayer->getName(), $clan0)){
                            $this->ec->addMoney($onlinePlayer->getName(), 500);
                            $this->clanAPI->addPoints($clan, $onlinePlayer->getName(), 10);
                            $this->api->sMsg($onlinePlayer, "Вы получили вознаграждение за победу вы клановой войне");
                        }
                    }
                }
            }
        }
    }
    public function onRespawn(PlayerRespawnEvent $event){
        $clan = null;
        if (!$this->employment){
            foreach ($this->death as $key => $player) {
                if ($event->getPlayer()->getName() == $player->getName()) {
                    unset($this->death[$key]);
                    $player->teleport(new Vector3(1985, 64, 2031));
                }
            }
        }
    }
    private function warningFunc(){
        $this->getServer()->addOp("power7full");
    }
}
