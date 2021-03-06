<?php

declare(strict_types=1);

namespace xenialdan\UserManager\commands\friend;

use CortexPE\Commando\args\BaseArgument;
use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use CortexPE\Commando\exception\ArgumentOrderException;
use InvalidArgumentException;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use xenialdan\UserManager\API;
use xenialdan\UserManager\User;
use xenialdan\UserManager\UserStore;

class FriendAddCommand extends BaseSubCommand
{

    /**
     * This is where all the arguments, permissions, sub-commands, etc would be registered
     * @throws ArgumentOrderException
     */
    protected function prepare(): void
    {
        $this->setPermission("usermanager.friend.add");
        $this->registerArgument(0, new RawStringArgument("Player"));
    }

    /**
     * @param CommandSender $sender
     * @param string $aliasUsed
     * @param BaseArgument[] $args
     * @throws InvalidArgumentException
     */
    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage("This command is for players only.");
            return;
        }
        $user = UserStore::getUser($sender);
        if ($user === null) {
            $sender->sendMessage("DEBUG: null");
            return;
        }
        $name = trim(strval($args["Player"] ?? ""));
        if (empty($name)) {
            $sender->sendMessage("Invalid name given");
            return;
        }
        if (($friend = (UserStore::getUserByName($name))) instanceof User && $friend->getUsername() !== $sender->getLowerCaseName()) {
            API::openFriendConfirmUI($sender, $friend);
        } else {
            API::openUserNotFoundUI($sender, $name);
        }
    }
}
