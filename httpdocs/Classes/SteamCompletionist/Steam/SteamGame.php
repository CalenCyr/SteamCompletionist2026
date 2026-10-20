<?php

namespace Classes\SteamCompletionist\Steam;

use Classes\Common\Database\DatabaseInterface;
use Classes\Common\Logger\LoggerInterface;
use Classes\Common\Util\Util;

/**
 * Holds all information pertaining to a single Steam Game.
 *
 * @author Felix Kastner <felix@chapterfain.com>
 */
class SteamGame
{
    /**
     * Holds a array of configuration values used by the SteamGame Class.
     * @var array
     */
    private $config;

    /**
     * The database connection.
     * @var DatabaseInterface $db
     */
    private $db;

    /**
     * The logger, used to log debug messages.
     * @var LoggerInterface $logger
     */
    private $logger;

    /**
     * Utility Class.
     * @var Util $util
     */
    private $util;

    /**
     * The appId of the Steam Game.
     * @var int
     */
    public $appId;

    /**
     * The name of the Steam Game.
     * @var string
     */
    public $name;

    /**
     * Total number of minutes the Steam User has played this Steam Game.
     * @var int
     */
    public $minutesTotal;

    /**
     * Number of minutes the Steam User has played this Steam Game in the last 2 weeks.
     * @var int
     */
    public $minutes2Weeks;

    /**
     * Stored number of minutes the Steam User has played this game since adding it to their "to beat" slot.
     * @var int
     */
    public $minutesStored;

    /**
     * "to beat" slot this Steam Game is in, if applicable.
     * @var int
     */
    public $gameSlot;

    /**
     * Percentage of achievements that Steam User has achieved for this Steam Game, if applicable.
     * @var int
     */
    public $achievementPercentage;

    /**
     * A hash string used to locate the logo image of the Steam Game on the steam server.
     * @var string
     */
    public $logoHash;

    /**
     * A hash string used to locate the icon image of the Steam Game on the steam server.
     * @var string
     */
    public $iconHash;

    /**
     * Boolean that indicates whether this Steam Game is Steam Community enabled.
     * @var boolean
     */
    public $community;

    /**
     * Indicates this Steam Game's status ( 0 = No Special Status, 1 = Beaten, 2 = Blacklisted ).
     * @var int
     */
    public $gameStatus;

    /**
     * Indicates how many Steam Users own this Steam Game.
     * @var int
     */
    public $numOwned;

    /**
     * Indicates how many Steam Users have beaten this Steam Game.
     * @var int
     */
    public $numBeaten;

    /**
     * Indicates how many Stea mUsers have blacklisted this Steam Game.
     * @var int
     */
    public $numBlacklisted;

    /**
     * Path of the game's 184x69 store capsule, relative to STORE_ASSET_BASE (e.g. "steam/apps/440/capsule_184x69.jpg?t=1").
     * null = not resolved yet, '' = resolution attempted but Steam returned no store assets.
     * @var string|null
     */
    public $capsulePath;

    /**
     * Base URL for store assets. Newer apps keep their art under hashed paths that are only reachable through here.
     */
    const STORE_ASSET_BASE = 'https://shared.akamai.steamstatic.com/store_item_assets/';

    /**
     * Base URL for community-hosted app images (icons and legacy logos).
     */
    const COMMUNITY_IMAGE_BASE = 'https://cdn.akamai.steamstatic.com/steamcommunity/public/images/apps/';

    /**
     * Shown when every other image source fails.
     */
    const PLACEHOLDER_IMAGE = '/img/no_capsule.svg';

