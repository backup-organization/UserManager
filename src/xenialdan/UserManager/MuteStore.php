<?php

declare(strict_types=1);

namespace xenialdan\UserManager;

use Ds\Map;
use pocketmine\Player;
use xenialdan\UserManager\event\UserMuteEvent;
use xenialdan\UserManager\models\Mute;

class MuteStore
{
    /**
     * userId => Ban
     * @var Map<int,Mute>
     */
    private static $mutes;

    public static function init(): void
    {
        self::$mutes = new Map();
        Loader::$queries->getMuteList(function (array $rows): void {
            foreach ($rows as $banData) {
                $ban = new Mute($banData["user_id"], $banData["since"], $banData["until"], $banData["expires"] === 1, $banData["reason"], $banData["types"], $banData["by"]);
                if (!$ban->hasExpired())
                    self::addMute($ban);
                else {//TODO Remove/cleanup this hack
                    Loader::$queries->deleteMute($ban, function (int $a): void {
                        echo $a;
                    });
                }
            }
            Loader::getInstance()->getLogger()->info(self::$mutes->count() . " mute entries loaded from database");
        });
    }

    /**
     * @return Mute[]
     */
    public static function getMutes(): array
    {
        return self::$mutes->toArray();
    }

    private static function addMute(Mute $ban): void
    {
        self::$mutes->put($ban->getUserId(), $ban);
        Loader::getInstance()->getLogger()->debug("Added mute $ban");
    }

    public static function createMute(Mute $ban): void
    {
        $ev = new UserMuteEvent(UserStore::getUserById($ban->getUserId()), $ban);
        $ev->call();
        if ($ev->isCancelled()) return;
        Loader::$queries->addMute($ban, function (int $insertId, int $affectedRows) use ($ban): void {
            self::addMute($ban);
        });
    }

    public static function getMute(?Player $player): ?Ban
    {
        if ($player === null) return null;
        return self::getMuteByName($player->getLowerCaseName());
    }

    public static function getMuteByName(string $playername): ?Ban
    {
        $user = UserStore::getUserByName($playername);
        if ($user instanceof User) return self::getMuteById($user->getId());
        return null;
    }

    public static function getMuteById(int $id): ?Ban
    {
        if (self::$mutes->isEmpty()) return null;
        if (self::$mutes->hasKey($id)) return self::$mutes->get($id);
        return null;
    }
}