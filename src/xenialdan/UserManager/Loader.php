<?php

declare(strict_types=1);

namespace xenialdan\UserManager;

use InvalidStateException;
use pocketmine\lang\BaseLang;
use pocketmine\plugin\PluginBase;
use pocketmine\plugin\PluginException;
use pocketmine\utils\Config;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;
use poggit\libasynql\SqlError;
use xenialdan\UserManager\commands\admin\BanCommand;
use xenialdan\UserManager\commands\admin\MuteCommand;
use xenialdan\UserManager\commands\admin\MutelistCommand;
use xenialdan\UserManager\commands\admin\BanlistCommand;
use xenialdan\UserManager\commands\friend\FriendCommand;
use xenialdan\UserManager\commands\party\PartyCommand;
use xenialdan\UserManager\commands\user\BlockCommand;
use xenialdan\UserManager\commands\user\UnblockCommand;
use xenialdan\UserManager\commands\UserManagerCommand;
use xenialdan\UserManager\exceptions\LanguageException;
use xenialdan\UserManager\listener\BanListener;
use xenialdan\UserManager\listener\ChatListener;
use xenialdan\UserManager\listener\MuteListener;
use xenialdan\UserManager\listener\PartyChatListener;
use xenialdan\UserManager\listener\SettingsListener;
use xenialdan\UserManager\listener\UserBaseEventListener;
use xenialdan\UserManager\models\Party;
use xenialdan\UserManager\models\Translations;
use pocketmine\scheduler\ClosureTask;

class Loader extends PluginBase
{
    /** @var Loader */
    private static $instance = null;
    /** @var DataConnector */
    private $database;
    /** @var Queries */
    public static $queries;
    /** @var string 3 letter iso639-2 */
    protected static $pluginLang = BaseLang::FALLBACK_LANGUAGE;
    /** @var array MC locale -> iso639-2 */
    public static $localeMapping = [];

    /**
     * Returns an instance of the plugin
     * @return Loader
     */
    public static function getInstance()
    {
        return self::$instance;
    }

    public static function getDataProvider(): DataConnector
    {
        return Loader::getInstance()->database;
    }

    public function onLoad(): void
    {
        self::$instance = $this;
        $this->saveDefaultConfig();
        //Remove default commands
        foreach (["ban", "ban-ip", "banlist", "pardon", "pardon-ip"] as $commandName) {
            $command = $this->getServer()->getCommandMap()->getCommand($commandName);
            if ($command !== null) {
                $this->getServer()->getCommandMap()->unregister($command);
                $this->getLogger()->debug("Unregistered default command $commandName");
            } else {
                $this->getLogger()->warning("Could not unregister default command $commandName");
            }
        }
        $this->getServer()->getCommandMap()->registerAll("UserManager", [
            new UserManagerCommand("usermanager", "UserManager help", ["um"]),
            new FriendCommand("friend", "Open friend list or manage friends"),
            new BlockCommand("block", "Block users"),
            new UnblockCommand("unblock", "Unblock users"),
            new BanCommand("ban", "%pocketmine.command.ban.player.description"),
            new MuteCommand("mute", "Mutes another player."),
            new BanlistCommand("banlist", "%pocketmine.command.banlist.description"),
            new MutelistCommand("mutelist", "Gives a list of muted players."),
            new PartyCommand("party", "Create parties or manage party members"),
        ]);
    }