    /**
     * Constructor.
     *
     * @param $appId
     * @param $config
     * @param DatabaseInterface $db
     * @param LoggerInterface $logger
     * @param Util $util
     * @param $name
     * @param $minutesTotal
     * @param $minutes2Weeks
     * @param $minutesStored
     * @param $gameSlot
     * @param $gameStatus
     * @param $achievementPercentage
     * @param $logoHash
     * @param $iconHash
     * @param $community
     * @param $numOwned
     * @param $numBeaten
     * @param $numBlacklisted
     * @param string|null $capsulePath
     */
    public function __construct($appId, $config, DatabaseInterface $db, LoggerInterface $logger, Util $util, $name, $minutesTotal, $minutes2Weeks, $minutesStored, $gameSlot, $gameStatus, $achievementPercentage, $logoHash, $iconHash, $community, $numOwned, $numBeaten, $numBlacklisted, $capsulePath = null)
    {
        $this->capsulePath = $capsulePath;
        $this->appId = $appId;
        $this->config = $config;
        $this->db = $db;
        $this->logger = $logger;
        $this->util = $util;
        $this->name = $name;
        $this->minutesTotal = $minutesTotal;
        $this->minutes2Weeks = $minutes2Weeks;
        $this->minutesStored = $minutesStored;
        $this->gameSlot = $gameSlot;
        $this->gameStatus = $gameStatus;
        $this->achievementPercentage = $achievementPercentage;
        $this->logoHash = $logoHash;
        $this->iconHash = $iconHash;
        $this->community = $community;
        $this->numOwned = $numOwned;
        $this->numBeaten = $numBeaten;
        $this->numBlacklisted = $numBlacklisted;
    }

    /**
     * Returns candidate image URLs for this game, best first. The browser walks the list on load errors.
     *
     * Steam no longer returns img_logo_url from GetOwnedGames and the old community logo URLs 404,
     * so the store capsule (exactly 184x69, the size of a game card) is used instead.
     *
     * @return array
     */
    public function getImageUrls()
    {
        $urls = array();

        if ($this->capsulePath) {
            $urls[] = self::STORE_ASSET_BASE . $this->capsulePath;
        }

        $urls[] = self::STORE_ASSET_BASE . 'steam/apps/' . $this->appId . '/capsule_184x69.jpg';
        $urls[] = self::STORE_ASSET_BASE . 'steam/apps/' . $this->appId . '/header.jpg';

        if ($this->logoHash) {
            $urls[] = self::COMMUNITY_IMAGE_BASE . $this->appId . '/' . $this->logoHash . '.jpg';
        }
        if ($this->iconHash) {
            $urls[] = self::COMMUNITY_IMAGE_BASE . $this->appId . '/' . $this->iconHash . '.jpg';
        }

        $urls[] = self::PLACEHOLDER_IMAGE;

        return array_values(array_unique($urls));
    }

    /**
     * Returns the src and data-fallbacks attributes for a game image tag.
     *
     * @return string
     */
    public function getImageAttributes()
    {
        $urls = $this->getImageUrls();
        $src = array_shift($urls);
        return 'src="' . htmlspecialchars($src) . '" data-fallbacks="' . htmlspecialchars(implode('|', $urls)) . '"';
    }

    /**
     * Contacts the steam servers and grabs the achievement list for that game and that user. Then, a percentage is calculated and stored.
     *
     * @param $steamId
     */
    public function getAchievementPercentage($steamId)
    {
        $achievements = @json_decode($this->util->file_get_contents_curl('http://api.steampowered.com/ISteamUserStats/GetPlayerAchievements/v0001/?key=' . $this->config['key'] . '&steamid=' . $steamId . '&appid=' . $this->appId))->playerstats;
        $this->db->prepare('INSERT INTO `steamAPIUsage` SET `module` = 3');
        $this->db->execute();

        $curlSuccess = false;
        $percentage = 142; // We default the percentage to 142 .. essentially the number does not matter so long as it is above 100

        if (isset($achievements->success) && $achievements->success === true && isset($achievements->achievements)) {
            $curlSuccess = true;
            $total = 0;
            $achieved = 0;
            foreach ($achievements->achievements as $value) {
                $total++;
                if ($value->achieved)
                    $achieved++;
            }
            $percentage = round(($achieved / $total) * 100);
            $this->logger->addEntry('Achievements for ' . $achievements->gameName . ' loaded successfully. ');
        } elseif (isset($achievements->success) && ($achievements->success === false || ($achievements->success === true && !isset($achievements->achievements)))) {
            if(isset($achievements->error)) {
                if($achievements->error != 'Profile is not public') {
                    $curlSuccess = true;
                }
                $error = $achievements->error;
            } else {
                $error = 'No achievements in the list.';
            }
            $this->logger->addEntry('Achievements for ' . $this->appId . ' could not be loaded: ' . $error);
        }

        // So long as our CURL request succeeded, we have our answer regarding whether the game has a achievement % to display or not.
        if ($curlSuccess) {
            $this->db->prepare('UPDATE ownedGamesDB SET `achievPer` = ? WHERE `steamid` = ? AND `appid` = ?');
            $this->db->execute(array($percentage, $steamId, $this->appId), 'isi');
            $this->achievementPercentage = $percentage;
        }
    }

