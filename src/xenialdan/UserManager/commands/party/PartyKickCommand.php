<?php

declare(strict_types=1);

namespace xenialdan\UserManager\commands\party;

use CortexPE\Commando\args\BaseArgument;
use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use CortexPE\Commando\exception\ArgumentOrderException;
use InvalidArgumentException;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use xenialdan\customui\elements\Button;
use xenialdan\customui\windows\SimpleForm;
use xenialdan\UserManager\API;
use xenialdan\UserManager\models\Party;
use xenialdan\UserManager\User;
use xenialdan\UserManager\UserStore;

class PartyKickCommand extends BaseSubCommand
{

    /**
     * This is where all the arguments, permissions, sub-commands, etc would be registered
     * @throws ArgumentOrderException
     */
    protected function prepare(): void
    {
        $this->setPermission("usermanager.party.kick");
        $this->registerArgument(0, new RawStringArgument("Player", true));
    }

    /**
     * @param CommandSender $sender
     * @param string $aliasUsed
     * @param BaseArgument[] $args
     * @throws InvalidArgumentException
     */
    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $user = UserStore::getUser($sender);
        if ($user === null) {
            $sender->sendMessage("DEBUG: null");
            return;
        }
        $party = Party::getParty($user);
        if (!$party instanceof Party) {
            $user->getPlayer()->sendMessage(TextFormat::RED . "You are in no party");
            return;
        }
        if ($party->getOwnerId() !== $user->getId()) {
            $user->getPlayer()->sendMessage(TextFormat::RED . "You are not the owner of this party");
            return;
        }

        if (!isset($args["Player"])) {
            $form = new SimpleForm("Kick member", "The owner can not be kicked");
            foreach ($party->getMembers() as $member) {
                if ($member->getId() !== $party->getOwnerId()) $form->addButton(new Button($member->getRealUsername()));
            }
            $form->addButton(new Button("Back"));
            $form->setCallable(function (Player $player, array $data) use ($form, $party): void {
                if ($data === "Back") return;
                if (($kickedMember = (UserStore::getUserByName($data[0]))) instanceof User) {
                    self::kick($party, $kickedMember);
                }
            });
            $sender->sendForm($form);
            return;
        }
        $name = trim($args["Player"] ?? "");
        if (empty($name)) {
            $sender->sendMessage("Invalid name given");
            return;
        }
        if (($kickedMember = (UserStore::getUserByName($name))) instanceof User) {
            self::kick($party, $kickedMember);
        } else {
            API::openUserNotFoundUI($sender, $name);
        }
    }

    /**
     * @param Party $party
     * @param User $user
     */
    private static function kick(Party $party, User $user): void
    {
        $user->getPlayer()->sendMessage(TextFormat::RED . "You have been kicked from the party");
        $party->getOwner()->getPlayer()->sendMessage(TextFormat::GREEN . $user->getDisplayName() . " has been kicked from the party");
        $party->removeMember($user);
    }
}