    /**
     * @throws LanguageException
     * @throws InvalidStateException
     * @throws PluginException
     * @throws SqlError
     */
    public function onEnable(): void
    {
        $this->saveDefaultConfig();
        $this->database = libasynql::create($this, $this->getConfig()->get("database"), [
            "sqlite" => "sqlite.sql",
            "mysql" => "mysql.sql"
        ]);
        //create tables
        self::$queries = new Queries();
        //User store
        UserStore::init();
        //Ban store
        BanStore::init();
        //Parties
        Party::init();
        //events
        $this->getServer()->getPluginManager()->registerEvents(new UserBaseEventListener(), $this);
        $this->getServer()->getPluginManager()->registerEvents(new SettingsListener(), $this);
        $this->getServer()->getPluginManager()->registerEvents(new ChatListener(), $this);
        $this->getServer()->getPluginManager()->registerEvents(new PartyChatListener(), $this);
        $this->getServer()->getPluginManager()->registerEvents(new BanListener(), $this);
        $this->getServer()->getPluginManager()->registerEvents(new MuteListener(), $this);
        //translations
        Translations::init();
        /**
         * This code checks for any other plugins using the commands below after enable() state.
         */
        $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function(): void{
        foreach (["ban", "ban-ip", "banlist", "pardon", "pardon-ip", "mute", "mutelist", "mute-list"] as $commandName) {
            $command = $this->getServer()->getCommandMap()->getCommand($commandName);
            if ($command !== null) {
                $this->getServer()->getCommandMap()->unregister($command);
                $this->getLogger()->debug("Unregistered default command $commandName");
            } else {
                $this->getLogger()->warning("Could not unregister default command $commandName");
            }
        }
        $this->getServer()->getCommandMap()->registerAll("UserManager", [
            new UserManagerCommand("usermanager", "UserManager help", ["um"]),
            new FriendCommand("friend", "Open friend list or manage friends"),
            new BlockCommand("block", "Block users"),
            new UnblockCommand("unblock", "Unblock users"),
            new BanCommand("ban", "%pocketmine.command.ban.player.description"),
            new MuteCommand("mute", "Mutes another player."),
            new BanlistCommand("banlist", "%pocketmine.command.banlist.description"),
            new MutelistCommand("mutelist", "Gives a list of muted players."),
            new PartyCommand("party", "Create parties or manage party members"),
        ]);
        }), 20 * 3);
        $lang = (string)$this->getConfig()->get("language", BaseLang::FALLBACK_LANGUAGE);
        try {
            if (strlen($lang) !== 3) {
                throw new LanguageException("Selected plugin language {$lang} is invalid, the string must be 3 letters long.");
            }
            try {
                Translations::getLanguage($lang);
            } catch (LanguageException $e) {
                throw new LanguageException("Selected plugin language {$lang} is invalid: " . $e->getMessage());
            }
        } catch (LanguageException $exception) {
            $lang = BaseLang::FALLBACK_LANGUAGE;
            $this->getLogger()->warning($exception->getMessage());
            $this->getLogger()->warning("Resetting to default ($lang)");
            $this->getConfig()->set("language", $lang);
            $this->getConfig()->save();
        } finally {
            self::$pluginLang = $lang;
            $this->getLogger()->debug("Plugin language set to " . Translations::getLanguage()->getName());
            Translations::languageNeedsUpdate($lang);
        }
        //Ugly, but works. Creates an array similar to this: array["en_US"]=["eng","English (United States)"]
        $mapping = [];
        $isoConfig = new Config($this->getFile() . "resources" . DIRECTORY_SEPARATOR . "iso639-2mapping.json");
        $namesConfig = new Config($this->getFile() . "resources" . DIRECTORY_SEPARATOR . "language_names.json");
        foreach ($isoConfig->getAll() as [$locale, $iso]) {
            $mapping[$locale] = [];
            $mapping[$locale][0] = $iso;
        }
        foreach ($namesConfig->getAll() as [$locale, $name]) {
            $mapping[$locale] = $mapping[$locale] ?? [];
            $mapping[$locale][1] = $name;
        }
        self::$localeMapping = $mapping;
    }

    public function onDisable(): void
    {
        if (isset($this->database)) $this->database->close();
    }

    /**
     * @return string 3 letter
     */
    public function getPluginLanguage(): string
    {
        return self::$pluginLang;
    }

    /**
     * Returns the path to the language files folder.
     *
     * @return string
     */
    public function getLanguageFolder(): string
    {
        return $this->getFile() . "resources" . DIRECTORY_SEPARATOR . "lang" . DIRECTORY_SEPARATOR;
    }

    /**
     * Get a list of available languages
     * @return array
     */
    public function getLanguageList(): array
    {
        return BaseLang::getLanguageList($this->getLanguageFolder());
    }
}