<?php

declare(strict_types=1);

namespace xenialdan\UserManager\event;

use pocketmine\event\Cancellable;
use xenialdan\UserManager\models\Mute;
use xenialdan\UserManager\User;

class UserMuteEvent extends UserEvent implements Cancellable
{
    /**
     * @var Ban
     */
    private $ban;

    /**
     * UserSettingsChangeEvent constructor.
     * @param User $user
     * @param Ban $ban
     */
    public function __construct(User $user, Mute $ban)
    {
        parent::__construct($user);
        $this->ban = $ban;
    }

    /**
     * @return Ban
     */
    public function getMute(): Mute
    {
        return $this->ban;
    }

}