    /**
     * Saves game data into the local database.
     * A 2 step query is necessary in order to reliably across multiple data sources determine
     * whether the game to be saved is a "first timer" for this Steam User ( and hence needs to affect the "Owned" counter )
     * or not.
     *
     * @param $steamId
     * @throws \Exception
     */
    public function saveGame($steamId)
    {
        // Steam no longer sends logo hashes (and sometimes no icon), so never overwrite a stored hash with an empty one.
        $this->db->prepare('INSERT INTO `steamGameDB` (`appid`, `name`, `community`, `logo`, `icon`) VALUES (?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `community` = VALUES(`community`),
                            `logo` = IF(VALUES(`logo`) = \'\', `logo`, VALUES(`logo`)),
                            `icon` = IF(VALUES(`icon`) = \'\', `icon`, VALUES(`icon`))');
        $this->db->execute(array($this->appId, $this->name, (int)$this->community, (string)$this->logoHash, (string)$this->iconHash), 'isiss');

        // Legacy code for storing the game logo locally
//        if(!file_exists($this->logo) && $this->steamLogo)
//        {
//            $game_logo = @$this->util->file_get_contents_curl($this->steamLogo);
//            if(!@file_put_contents($this->logo, $game_logo)) {
//                throw new \Exception('Creating game logo failed.');
//            }
//        }

        $this->db->prepare('SELECT 1 FROM `ownedGamesDB` WHERE `steamid` = ? AND `appid` = ?');
        $this->db->execute(array($steamId, $this->appId), 'si');

        $result = $this->db->fetch();
        if ($result) {
            // Game already existed
            $this->db->prepare('UPDATE `ownedGamesDB` SET `minutesTotal` = ?, `minutes2Weeks` = ? WHERE `steamid` = ? AND `appid` = ?');
            $this->db->execute(array($this->minutesTotal, $this->minutes2Weeks, $steamId, $this->appId), 'iisi');
        } else {
            // New Game
            $this->db->prepare('INSERT INTO `ownedGamesDB` (`steamid`, `appid`, `minutesTotal`, `minutes2Weeks`) VALUES (?, ?, ?, ?)');
            $this->db->execute(array($steamId, $this->appId, $this->minutesTotal, $this->minutes2Weeks), 'siii');

            $this->db->prepare('UPDATE `steamGameDB` SET `owned` = `owned` + 1 WHERE appid = ?');
            $this->db->execute(array($this->appId), 'i');
        }
    }

    /**
     * Deletes a game from the owned games database.
     *
     * @param $steamId
     */
    public function deleteGame($steamId)
    {
        $this->db->prepare('DELETE FROM `ownedGamesDB` WHERE `steamid` = ? AND `appid` = ?');
        $this->db->execute(array($steamId, $this->appId), 'si');

        if ($this->gameStatus == 1) {
            // Beaten
            $this->db->prepare('UPDATE `steamGameDB` SET `owned` = `owned` - 1, `beaten` = `beaten` - 1 WHERE `appid` = ?');
            $this->db->execute(array($this->appId), 'i');
        } else if ($this->gameStatus == 2) {
            // Blacklisted
            $this->db->prepare('UPDATE `steamGameDB` SET `owned` = `owned` - 1, `blacklisted` = `blacklisted` - 1 WHERE `appid` = ?');
            $this->db->execute(array($this->appId), 'i');
        } else {
            $this->db->prepare('UPDATE `steamGameDB` SET `owned` = `owned` - 1 WHERE `appid` = ?');
            $this->db->execute(array($this->appId), 'i');
        }

    }
}