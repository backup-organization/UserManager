<?php

declare(strict_types=1);

namespace xenialdan\UserManager\listener;

use pocketmine\event\Listener;
use pocketmine\utils\TextFormat;
use xenialdan\UserManager\MuteStore;
use xenialdan\UserManager\event\UserMuteEvent;
use pocketmine\event\player\PlayerChatEvent;
use xenialdan\UserManager\Loader;
use xenialdan\UserManager\models\Mute;
use xenialdan\UserManager\User;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use xenialdan\UserManager\UserStore;

class MuteListener implements Listener
{
    private const WHITELISTED = [
        "/me",
        "/say",
        "/tell",
        "/message",
        "/msg",
        "/broadcast",
        "/bc",
        "/reply",
        "/whisper"
    ];
     /**
     * @priority NORMAL
     * @param PlayerCommandPreprocessEvent $event
     *
     */
    public function onPlayerCommandPreprocess(PlayerCommandPreprocessEvent $event): void {
        $player = $event->getPlayer();
        if(strpos($event->getMessage(), "/") !== 0) {
            return;
        }
        if(in_array(explode(" ", $event->getMessage())[0], self::WHITELISTED)) {
            return;
        }
        if (($user = UserStore::getUser($player = $event->getPlayer())) instanceof User) {
            $ban = MuteStore::getMuteById($user->getId());
            if($ban instanceof Mute){
            if (!$ban->hasExpired()) {
            $player->sendMessage(TextFormat::colorize("&cYou cannot use this command whilst muted."));
            $event->setCancelled();
            }else{
                Loader::$queries->deleteMute($ban, function (int $affectedRows) use ($ban): void {
                    Loader::getInstance()->getLogger()->notice("Removed mute " . $ban . " ($affectedRows)");
                    unset($ban);
                });
            }
        }
        }
    }
    /**
     * @priority HIGHEST
     * @param PlayerChatEvent $event
     */
    public function onUserChat(PlayerChatEvent $event): void//TODO CHANGE AGAIN to loginevent?
    {
        if (($user = UserStore::getUser($player = $event->getPlayer())) instanceof User) {
        $player = $user->getPlayer();
        $ban = MuteStore::getMuteById($user->getId());
        if ($ban instanceof Mute) {
            if ($ban->hasExpired()) {
                Loader::$queries->deleteMute($ban, function (int $affectedRows) use ($ban): void {
                    Loader::getInstance()->getLogger()->notice("Removed mute " . $ban . " ($affectedRows)");
                    unset($ban);
                });
                return;
            }
            $msg = TextFormat::DARK_RED . TextFormat::BOLD . "You are muted!";
            $msg .= TextFormat::RED . "\nMuted by: " . TextFormat::GRAY . $ban->by;
            $msg .= TextFormat::RED . "\nReason: " . TextFormat::GRAY . $ban->reason;
            $msg .= TextFormat::RED . "\nMuted since: " . TextFormat::GRAY . strftime("%c", $ban->getSince());
        if(strftime("%c", $ban->getSince()) === strftime("%c", $ban->getUntil())){
            $expiry = "Forever";
        }else{
            $expiry = strftime("%c", $ban->getUntil());
        }
            $msg .= TextFormat::RED . "\nMuted Until: " . TextFormat::GRAY . $expiry;
            $debug = "Muted user tried to chat:" . TextFormat::EOL . $ban;
            $kick = false;
            if ($ban->isTypeMuted(Mute::TYPE_IP) && $user->getIP() === $player->getAddress()) {
                $kick = true;
            }
            if ($ban->isTypeMuted(Mute::TYPE_NAME) && $user->getIUsername() === $player->getLowerCaseName()) {
                $kick = true;
            }
            //TODO UUID, XUID
            if ($kick) {
                //TODO check why kick message does not appear + stuck in loading resources
                Loader::getInstance()->getLogger()->debug($debug);
                #$player->kick($msg, false);
                #$event->setKickMessage($msg);
                #$event->setCancelled();
                $event->getPlayer()->sendMessage($msg);
                $event->setCancelled(true);
            return;
            }
        }
        }
    }

    public function onUserMuteEvent(UserMuteEvent $event): void
    {
        if (!$event->isCancelled()) {
            if ($event->getUser()->isOnline()) {
                $ban = $event->getMute();
                $msg = TextFormat::DARK_RED . TextFormat::BOLD . "You are muted!";
                $msg .= TextFormat::RED . "\nMuted by: " . TextFormat::GRAY . $ban->by;
                $msg .= TextFormat::RED . "\nReason: " . TextFormat::GRAY . $ban->reason;
                $msg .= TextFormat::RED . "\nMuted since: " . TextFormat::GRAY . strftime("%c", $ban->getSince());
            if(strftime("%c", $ban->getSince()) === strftime("%c", $ban->getUntil())){
                $expiry = "Forever";
            }else{
                $expiry = strftime("%c", $ban->getUntil());
            }
                $msg .= TextFormat::RED . "\nMuted Until: " . TextFormat::GRAY . $expiry;
                $debug = "Muted user tried to chat:" . TextFormat::EOL . $ban;
                $kick = false;
                if ($ban->isTypeMuted(Mute::TYPE_IP) && $user->getIP() === $player->getAddress()) {
                    $kick = true;
                }
                if ($ban->isTypeMuted(Mute::TYPE_NAME) && $user->getIUsername() === $player->getLowerCaseName()) {
                    $kick = true;
                }
                //TODO UUID, XUID
                if ($kick) {
                    //TODO check why kick message does not appear + stuck in loading resources
                    Loader::getInstance()->getLogger()->debug($debug);
                    #$player->kick($msg, false);
                    #$event->setKickMessage($msg);
                    #$event->setCancelled();
                    $event->getPlayer()->sendMessage($msg);
                }
            }
        }
    }
}
