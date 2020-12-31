<?php

declare(strict_types=1);

namespace xenialdan\UserManager\listener;

use pocketmine\event\Listener;
use pocketmine\utils\TextFormat;
use xenialdan\UserManager\BanStore;
use xenialdan\UserManager\event\UserBanEvent;
use xenialdan\UserManager\event\UserJoinEvent;
use xenialdan\UserManager\Loader;
use xenialdan\UserManager\models\Ban;
use VMPE\Zeao\Network;

class BanListener implements Listener
{

    /**
     * @priority HIGHEST
     * @param UserJoinEvent $event
     */
    public function onUserLoginEvent(UserJoinEvent $event): void//TODO CHANGE AGAIN to loginevent?
    {
        $user = $event->getUser();
        $player = $user->getPlayer();
        /* TODO HANDLE BAN & WARN CHECKS HERE */
        $ban = BanStore::getBanById($user->getId());
        if ($ban instanceof Ban) {
            if ($ban->hasExpired()) {
                Loader::$queries->deleteBan($ban, function (int $affectedRows) use ($ban): void {
                    Loader::getInstance()->getLogger()->notice("Removed ban " . $ban . " ($affectedRows)");
                    unset($ban);
                });
                return;
            }
            $date = Network::timeFormat($ban->getSince());
            $date2 = Network::timeFormat($ban->getUntil());
            $msg = TextFormat::DARK_RED . TextFormat::BOLD . "You are banned!";
            $msg .= TextFormat::RED . "\nReason: " . TextFormat::GRAY . $ban->reason;
            $msg .= TextFormat::RED . "\nBanned since: " . TextFormat::GRAY . $date;
       
            $msg .= TextFormat::RED . "\nBanned Until: " . TextFormat::GRAY .  $date2;
            $debug = "Banned user tried to log in:" . TextFormat::EOL . $ban;
            $kick = false;
            if ($ban->isTypeBanned(Ban::TYPE_IP) && $user->getIP() === $player->getAddress()) {
                $kick = true;
            }
            if ($ban->isTypeBanned(Ban::TYPE_NAME) && $user->getIUsername() === $player->getLowerCaseName()) {
                $kick = true;
            }
            //TODO UUID, XUID
            if ($kick) {
                //TODO check why kick message does not appear + stuck in loading resources
                Loader::getInstance()->getLogger()->debug($debug);
                #$player->kick($msg, false);
                #$event->setKickMessage($msg);
                #$event->setCancelled();
                $event->getUser()->getPlayer()->kick($msg, false, $msg);
            }
            return;
        }
    }

    public function onUserBanEvent(UserBanEvent $event): void
    {
        if (!$event->isCancelled()) {
            if ($event->getUser()->isOnline()) {
                $ban = $event->getBan();
                $date = Network::timeFormat($ban->getSince());
            $date2 = Network::timeFormat($ban->getUntil());
                 $msg = TextFormat::DARK_RED . TextFormat::BOLD . "You are banned!";
            $msg .= TextFormat::RED . "\nReason: " . TextFormat::GRAY . $ban->getReason();
            $msg .= TextFormat::RED . "\nBanned since: " . TextFormat::GRAY . $date;
       
            $msg .= TextFormat::RED . "\nBanned Until: " . TextFormat::GRAY .  $date2;
                $event->getUser()->getPlayer()->kick($msg, false, $msg);
            }
        }
    }

}
