<?php

/**
 * Inicializace, tridy a funkce jadra
 *
 * Pouziti:
 *
 *  require './require/load.php';
 *  SL::init('./');
 *
 */

/* ----  tridy  ---- */

/**
 * Systemova trida
 * Spravuje prostredi systemu.
 */
abstract class SL
{
    /** @var string */
    public static $configFile;
    /** @var bool */
    public static $envChanges;
    /** @var bool */
    public static $lightMode;
    /** @var bool */
    public static $databaseEnabled;

    /** @var float */
    public static $start;
    /** @var ClassLoader */
    public static $classLoader;
    /** @var array */
    public static $states = array('BETA', 'RC', 'STABLE');
    /** @var array */
    public static $imageExt = array('png', 'jpeg', 'jpg', 'gif');
    /** @var string */
    public static $imageError;
    /** @var int */
    public static $hcmUid = 0;
    /** @var int */
    public static $captchaCounter = 0;
    /** @var array */
    public static $settings = array();
    /** @var array */
    public static $registry = array();
    /** @var array */
    public static $cronIntervals = array(
        '1m' => 60,     '2m' => 120,    '5m' => 300,    '10m' => 600,
        '15m' => 900,   '30m' => 1800,  '1h' => 3600,   '2h' => 7200,
        '5h' => 18000,  '12h' => 43200, '24h' => 86400, 'maintenance' => null,
    );

    /** @var bool */
    protected static $initialized = false;
    /** @var FileCache|null */
    protected static $cache;

    /**
     * Inicializovat system
     *
     * @param string      $root            relativni cesta do korenoveho adresare
     * @param string|null $configFile      cesta ke konfiguracnimu skriptu nebo null(= vychozi)
     * @param bool        $envChanges      provest zmeny v prostredi (error reporting, locale, header, ...) 1/0
     * @param bool        $lightMode       odlehceny mod (pouze pripojeni k db, bez session, nastaveni, lokalizace, atd) 1/0
     * @param bool        $databaseEnabled inicializovat pripojeni k databazi 1/0
     * @param bool        $runCron         automaticky spustit cron, je-li aktivovan 1/0
     */
    public static function init($root, $configFile = null, $envChanges = true, $lightMode = false, $databaseEnabled = true, $runCron = true)
    {
        if (self::$initialized) {
            throw new BadMethodCallException();
        }
        $initialized = true;

        self::$start = microtime(true);

        /* ----  konfigurace  ---- */

        if (null === $configFile) {
            self::$configFile = $root . 'config.php';
        } else {
            self::$configFile = $configFile;
        }
        self::$envChanges = $envChanges;
        self::$lightMode = $lightMode;
        self::$databaseEnabled = $databaseEnabled;
        self::$imageError = $root . 'remote/image_error.png';

        // soubor s nastavenim
        require self::$configFile;

        // doplneni konfigurace (kvuli kompatibilite)
        if (!isset($locale)) $locale = array('czech', 'utf8', 'cz_CZ');
        if (!isset($timezone)) $timezone = 'Europe/Prague';
        if (!isset($geo)) $geo = array(50.5, 14.26, 90.583333);
        if (!isset($port)) $port = ini_get('mysqli.default_port');

        // systemove konstanty
        define('_indexroot', $root);
        define('_core', '1');
        define('_nl', "\n");
        define('_sessionprefix', md5($server . $database . $user . $prefix) . '-');
        if (!defined('_administration')) define('_administration', 0);
        define('_dev', isset($dev) ? $dev : true); // vyvojovy mod 1/0
        define('_systemstate', 2); // 0 = beta, 1 = rc, 2 = stable
        define('_systemstate_revision', 0); // revize systemu
        define('_systemversion', '7.5.4'); // verze systemu
        define('_mysql_prefix', $prefix);
        define('_mysql_db', $database);
        define('_upload_dir', _indexroot . 'upload/');
        define('_plugin_dir', _indexroot . 'plugins/common/');
        define('_tmp_dir', _indexroot . 'data/tmp/');
        define('_void_file', _indexroot . 'data/void.nodelete');
        define('_geo_latitude', $geo[0]);
        define('_geo_longitude', $geo[1]);
        define('_geo_zenith', $geo[2]);

        /* ----  autoloader  ---- */

        require _indexroot . 'require/class/class_loader.php';
        self::$classLoader = new ClassLoader;
        self::$classLoader
            ->setDebug(_dev)
            ->registerClassMap(array(
                'AdminBread' => _indexroot . 'require/class/admin_bread.php',
                'Color' => _indexroot . 'require/class/color.php',
                'DBDump' => _indexroot . 'require/class/dbdump.php',
                'KZip' => _indexroot . 'require/class/kzip.php',
                'KZipStream' => _indexroot . 'require/class/kzip.php',
                'TreeManager' => _indexroot . 'require/class/tree_manager.php',
                'TreeReader' => _indexroot . 'require/class/tree_reader.php',
                'LangPack' => _indexroot . 'require/class/lang_pack.php',
                'FileCache' => _indexroot . 'require/class/file_cache.php',
            ))
            ->register()
        ;

        /* ----  upravy PHP prostredi  ---- */

        if ($envChanges) {
        
            // kontrola verze PHP a pritomnosti rozsireni
            if (PHP_VERSION_ID < 50100) {
                _systemFailure('Je vyžadováno PHP 5.1.0 nebo novější.');
            }
            if (!extension_loaded('mbstring')) {
                _systemFailure('Chybí PHP rozšíření <code>mbstring</code> (Multibyte String Functions).');
            }
            if (!extension_loaded('mysqli')) {
                _systemFailure('Chybí PHP rozšíření <code>mysqli</code>, které je potřebné pro práci s databází.');
            }

            // kontrola a nastaveni $_SERVER['REQUEST_URI']
            if (!isset($_SERVER['REQUEST_URI'])) {
                if (isset($_SERVER['HTTP_X_REWRITE_URL'])) $_SERVER['REQUEST_URI'] = $_SERVER['HTTP_X_REWRITE_URL']; // ISAPI_Rewrite 3.x
                elseif (isset($_SERVER['HTTP_REQUEST_URI'])) $_SERVER['REQUEST_URI'] = $_SERVER['HTTP_REQUEST_URI']; // ISAPI_Rewrite 2.x
                else {
                    if (isset($_SERVER['SCRIPT_NAME'])) $_SERVER['HTTP_REQUEST_URI'] = $_SERVER['SCRIPT_NAME'];
                    else $_SERVER['HTTP_REQUEST_URI'] = $_SERVER['PHP_SELF'];
                    if (!empty($_SERVER['QUERY_STRING'])) $_SERVER['HTTP_REQUEST_URI'] .= '?' . $_SERVER['QUERY_STRING'];
                    $_SERVER['REQUEST_URI'] = $_SERVER['HTTP_REQUEST_URI'];
                }
            }

            // vyruseni register_globals
            if (ini_get('register_globals') != '') {
                foreach (array_keys($_REQUEST) as $key) {
                    unset($GLOBALS[$key]);
                }
            }

            // vypnuti magic_quotes
            if (get_magic_quotes_gpc()) {

                $search = array(&$_GET, &$_POST, &$_COOKIE);
                for ($i = 0; isset($search[$i]); ++$i) {
                    foreach ($search[$i] as &$value) {
                        if (is_array($value)) {
                            $search[] = &$value;
                        } else {
                            $value = stripslashes($value);
                        }
                    }
                    unset($search[$i]);
                }

                if (function_exists('set_magic_quotes_runtime')) {
                    @set_magic_quotes_runtime(0);
                }

                unset($search, $i, $value);

            }

            // hlaseni chyb
            $err_rep = E_ALL;
            if (_dev) $disable = array();
            else $disable = array('E_NOTICE ', 'E_USER_NOTICE', 'E_DEPRECATED', 'E_STRICT');
            for($i = 0; isset($disable[$i]); ++$i)
                if (defined($disable[$i])) $err_rep &= ~ constant($disable[$i]);
            error_reporting($err_rep);

            // casove pasmo
            @setlocale(LC_TIME, $locale);
            if (function_exists('date_default_timezone_set')) date_default_timezone_set($timezone);

            // header a kodovani
            mb_internal_encoding('UTF-8');
            if (!defined('_header')) $header = 'Content-Type: text/html; charset=UTF-8';
            else $header = _header;
            if ($header !== '') header($header);

        }

        /* ----  nacteni funkci  ---- */

        require _indexroot . 'require/functions.php';
        if (isset($_GET['___identify'])) {
            echo 'SunLight CMS ', _systemversion, ' ', self::$states[_systemstate], _systemstate_revision;
            exit;
        }

        /* ----  pripojeni k mysql  ---- */

        if ($databaseEnabled) {
            $con = @mysqli_connect($server, $user, $password, $database);
            if (!is_object($con)) _systemFailure('Připojení k databázi se nezdařilo. Důvodem je pravděpodobně výpadek serveru nebo chybné přístupové údaje.</p><hr /><pre>' . _htmlStr(mysqli_connect_error()) . '</pre><hr /><p>Zkontrolujte přístupové údaje v souboru <em>config.php</em>.');
            $con->set_charset('utf8');
            DB::$con = $con;
        }

        /* ----  konstanty nastaveni, jazykovy soubor, motiv, session  ---- */

        if (!$lightMode) {

            // definovani konstant nastaveni
            $query = DB::query('SELECT * FROM `' . _mysql_prefix . '-settings`', true);
            $directive = array('banned' => '');
            if (DB::error() != false) _systemFailure('Připojení k databázi proběhlo úspěšně, ale dotaz na databázi selhal.</p><hr /><pre>' . _htmlStr(DB::error()) . '</pre><hr /><p>Zkontrolujte, zda je databáze správně nainstalovaná.');
            while ($item = DB::row($query)) {
                if (isset($directive[$item['var']])) {
                    // direktiva
                    $directive[$item['var']] = $item['val'];
                } elseif ($item['var'][0] === '.') {
                    // nastaveni zacinajici teckou
                    self::$settings[substr($item['var'], 1)] = $item['val'];
                } else {
                    // konstanta
                    define('_' . $item['var'], $item['val']);
                }
            }
            DB::free($query);

            // nastavit interval pro maintenance
            self::$cronIntervals['maintenance'] = _maintenance_interval;

            // ip adresa klienta
            if (empty($_SERVER['REMOTE_ADDR'])) {
                $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
            }
            if (_proxy_mode && isset($_SERVER['HTTP_X_FORWARDED_FOR'])) $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            else $ip = $_SERVER['REMOTE_ADDR'];
            define('_userip', trim((($addr_comma = strrpos($ip, ',')) === false) ? $ip : substr($ip, $addr_comma + 1)));

            // poinstalacni kontrola
            if (_install_check) {
                require _indexroot . 'require/installcheck.php';
            }

            // kontrola verze databaze
            if (!defined('_dbversion') or !_checkVersion('database', _dbversion)) {
                _systemFailure('Verze nainstalované databáze není kompatibilní s verzí systému. Pokud byl právě aplikován patch pro přechod na novější verzi, pravděpodobně jste zapoměl(a) spustit skript pro aktualizaci databáze.');
            }

            // inicializace session
            require _indexroot . 'require/session.php';

            // inicializace jazykoveho souboru
            if (_loginindicator and _language_allowcustom and _loginlanguage != "") $language = _loginlanguage;
            else $language = _language;
            $langfile = _indexroot . 'plugins/languages/' . $language . '.php';
            $langfile_default = _indexroot . 'plugins/languages/default.php';

            if (file_exists($langfile)) {
                $GLOBALS['_lang'] = require $langfile;
                define('_active_language', $language);
            } else {
                if (file_exists($langfile_default)) {
                    $GLOBALS['_lang'] = require $langfile_default;
                    define('_active_language', 'default');
                } else {
                    _systemFailure('Zvolený ani přednastavený jazykový soubor nebyl nalezen.');
                }
            }

            // kontrola verze jazykoveho souboru
            if (!_checkVersion('language_file', $GLOBALS['_lang']['main.version'])) {
                DB::query('UPDATE `' . _mysql_prefix . '-settings` SET val="default" WHERE var="language"');
                _systemFailure('Zvolený jazykový soubor není kompatibilní s verzí systému.');
            }

            // kontrola blokace IP
            if ($directive['banned'] !== '' && !_administration) {
                $directive['banned'] = explode("\n", $directive['banned']);
                for ($i = 0; isset($directive['banned'][$i]); ++$i) {
                    if (0 === strncmp($directive['banned'][$i], _userip, strlen($directive['banned'][$i]))) {
                        header('HTTP/1.0 403 Forbidden');
                        if (defined('_header')) die('Your IP address is banned');
                        require _indexroot . 'require/ipban.php';
                        die;
                    }
                }
            }

            // motiv
            $template = _indexroot . 'plugins/templates/' . _template . '/template.php';
            $template_config = _indexroot . 'plugins/templates/' . _template . '/config.php';

            if (!file_exists($template) or !file_exists($template_config)) {
                DB::query('UPDATE `' . _mysql_prefix . '-settings` SET val=\'default\' WHERE var=\'template\'');
                _systemFailure('Zvolený motiv ' . _template . ' nebyl nalezen. Přepnuto na výchozí motiv.');
            }

            require $template_config;

            // kontrola verze motivu
            if (!_checkVersion('template', _template_version) and !_administration) {
                _systemFailure('Zvolený motiv není kompatibilní s verzí systému.');
            }

            // nacist rozsireni
            _extendLoad();

            // udalost inicializace systemu
            _extend('call', 'sys.init');

            // systemove callbacky
            _extend('reg', 'sys.cron.maintenance', array(__CLASS__, 'doMaintenance'));

            // cron
            if (_cron_auto && $runCron) {
                self::runCron();
            }

        }
    }

    /**
     * Spustit CRON
     */
    public static function runCron()
    {
        $cronNow = time();
        $cronUpdate = false;
        $cronLockFile = null;
        $cronTimes = unserialize(self::$settings['cron_times']);
        if (false === $cronTimes) {
            $cronTimes = array();
            $cronUpdate = true;
        }

        // zkontrolovat intervaly
        foreach (self::$cronIntervals as $cronIntervalName => $cronIntervalSeconds) {
            if (isset($cronTimes[$cronIntervalName])) {
                // posledni cas je zaznamenan
                if ($cronNow - $cronTimes[$cronIntervalName] >= $cronIntervalSeconds) {

                    // kontrola lock file
                    if (null === $cronLockFile) {
                        $cronLockFilePath = _indexroot . 'data/cron.lock';
                        $cronLockFile = fopen($cronLockFilePath, 'r');
                        if (!flock($cronLockFile, LOCK_EX | LOCK_NB)) {
                            // lock file je nepristupny
                            fclose($cronLockFile);
                            $cronLockFile = null;
                            $cronUpdate = false;
                            break;
                        }
                    }

                    // udalost
                    $cronEventArgs = array(
                        'last' => $cronTimes[$cronIntervalName],
                        'name' => $cronIntervalName,
                        'seconds' => $cronIntervalSeconds,
                        'delay' => $cronNow - $cronTimes[$cronIntervalName],
                    );
                    _extend('call', 'sys.cron', $cronEventArgs);
                    _extend('call', 'sys.cron.' . $cronIntervalName, $cronEventArgs);

                    // aktualizovat posledni cas
                    $cronTimes[$cronIntervalName] = $cronNow;
                    $cronUpdate = true;

                }
            } else {
                // posledni cas neni zaznamenan
                $cronTimes[$cronIntervalName] = $cronNow;
                $cronUpdate = true;
            }
        }

        // aktualizovat casy
        if ($cronUpdate) {
            DB::update(_mysql_prefix . '-settings', '`var`=".cron_times"', array(
                'val' => serialize($cronTimes),
            ));
        }

        // uvolnit lockfile
        if (null !== $cronLockFile) {
            flock($cronLockFile, LOCK_UN);
            fclose($cronLockFile);
        }
    }

    /**
     * Provest udrzbu systemu
     */
    public static function doMaintenance()
    {
        // cisteni miniatur
        _pictureThumbClean(_thumb_cleanup_threshold);

        // smazani cache
        self::getCache()->clear();
    }

    /**
     * Ziskat instanci cache komponentu
     * @return FileCache
     */
    public static function getCache()
    {
        if (null === self::$cache) {
            self::$cache = new FileCache(_indexroot . 'data/tmp/cache');
            self::$cache->setVerifyBoundFiles(1 == _dev);
        }

        return self::$cache;
    }
}

/**
 * Databazova trida
 * Staticky se pouziva pro praci se sytemovym pripojenim
 */
abstract class DB
{
    /** @var mysqli */
    public static $con;

    /** @var callable|null */
    public static $logger;

    /**
     * Vykonat SQL dotaz
     * @param string $sql          SQL dotaz
     * @param bool   $expect_error nevyvolat trigger_error v DEV modu, chyba je ocekavana
     * @param bool   $log          vyvolat logger, je-li registrovan 1/0
     * @return mysqli_result|bool
     */
    public static function query($sql, $expect_error = false, $log = true)
    {
        if (null !== static::$logger && $log) {
            call_user_func(static::$logger, $sql);
        }

        $q = static::$con->query($sql);
        if (_dev && $q === false && !$expect_error) {
            // varovani v dev modu
            trigger_error('SQL error: ' . static::$con->error . ' --- SQL code: ' . $sql, E_USER_WARNING);
        }

        return $q;
    }

    /**
     * Vykonat SQL dotaz a vratit prvni radek
     * @param string $sql
     * @param bool   $expect_error nevyvolat trigger_error v DEV modu, chyba je ocekavana
     * @return array|bool
     */
    public static function query_row($sql, $expect_error = false)
    {
        $q = static::query($sql, $expect_error);
        if (false === $q) {
            return false;
        }
        $row = static::row($q);
        static::free($q);

        return $row;
    }

    /**
     * Spocitat pocet radku splnujici podminku
     * @param string $table nazev tabulky s prefixem
     * @param string $where podminka
     * @return int
     */
    public static function count($table, $where = '1')
    {
        $q = static::query('SELECT COUNT(*) FROM `' . $table . '` WHERE ' . $where);
        if ($q) {
            $count = intval(static::result($q, 0));
            static::free($q);

            return $count;
        }

        return 0;
    }

    /**
     * Zjistit posledni chybu
     * @return string prazdny retezec pokud neni chyba
     */
    public static function error()
    {
        return static::$con->error;
    }

    /**
     * Ziskat radek z dotazu
     * @param mysqli_result $result
     * @return array|bool
     */
    public static function row(mysqli_result $result)
    {
        $row = $result->fetch_assoc();

        if (null !== $row) {
            return $row;
        } else {
            return false;
        }
    }

    /**
     * Ziskat radek z dotazu s numerickymi klici namisto nazvu sloupcu
     * @param  mysqli_result $result
     * @return array|bool
     */
    public static function rown(mysqli_result $result)
    {
        $row = $result->fetch_row();

        if (null !== $row) {
            return $row;
        } else {
            return false;
        }
    }

    /**
     * Ziskat konkretni radek a sloupec z dotazu
     *
     * @param mysqli_result $result vysledek dotazu
     * @param int           $row    cislo radku
     * @param int           $column cislo sloupce
     */
    public static function result(mysqli_result $result, $row, $column = 0)
    {
        $row = $result->fetch_row();

        if (null !== $row && isset($row[$column])) {
            return $row[$column];
        } else {
            return null;
        }
    }

    /**
     * Uvolnit vysledek dotazu
     * @param  mysqli_result $result
     * @return bool
     */
    public static function free(mysqli_result $result)
    {
        return $result->free();
    }

    /**
     * Zjistit pocet radku v dotazu
     * @param  mysqli_result $result
     * @return int
     */
    public static function size(mysqli_result $result)
    {
        return $result->num_rows;
    }

    /**
     * Zjitit AUTO_INCREMENT ID posledniho vlozeneho radku
     * @return int
     */
    public static function insertID()
    {
        return static::$con->insert_id;
    }

    /**
     * Zjistit pocet radku ovlivnenych poslednim dotazem
     * @return int
     */
    public static function affectedRows()
    {
        return static::$con->affected_rows;
    }

    /**
     * Zpracovat hodnotu pro pouziti v dotazu
     * @param  mixed             $value       hodnota
     * @param  bool              $handleArray zpracovavat pole 1/0
     * @return string|array|null
     */
    public static function esc($value, $handleArray = false)
    {
        if (null === $value) {
            return null;
        }

        if ($handleArray && is_array($value)) {
            foreach ($value as &$item) {
                $item = static::esc($item);
            }

            return $value;
        }
        if (is_string($value)) {
            return static::$con->real_escape_string($value);
        }
        if (is_numeric($value)) {
            return (0 + $value);
        }

        return static::$con->real_escape_string((string) $value);
    }

    /**
     * Zpracovat hodnotu pro pouziti v dotazu vcetne pripadnych uvozovek
     * @param  mixed  $value       hodnota
     * @param  bool   $handleArray zpracovavat pole 1/0
     * @return string
     */
    public static function val($value, $handleArray = false)
    {
        $value = static::esc($value, $handleArray);
        if ($handleArray && is_array($value)) {
            $out = '';
            $itemCounter = 0;
            foreach ($value as $item) {
                if (0 !== $itemCounter) {
                    $out .= ',';
                }
                $out .= static::val($item);
                ++$itemCounter;
            }

            return $out;
        } elseif (is_string($value)) {
            return '\'' . $value . '\'';
        } elseif (null === $value) {
            return 'NULL';
        }
        return $value;
    }

    /**
     * Zpracovat pole hodnot pro pouziti v dotazu (napr. IN)
     * @param  array  $arr pole
     * @return string ve formatu a,b,c,d
     */
    public static function arr($arr)
    {
        $sql = '';
        for ($i = 0; isset($arr[$i]); ++$i) {
            if (0 !== $i) $sql .= ',';
            $sql .= static::val($arr[$i]);
        }

        return $sql;
    }

    /**
     * Vlozit radek do databaze
     * @param  string   $table         nazev tabulky s prefixem
     * @param  array    $data          asociativni pole s daty
     * @param  bool     $get_insert_id vratit insert ID 1/0
     * @return bool|int
     */
    public static function insert($table, $data, $get_insert_id = false)
    {
        if (empty($data)) return false;
        $counter = 0;
        $col_list = '';
        $val_list = '';
        foreach ($data as $col => $val) {
            if (0 !== $counter) {
                $col_list .= ',';
                $val_list .= ',';
            }
            $col_list .= "`{$col}`";
            $val_list .= static::val($val);
            ++$counter;
        }
        $q = static::query("INSERT INTO `{$table}` ({$col_list}) VALUES({$val_list})");
        if (false !== $q && $get_insert_id) return static::insertID();
        return $q;
    }

    /**
     * Aktualizovat radky v databazi
     * @param  string   $table nazev tabulky s prefixem
     * @param  string   $cond  podminka WHERE
     * @param  array    $data  asociativni pole se zmenami
     * @param  int|null $limit limit upravenych radku (null = bez limitu)
     * @return bool
     */
    public static function update($table, $cond, $data, $limit = 1)
    {
        if (empty($data)) return false;
        $counter = 0;
        $set_list = '';
        foreach ($data as $col => $val) {
            if (0 !== $counter) $set_list .= ',';
            $set_list .= "`{$col}`=" . static::val($val);
            ++$counter;
        }

        return static::query("UPDATE `{$table}` SET {$set_list} WHERE {$cond}" . ((null === $limit) ? '' : " LIMIT {$limit}"));
    }

    /**
     * Formatovat datum a cas
     * @param  int    $timestamp timestamp
     * @return string YY-MM-DD HH:MM:SS (bez uvozovek)
     */
    public static function datetime($timestamp)
    {
        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Formatovat datum
     * @param  int    $timestamp timestamp
     * @return string YY-MM-DD (bez uvozovek)
     */
    public static function date($timestamp)
    {
        return date('Y-m-d', $timestamp);
    }

}

/* ----  funkce  ---- */

if (!function_exists('memory_get_usage')) {
    /**
     * Funkce pro pripad, ze neexistuje memory_get_usage()
     * Vraci vzdy 1MB obsazenou pamet
     * @return int
     */
    function memory_get_usage()
    {
        return 1048576;
    }
}



/**
 * Zobrazi zpravu o selhani systemu a ukonci provadeni skriptu
 * @param string $msg zprava
 */
function _systemFailure($msg)
{
    if (!headers_sent()) {
        header('HTTP/1.1 503 Service Temporarily Unavailable');
        header('Retry-After: 600');
        header('Content-Type: text/html; charset=UTF-8', true);
    }
    echo '<!DOCTYPE html><html><head><title>Selhání systému</title></head><body>';
    _systemMessage("Selhání systému", "<p>" . $msg . "</p>");
    echo '</body></html>';
    exit;
}

/**
 * Zobrazeni systemove zpravy (neukonci skript)
 * @param string $title titulek
 * @param string $message zprava
 */
function _systemMessage($title, $message)
{
    echo "

<div style='text-align: center !important; margin: 10px !important;'>
<div style='text-align: left !important; margin: 0 auto !important; width: 600px !important; font-family: monospace !important; font-size: 13px !important; color: #000000 !important; background-color: #ffffff !important; border: 1px solid #ffffff !important; position: relative; z-index: 999;'>
<div style='border: 1px solid #000000 !important; padding: 10px; overflow: auto !important;'>
<h1 style='color: #000000 !important; font-size: 20px !important; border-bottom: 2px solid #ff6600 !important;'>" . $title . "</h1>
" . $message . "
</div>
</div>
</div>

";
}

/**
 * Vlozeni GET promenne do odkazu
 * @param string $link adresa
 * @param string $params query retezec
 * @param bool $entity pouzit &amp; pro oddeleni 1/0
 * @return string
 */
function _addGetToLink($link, $params, $entity = true)
{
    // oddelovaci znak
    if ($params !== '') {
        if (false === strpos($link, "?")) {
            $link .= "?";
        } else {
            if ($entity) {
                $link .= "&amp;";
            } else {
                $link .= "&";
            }
        }
    }

    return $link . $params;
}

/**
 * Vlozeni dat pro obnovu formulare do url
 * @param string $url adresa
 * @param array $array asociativni pole s daty
 * @return string
 */
function _addFdGetToLink($url, $array)
{
    // oddelovaci znak
    if (mb_substr_count($url, "?") == 0) {
        $url .= "?";
    } else {
        $url .= "&";
    }

    // seznam
    foreach ($array as $key => $item) {
        $url .= "_formData[" . $key . "]=" . urlencode($item) . "&";
    }

    return mb_substr($url, 0, mb_strlen($url) - 1);
}

/**
 * Sestaveni query stringu z pole
 * @param array $items asociativni pole s daty
 * @return string
 */
function _buildQuery($items)
{
    if (function_exists('http_build_query')) return http_build_query($items);
    $output = '';
    $last = sizeof($items) - 1;
    $current = 0;
    foreach ($items as $key => $val) {
        $output .= urlencode($key) . '=' . urlencode($val);
        if ($current !== $last) $output .= '&';
        ++$current;
    }

    return $output;
}

/**
 * Sestaveni url z casti
 * Kompatibilni s vystupem funkce parse_url()
 * @param array $parts asociativni pole s castmi url (scheme, host, port, user, pass, path, query, fragment)
 * @return string
 */
function _buildURL($parts)
{
    $output = '';

    // zakladni casti
    if (!empty($parts['host'])) {
        if (!empty($parts['scheme'])) $output .= $parts['scheme'] . '://'; // scheme
        if (!empty($parts['user'])) $output .= $parts['user']; // username
        if (!empty($parts['pass'])) $output .= ':' . $parts['pass']; // password
        if (!empty($parts['user']) || !empty($parts['pass'])) $output .= '@'; // @
        $output .= $parts['host']; // host
        if (!empty($parts['port'])) $output .= ':' . $parts['port']; // port
    }

    // path
    if (!empty($parts['path'])) $output .= (($parts['path'][0] !== '/') ? '/' : '') . $parts['path'];
    else $output .= '/';

    // query
    if (!empty($parts['query']) && is_array($parts['query'])) {
        $output .= '?';
        $output .= _buildQuery($parts['query']);
    }

    // fragment
    if (!empty($parts['fragment'])) $output .= '#' . $parts['fragment'];

    // return output
    return $output;
}

/**
 * Pridat schema do URL, pokud jej neobsahuje nebo neni relativni
 * @param string $url
 * @return string
 */
function _addSchemeToURL($url)
{
    if (mb_substr($url, 0, 7) !== 'http://' && mb_substr($url, 0, 8) !== 'https://' && $url[0] !== '/' && mb_substr($url, 0, 2) !== './') $url = 'http://' . $url;
    return $url;
}

/**
 * Formatovani retezce pro uzivatelska jmena, mod rewrite atd.
 * @param string $input vstupni retezec
 * @param bool $lower prevest na mala pismena 1/0
 * @param array|null $extra mapa extra povolenych znaku nebo null
 * @return string
 */
function _anchorStr($input, $lower = true, $extra = null)
{
    // diakritika a mezery
    static $trans = array(' ' => '-', 'é' => 'e', 'ě' => 'e', 'É' => 'E', 'Ě' => 'E', 'ř' => 'r', 'Ř' => 'R', 'ť' => 't', 'Ť' => 'T', 'ž' => 'z', 'Ž' => 'Z', 'ú' => 'u', 'Ú' => 'U', 'ů' => 'u', 'Ů' => 'U', 'ü' => 'u', 'Ü' => 'U', 'í' => 'i', 'Í' => 'I', 'ó' => 'o', 'Ó' => 'O', 'á' => 'a', 'Á' => 'A', 'š' => 's', 'Š' => 'S', 'ď' => 'd', 'Ď' => 'D', 'ý' => 'y', 'Ý' => 'Y', 'č' => 'c', 'Č' => 'C', 'ň' => 'n', 'Ň' => 'N', 'ä' => 'a', 'Ä' => 'A', 'ĺ' => 'l', 'Ĺ' => 'L', 'ľ' => 'l', 'Ľ' => 'L', 'ŕ' => 'r', 'Ŕ' => 'R', 'ö' => 'o', 'Ö' => 'O');
    $input = strtr($input, $trans);

    // odfiltrovani nepovolenych znaku
    static
        $allow = array('A' => 0, 'a' => 1, 'B' => 2, 'b' => 3, 'C' => 4, 'c' => 5, 'D' => 6, 'd' => 7, 'E' => 8, 'e' => 9, 'F' => 10, 'f' => 11, 'G' => 12, 'g' => 13, 'H' => 14, 'h' => 15, 'I' => 16, 'i' => 17, 'J' => 18, 'j' => 19, 'K' => 20, 'k' => 21, 'L' => 22, 'l' => 23, 'M' => 24, 'm' => 25, 'N' => 26, 'n' => 27, 'O' => 28, 'o' => 29, 'P' => 30, 'p' => 31, 'Q' => 32, 'q' => 33, 'R' => 34, 'r' => 35, 'S' => 36, 's' => 37, 'T' => 38, 't' => 39, 'U' => 40, 'u' => 41, 'V' => 42, 'v' => 43, 'W' => 44, 'w' => 45, 'X' => 46, 'x' => 47, 'Y' => 48, 'y' => 49, 'Z' => 50, 'z' => 51, '0' => 52, '1' => 53, '2' => 54, '3' => 55, '4' => 56, '5' => 57, '6' => 58, '7' => 59, '8' => 60, '9' => 61, '.' => 62, '-' => 63, '_' => 64),
        $lowermap = array("A" => "a", "B" => "b", "C" => "c", "D" => "d", "E" => "e", "F" => "f", "G" => "g", "H" => "h", "I" => "i", "J" => "j", "K" => "k", "L" => "l", "M" => "m", "N" => "n", "O" => "o", "P" => "p", "Q" => "q", "R" => "r", "S" => "s", "T" => "t", "U" => "u", "V" => "v", "W" => "w", "X" => "x", "Y" => "y", "Z" => "z")
    ;
    $output = "";
    for ($i = 0; isset($input[$i]); ++$i) {
        $char = $input[$i];
        if (isset($allow[$char]) || null !== $extra && isset($extra[$char])) {
            if ($lower && isset($lowermap[$char])) $output .= $lowermap[$char];
            else $output .= $char;
        }
    }

    // dvojite symboly
    $from = array('|--+|', '|\.\.+|', '|\.-+|', '|-\.+|');
    $to = array('-', '.', '.', '-');
    if (null !== $extra) {
        foreach ($extra as $extra_char => $i) {
            $from[] = '|' . preg_quote($extra_char . $extra_char) . '+|';
            $to[] = $extra_char;
        }
    }
    $output = preg_replace($from, $to, $output);

    // orezani
    $trim_chars = "-_.";
    if (null !== $extra) $trim_chars .= implode('', array_keys($extra));
    $output = trim($output, $trim_chars);

    // return
    return $output;
}

/**
 * Vytvoreni MD5 hashe
 * @param string $str vstupni retezec
 * @param string|null $salt string saltu.. pokud je null, vybere se nahodne a funkce vrati array(hash, salt, puvodni_vstup)
 * @return string|array
 */
function _md5Salt($str, $usesalt = null)
{
    if ($usesalt === null) $salt = _wordGen(8, 3);
    else $salt = $usesalt;
    $hash = md5($salt . $str . $salt);
    if ($usesalt === null) return array($hash, $salt, $str);
    else return $hash;
}

/**
 * Vypocet HMAC-MD5
 *
 * RFC 2104 HMAC implementation for php.
 * Creates an md5 HMAC.
 * Eliminates the need to install mhash to compute a HMAC
 * Hacked by Lance Rushing
 *
 * @param string $data
 * @param string $key
 * @return string
 */
function _md5HMAC($data, $key)
{
    $b = 64;
    if (strlen($key) > $b) $key = pack("H*", md5($key));
    $key = str_pad($key, $b, chr(0x00));
    $ipad = str_pad('', $b, chr(0x36));
    $opad = str_pad('', $b, chr(0x5c));
    $k_ipad = $key ^ $ipad;
    $k_opad = $key ^ $opad;

    return md5($k_opad . pack("H*", md5($k_ipad . $data)));
}

/**
 * Definovani nedefinovanych klicu v poli
 * @param array $array vstupni pole
 * @param array $keys klice a hodnoty k definovani, pokud jiz nejsou (pole)
 * @return array
 */
function _arrayDefineKeys($array, $keys)
{
    if (is_array($array)) {
        foreach ($keys as $key => $value) {
            if (!isset($array[$key])) {
                $array[$key] = $value;
            }
        }

        return $array;
    } else {
        return array();
    }
}

/**
 * Odfiltrovani dane hodnoty z pole
 * @param array $array vstupni pole
 * @param mixed $value_remove hodnota ktera ma byt odstranena
 * @param bool $preserve_keys zachovat ciselnou radu klicu 1/0
 */
function _arrayRemoveValue($array, $value_remove, $preserve_keys = false)
{
    $output = array();
    if (is_array($array)) {
        foreach ($array as $key => $value) {
            if ($value != $value_remove) {
                if (!$preserve_keys) $output[] = $value;
                else $output[$key] = $value;
            }
        }
    }

    return $output;
}

/**
 * Prevest hodnotu na boolean
 * @param mixed $input vstupni hodnota
 * @return bool
 */
function _boolean($input)
{
    return @($input == 1);
}

/**
 * Vratit textovou reprezentaci boolean hodnoty cisla
 * @param mixed $input vstupni hodnota
 * @return string 'true' nebo 'false'
 */
function _booleanStr($input)
{
    if (@($input == true)) return "true";
    return "false";
}

/**
 * Zaskrtnout checkbox na zaklade podminky
 * @return string
 */
function _checkboxActivate($input)
{
    if ($input == 1) return " checked='checked'";
    return '';
}

/**
 * Nacteni odeslaneho checkboxu formularem
 * @param string $name jmeno checkboxu (post)
 * @return int 1/0
 */
function _checkboxLoad($name)
{
    if (isset($_POST[$name])) return 1;
    return 0;
}

/**
 * Orezat text na pozadovanou delku
 * @param string $string vstupni retezec
 * @param int $length pozadovana delka
 * @param bool $convert_entities prevest html entity zpet na originalni znaky a po orezani opet zpet
 * @return string
 */
function _cutStr($string, $length, $convert_entities = true)
{
    if ($length === null) return $string;
    if ($length > 0) {
        if ($convert_entities) $string = _htmlStrUndo($string);
        if (mb_strlen($string) > $length) $string = mb_substr($string, 0, $length - 3) . "...";
        if ($convert_entities) $string = _htmlStr($string);
        return $string;
    }

    return $string;
}

/**
 * Prevest HTML znaky na entity
 * @param string $input vstupni retezec
 * @return string
 */
function _htmlStr($input)
{
    return str_replace(array("&", "<", ">", "\"", "'"), array("&amp;", "&lt;", "&gt;", "&quot;", "&#39;"), $input);
    //return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

/**
 * Prevest entity zpet na HTML znaky
 * @param string $input vstupni retezec
 * @param bool $double provest prevod dvakrat 1/0
 * @return string
 */
function _htmlStrUndo($input, $double = false)
{
    static $map;
    if (!isset($map)) $map = array_flip(get_html_translation_table(HTML_SPECIALCHARS, ENT_QUOTES));
    $output = strtr($input, $map);
    if ($double) $output = _htmlStrUndo($output, false);
    return $output;
}

/**
 * Zakazat pole formulare, pokud NEPLATI podminka
 * @param bool $cond pole je povoleno 1/0
 * @return string
 */
function _inputDisable($cond)
{
    if ($cond != true) return " disabled='disabled'";
    return '';
}

/**
 * Rozpoznat, zda se jedna o URL v absolutnim tvaru
 * @param string $path adresa
 * @return bool
 */
function _isAbsolutePath($path)
{
    $path = @parse_url($path);

    return isset($path['scheme']);
}

/**
 * Vygenerovani nahodneho slova slozeneho z pismen a cislic
 * @param int $len celkova delka slova
 * @param int $numlen delka ciselne casti
 * @return string
 */
function _wordGen($len = 10, $numlen = 3)
{
    if ($len > $numlen) {
        $wordlen = $len - $numlen;
    } else {
        $wordlen = $len;
        $numlen = 0;
    }
    $output = "";

    // priprava poli
    static $letters1 = array("a", "e", "i", "o", "u", "y");
    static $letters2 = array("b", "c", "d", "f", "g", "h", "j", "k", "l", "m", "n", "p", "q", "r", "s", "t", "v", "w", "x", "z");

    // textova cast
    $t = true;
    for ($x = 0; $x < $wordlen; $x++) {
        if ($t) {
            $r = array_rand($letters2);
            $output .= $letters2[$r];
        } else {
            $r = array_rand($letters1);
            $output .= $letters1[$r];
        }
        $t = !$t;
    }

    // ciselna cast
    if ($numlen != 0) $output .= mt_rand(pow(10, $numlen - 1), pow(10, $numlen) - 1);

    // vystup
    return $output;
}

/**
 * Vygenerovani nahodneho slova (pouze pismena) pomoci markov retezce
 *
 * The transition matrix was calculated with Oscar Wilde's The Picture of Dorian Gray.
 * Markov chains produce quite easy to type words.
 * Source: http://code.google.com/p/3dcaptcha/
 *
 * @param int $length pozadovana delka
 * @return string
 */
function _wordGenMarkov($length)
{
    static $matrix = array(0.0001, 0.0218, 0.0528, 0.1184, 0.1189, 0.1277, 0.1450, 0.1458, 0.1914, 0.1915, 0.2028, 0.2792, 0.3131, 0.5293, 0.5304, 0.5448, 0.5448, 0.6397, 0.7581, 0.9047, 0.9185, 0.9502, 0.9600, 0.9601, 0.9982, 1.0000, 0.0893, 0.0950, 0.0950, 0.0950, 0.4471, 0.4471, 0.4471, 0.4471, 0.4784, 0.4821, 0.4821, 0.6075, 0.6078, 0.6078, 0.7300, 0.7300, 0.7300, 0.7979, 0.8220, 0.8296, 0.9342, 0.9348, 0.9351, 0.9351, 1.0000, 1.0000, 0.1313, 0.1317, 0.1433, 0.1433, 0.3264, 0.3264, 0.3264, 0.4887, 0.5454, 0.5454, 0.5946, 0.6255, 0.6255, 0.6255, 0.8022, 0.8022, 0.8035, 0.8720, 0.8753, 0.9545, 0.9928, 0.9928, 0.9928, 0.9928, 1.0000, 1.0000, 0.0542, 0.0587, 0.0590, 0.0840, 0.3725, 0.3837, 0.3879, 0.3887, 0.5203, 0.5208, 0.5211, 0.5390, 0.5435, 0.5550, 0.8183, 0.8191, 0.8191, 0.8759, 0.9376, 0.9400, 0.9629, 0.9648, 0.9664, 0.9664, 1.0000, 1.0000, 0.0860, 0.0877, 0.1111, 0.2533, 0.3017, 0.3125, 0.3183, 0.3211, 0.3350, 0.3355, 0.3378, 0.4042, 0.4381, 0.5655, 0.5727, 0.5842, 0.5852, 0.7817, 0.8718, 0.9191, 0.9201, 0.9530, 0.9652, 0.9792, 0.9998, 1.0000, 0.1033, 0.1037, 0.1050, 0.1057, 0.2916, 0.3321, 0.3324, 0.3324, 0.4337, 0.4337, 0.4337, 0.4912, 0.4912, 0.4912, 0.7237, 0.7274, 0.7274, 0.8545, 0.8569, 0.9150, 0.9986, 0.9986, 0.9990, 0.9990, 1.0000, 1.0000, 0.1014, 0.1017, 0.1024, 0.1028, 0.2725, 0.2729, 0.2855, 0.4981, 0.5770, 0.5770, 0.5770, 0.6184, 0.6191, 0.6384, 0.7783, 0.7797, 0.7797, 0.9249, 0.9663, 0.9688, 0.9923, 0.9923, 0.9937, 0.9937, 1.0000, 1.0000, 0.2577, 0.2579, 0.2580, 0.2581, 0.6967, 0.6970, 0.6970, 0.6970, 0.8648, 0.8648, 0.8650, 0.8661, 0.8667, 0.8670, 0.9397, 0.9397, 0.9397, 0.9509, 0.9533, 0.9855, 0.9926, 0.9926, 0.9929, 0.9929, 1.0000, 1.0000, 0.0324, 0.0478, 0.0870, 0.1267, 0.1585, 0.1908, 0.2182, 0.2183, 0.2193, 0.2193, 0.2309, 0.2859, 0.3426, 0.6110, 0.6501, 0.6579, 0.6583, 0.6923, 0.8211, 0.9764, 0.9781, 0.9948, 0.9949, 0.9965, 0.9965, 1.0000, 0.1276, 0.1276, 0.1276, 0.1276, 0.4286, 0.4286, 0.4286, 0.4286, 0.4337, 0.4337, 0.4337, 0.4337, 0.4337, 0.4337, 0.6684, 0.6684, 0.6684, 0.6684, 0.6684, 0.6684, 1.0000, 1.0000, 1.0000, 1.0000, 1.0000, 1.0000, 0.0033, 0.0059, 0.0100, 0.0109, 0.5401, 0.5443, 0.5477, 0.5485, 0.7149, 0.7149, 0.7149, 0.7316, 0.7333, 0.9247, 0.9264, 0.9273, 0.9273, 0.9289, 0.9791, 0.9816, 0.9824, 0.9824, 0.9833, 0.9833, 1.0000, 1.0000, 0.0850, 0.0865, 0.0874, 0.1753, 0.3439, 0.3725, 0.3744, 0.3746, 0.5083, 0.5083, 0.5192, 0.6784, 0.6840, 0.6848, 0.8088, 0.8128, 0.8128, 0.8147, 0.8326, 0.8511, 0.8743, 0.8817, 0.9054, 0.9054, 1.0000, 1.0000, 0.1562, 0.1760, 0.1774, 0.1776, 0.5513, 0.5517, 0.5517, 0.5520, 0.6352, 0.6352, 0.6352, 0.6369, 0.6486, 0.6499, 0.7717, 0.8230, 0.8230, 0.8337, 0.8697, 0.8703, 0.9376, 0.9376, 0.9378, 0.9378, 1.0000, 1.0000, 0.0255, 0.0265, 0.0682, 0.2986, 0.4139, 0.4204, 0.6002, 0.6009, 0.6351, 0.6360, 0.6507, 0.6672, 0.6679, 0.6786, 0.7718, 0.7723, 0.7732, 0.7873, 0.8364, 0.9715, 0.9753, 0.9797, 0.9803, 0.9804, 0.9997, 1.0000, 0.0050, 0.0089, 0.0183, 0.0379, 0.0410, 0.1451, 0.1494, 0.1514, 0.1654, 0.1656, 0.1866, 0.2171, 0.2821, 0.4272, 0.4761, 0.4926, 0.4927, 0.6434, 0.6722, 0.7195, 0.9126, 0.9332, 0.9913, 0.9925, 0.9999, 1.0000, 0.1596, 0.1688, 0.1688, 0.1688, 0.3799, 0.3799, 0.3799, 0.4011, 0.4827, 0.4827, 0.4833, 0.6081, 0.6087, 0.6090, 0.7353, 0.7953, 0.7953, 0.8804, 0.9181, 0.9584, 0.9952, 0.9952, 0.9952, 0.9952, 1.0000, 1.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 1.0000, 1.0000, 1.0000, 1.0000, 1.0000, 1.0000, 0.0902, 0.0938, 0.1003, 0.1555, 0.4505, 0.4606, 0.4705, 0.4740, 0.5928, 0.5928, 0.6018, 0.6201, 0.6402, 0.6605, 0.7619, 0.7666, 0.7671, 0.8125, 0.8645, 0.9029, 0.9226, 0.9298, 0.9319, 0.9319, 0.9996, 1.0000, 0.0584, 0.0598, 0.0903, 0.0912, 0.2850, 0.2870, 0.2883, 0.3902, 0.5057, 0.5058, 0.5165, 0.5271, 0.5400, 0.5447, 0.6525, 0.6762, 0.6792, 0.6792, 0.7512, 0.9370, 0.9843, 0.9851, 0.9953, 0.9953, 0.9999, 1.0000, 0.0416, 0.0419, 0.0466, 0.0467, 0.1673, 0.1696, 0.1697, 0.6314, 0.7003, 0.7003, 0.7003, 0.7142, 0.7150, 0.7160, 0.8626, 0.8626, 0.8627, 0.9023, 0.9255, 0.9498, 0.9746, 0.9746, 0.9812, 0.9812, 0.9998, 1.0000, 0.0141, 0.0308, 0.0668, 0.0877, 0.1241, 0.1282, 0.1874, 0.1874, 0.2191, 0.2192, 0.2210, 0.3626, 0.3794, 0.4618, 0.4632, 0.5097, 0.5097, 0.6957, 0.8373, 0.9949, 0.9949, 0.9961, 0.9963, 0.9982, 0.9984, 1.0000, 0.0740, 0.0740, 0.0740, 0.0740, 0.8423, 0.8423, 0.8423, 0.8423, 0.9486, 0.9486, 0.9486, 0.9486, 0.9486, 0.9491, 0.9836, 0.9836, 0.9836, 0.9849, 0.9849, 0.9849, 0.9907, 0.9907, 0.9907, 0.9907, 1.0000, 1.0000, 0.2785, 0.2789, 0.2795, 0.2823, 0.4088, 0.4118, 0.4118, 0.6070, 0.7774, 0.7774, 0.7782, 0.7840, 0.7840, 0.8334, 0.9704, 0.9704, 0.9704, 0.9861, 0.9996, 1.0000, 1.0000, 1.0000, 1.0000, 1.0000, 1.0000, 1.0000, 0.0741, 0.0741, 0.1963, 0.1963, 0.2519, 0.2741, 0.2741, 0.3333, 0.4000, 0.4000, 0.4000, 0.4000, 0.4000, 0.4000, 0.4037, 0.6741, 0.7667, 0.7667, 0.7667, 0.9667, 0.9963, 0.9963, 0.9963, 0.9963, 1.0000, 1.0000, 0.0082, 0.0130, 0.0208, 0.0225, 0.1587, 0.1608, 0.1613, 0.1686, 0.2028, 0.2028, 0.2032, 0.2322, 0.2391, 0.2417, 0.8232, 0.8314, 0.8314, 0.8409, 0.9529, 0.9965, 0.9965, 0.9965, 0.9991, 0.9996, 1.0000, 1.0000, 0.0678, 0.0678, 0.0763, 0.0763, 0.7373, 0.7373, 0.7373, 0.7458, 0.8729, 0.8729, 0.8729, 0.8814, 0.8814, 0.8814, 0.9237, 0.9237, 0.9237, 0.9237, 0.9237, 0.9407, 0.9492, 0.9492, 0.9492, 0.9492, 0.9492, 1.0000);

    $output = '';
    $char = mt_rand(0, 25);

    for ($i = 0; $i < $length; ++$i) {

        // add char
        $output .= chr($char + 65 + 32);

        // get next char
        $next = mt_rand(0, 10000) / 10000;
        for ($j = 0; $j < 26; ++$j) {
            if ($next < $matrix[$char * 26 + $j]) {
                $char = $j;
                break;
            }
        }

    }

    return $output;
}


/**
 * Rozebrat retezec na parametry a vratit jako pole
 * @param string $input vstupni retezec
 * @return array
 */
function _parseStr($input)
{
    // nastaveni funkce
    static $sep = ',', $quote = '"', $quote2 = '\'', $esc = '\\', $ws = array("\n" => 0, "\r" => 1, "\t" => 2, " " => 3);

    // priprava
    $output = array();
    $input = trim($input);
    $last = strlen($input) - 1;
    $val = '';
    $ws_buffer = '';
    $val_quote = null;
    $mode = 0;

    // vyhodnoceni
    for ($i = 0; isset($input[$i]); ++$i) {

        $char = $input[$i];
        switch ($mode) {

                /* ----  najit zacatek argumentu  ---- */
            case 0:
                if (!isset($ws[$char])) {
                    if ($char === $sep) $output[] = null; // prazdny argument
                    else {
                        --$i;
                        $mode = 1;
                        $val = '';
                        $val_fc = true;
                        $escaped = false;
                    }
                }
                break;

                /* ----  nacist hodnotu  ---- */
            case 1:

                // prvni znak - rozpoznat uvozovky
                if ($val_fc) {
                    $val_fc = false;
                    if ($char === $quote || $char === $quote2) {
                        $val_quote = $char;
                        break;
                    } else {
                        $val_quote = null;
                    }
                }

                // zpracovat znak
                if (isset($val_quote)) {

                    // v retezci s uvozovkami
                    if ($char === $esc) {

                        // escape znak
                        if ($escaped) {
                            // escaped + escaped
                            $val .= $char;
                            $escaped = false;
                        } else {
                            // aktivovat
                            $escaped = true;
                        }

                    } elseif ($char === $val_quote) {

                        // uvozovka
                        if ($escaped) {
                            // escaped uvozovka
                            $val .= $char;
                            $escaped = false;
                        } else {
                            // konec hodnoty
                            $output[] = $val;
                            $mode = 2; // najit konec
                        }

                    } else {
                        // normalni znak
                        if ($escaped) {
                            // escapovany normalni znak
                            $val .= $esc;
                            $escaped = false;
                        }
                        $val .= $char;
                    }

                } else {

                    // mimo uvozovky
                    if ($char === $sep || $i === $last) {
                        // konec hodnoty
                        $ws_buffer = '';
                        if ($i === $last) $val .= $char;
                        $output[] = $val;
                        $mode = 0;
                    } elseif (isset($ws[$char])) {
                        // bile znaky
                        $ws_buffer .= $char;
                    } else {
                        // normal znak
                        if ($ws_buffer !== '') {
                            // vyprazdnit buffer bilych znaku
                            $val .= $ws_buffer;
                            $ws_buffer = '';
                        }
                        $val .= $char;
                    }

                }

                break;

                /* ----  najit konec argumentu  ---- */
            case 2:
                if ($char === $sep) $mode = 0;
                break;

        }

    }

    // vystup
    return $output;
}


/**
 * Vyhodnoceni relativnich casti cesty
 * @param string $path cesta
 * @return string
 */
function _parsePath($path)
{
    $path = _arrayRemoveValue(explode("/", trim($path, "/")), ".");
    $loop = true;

    while ($loop) {

        $moverindex = -1;

        for ($i = count($path) - 1; $i >= 0; --$i) {
            if ($path[$i] == "..") {
                $moverindex = $i;
                break;
            }
        }

        if ($moverindex != -1) {

            $collision = -1;

            for ($i = $moverindex - 1; $i >= 0; --$i) {
                if ($path[$i] != "..") {
                    $collision = $i;
                    break;
                }
            }

            if ($collision != -1) {
                unset($path[$moverindex], $path[$collision]);
                $path = array_values($path);;
            } else {
                $loop = false;
            }

        } else {
            $loop = false;
        }

    }

    $output = implode("/", $path) . "/";
    if ($output == "/") {
        $output = "./";
    }

    return $output;
}


/**
 * Overit, zda neobsahuje adresa skodlivy kod
 * @param string $url adresa
 * @return bool
 */
function _isSafeUrl($url)
{
    if (mb_strtolower(mb_substr($url, 0, 11)) == "javascript:" or mb_strtolower(mb_substr($url, 0, 5)) == "data:") return false;
    return true;
}


/**
 * Odstraneni vsech lomitek z konce retezce
 * @param string $string vstupni retezec
 * @return string
 */
function _removeSlashesFromEnd($string)
{
    while (mb_substr($string, -1) == "/") {
        $string = mb_substr($string, 0, mb_strlen($string) - 1);
    }

    return $string;
}


/**
 * Obnoveni hodnoty prvku podle stavu $_POST
 * @param string $name nazev klice v post
 * @param string|null $else vychozi hodnota nebo null
 * @param bool $noparam nepouzivat 'value=' pri vypisu hodnoty 1/0
 * @param bool $cond podminka pro pouziti nalezeneho post zaznamu 1/0
 * @param bool $else_entities prevest entity pro $else hodnotu 1/0
 * @return string
 */
function _restorePostValue($name, $else = null, $noparam = false, $cond = true, $else_entities = true)
{
    if ($noparam) {
        $param_start = "";
        $param_end = "";

    } else {
        $param_start = " value='";
        $param_end = "'";
    }

    if (isset($_POST[$name]) /* && $_POST[$name] != ""*/ && $cond) return $param_start . _htmlStr($_POST[$name]) . $param_end;
    elseif (isset($else)) return $param_start . ($else_entities ? _htmlStr($else) : $else) . $param_end;
    return '';
}


/**
 * Obnoveni hodnoty prvku podle stavu $_GET
 * @param string $name nazev klice v get
 * @param string|null $else vychozi hodnota nebo null
 * @return string
 */
function _restoreGetValue($name, $else = null)
{
    if (isset($_GET[$name]) and $_GET[$name] != "") {
        return " value='" . _htmlStr($_GET[$name]) . "'";
    } else {
        if ($else != null) {
            return " value='" . _htmlStr($else) . "'";
        }
    }
}



/**
 * Ziskat hodnotu z $_GET
 * @param string $key klic
 * @param mixed $default vychozi hodnota
 * @param bool $allow_array povolit pole 1/0
 * @return mixed
 */
function _get($key, $default = null, $allow_array = false)
{
    if (isset($_GET[$key]) && ($allow_array || !is_array($_GET[$key]))) {
        return $_GET[$key];
    }

    return $default;
}



/**
 * Ziskat hodnotu z $_POST
 * @param string $key klic
 * @param mixed $default vychozi hodnota
 * @param bool $allow_array povolit pole 1/0
 * @return mixed
 */
function _post($key, $default = null, $allow_array = false)
{
    if (isset($_POST[$key]) && ($allow_array || !is_array($_POST[$key]))) {
        return $_POST[$key];
    }

    return $default;
}



/**
 * Ziskat polozky z pole $_POST jako HTML kod skrytych poli formulare nebo jako pole
 * @param bool $array vratit pole namisto HTML kodu skrytych poli formulare 1/0
 * @param string|null $filter pokud neni null, budou do vysledku zahrnuty pouze klice zacinajici timto retezcem
 * @param array|null $skip pokud neni null, budou preskoceny vsechny klice uvedene v tomto poli
 * @return string|array
 */
function _getPostdata($array = false, $filter = null, $skip = null)
{
    $output = array();
    $counter = 0;
    if (isset($filter)) $filter_len = mb_strlen($filter);
    if (isset($skip)) $skip = array_flip($skip);
    foreach ($_POST as $key => $value) {
        if (isset($filter) && mb_substr($key, 0, $filter_len) != $filter) continue;
        if (isset($skip, $skip[$key])) continue;
        if (!$array) $output[] = _getPostdata_processItem($key, $value);
        else $output[] = array($key, $value);
        ++$counter;
    }
    if (!$array) $output = implode("\n", $output);
    return $output;
}


/**
 * @internal
 */
function _getPostdata_processItem($key, $value, $pkeys = array())
{
    // zpracovat pole
    if (is_array($value)) {
        $output = array();
        foreach($value as $vkey => $vvalue) $output[] = _getPostdata_processItem($key, $vvalue, array_merge($pkeys, array($vkey)));

        return implode("\n", $output);
    }

    // hodnota
    $name = _htmlStr($key);
    if (!empty($pkeys)) $name .= _htmlStr('[' . implode('][', $pkeys) . ']');
    return "<input type='hidden' name='" . $name . "' value='" . _htmlStr($value) . "' />";
}


/**
 * Validace e-mailove adresy
 * @param string $email e-mailova adresa
 * @return bool
 */
function _validateEmail($email)
{
    $isValid = true;
    $atIndex = mb_strrpos($email, "@");
    if (is_bool($atIndex) && !$atIndex) {
        $isValid = false;
    } else {
        $domain = mb_substr($email, $atIndex + 1);
        $local = mb_substr($email, 0, $atIndex);
        $localLen = mb_strlen($local);
        $domainLen = mb_strlen($domain);
        if ($localLen < 1 || $localLen > 64) {
            // local part length exceeded
            $isValid = false;
        } else
            if ($domainLen < 1 || $domainLen > 255) {
                // domain part length exceeded
                $isValid = false;
            } else
                if ($local[0] == '.' || $local[$localLen - 1] == '.') {
                    // local part starts or ends with '.'
                    $isValid = false;
                } else
                    if (preg_match('/\\.\\./', $local)) {
                        // local part has two consecutive dots
                        $isValid = false;
                    } else
                        if (!preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain)) {
                            // character not valid in domain part
                            $isValid = false;
                        } else
                            if (preg_match('/\\.\\./', $domain)) {
                                // domain part has two consecutive dots
                                $isValid = false;
                            } else
                                if (!preg_match('/^[A-Za-z0-9\\-\\._]+$/', $local)) $isValid = false; // character not valid in local part
        /*if (!preg_match('/^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$/', str_replace("\\\\", "", $local))) {
        // character not valid in local part unless
        // local part is quoted
        if (!preg_match('/^"(\\\\"|[^"])+"$/', str_replace("\\\\", "", $local))) {
        $isValid = false;
        }
        }*/
        if (function_exists("checkdnsrr")) {
            if ($isValid && !(checkdnsrr($domain, "MX") || checkdnsrr($domain, "A"))) {
                // domain not found in DNS
                $isValid = false;
            }
        }
    }

    return $isValid;
}


/**
 * Kontrola, zda je zadana URL (v absolutnim tvaru zacinajici http://) platna
 * @param string $url adresa
 * @return bool
 */
function _validateURL($url)
{
    return (preg_match('|^https?:\/\/[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,6}((:[0-9]{1,5})?\/.*)?$|i', $url) === 1);
}


/**
 * Odstraneni nezadoucich odradkovani a mezer z retezce
 * @param string $string vstupni retezec
 * @return string
 */
function _wsTrim($string)
{
    $from = array("|(\r\n){3,}|s", "|  +|s");
    $to = array("\r\n\r\n", " ");

    return preg_replace($from, $to, trim($string));
}


/**
 * Zjistit maximalni moznou celkovou velikost uploadu
 * @param bool $get_mb ziskat cislo jiz prevedene na megabajty
 * @return number|null cislo v B/mB nebo null (= neomezeno, resp. neznamy limit)
 */
function _getUploadLimit($get_mb = false)
{
    static $result;
    if (!isset($result)) {
        $limit_lowest = null;
        $opts = array('upload_max_filesize', 'post_max_size', 'memory_limit');
        for ($i = 0; isset($opts[$i]); ++$i) {
            $limit = _phpIniLimit($opts[$i]);
            if (isset($limit) && (!isset($limit_lowest) || $limit < $limit_lowest)) $limit_lowest = $limit;
        }
        if (isset($limit_lowest)) $result = ($get_mb ? round($limit_lowest / 1048576, 1) : $limit_lowest);
        else $result = null;
    }

    return $result;
}


/**
 * Zjistit datovy limit dane konfiguracni volby PHP
 * @return number|null cislo v bajtech nebo null (= neomezeno)
 */
function _phpIniLimit($opt)
{
    // get ini value
    $ini = ini_get($opt);

    // check value
    if ($ini === '') {
        // no limit?
        return null;
    }

    // extract type, process number
    $last = substr($ini, -1);
    $ini += 0;

    // parse ini value
    switch ($last) {
        case 'M':
        case 'm':
            $ini *= 1048576;
            break;
        case 'K':
        case 'k':
            $ini *= 1024;
            break;
        case 'G':
        case 'g':
            $ini *= 1073741824;
            break;
    }

    // return
    return $ini;
}


/**
 * Zjistit zda je den podle casu vychozu a zapadu slunce
 * @param int|null $time timestamp nebo null (= aktualni)
 * @param bool $get_times navratit casy misto vyhodnoceni, ve formatu array(time, sunrise, sunset)
 * @return bool|array
 */
function _isDayTime($time = null, $get_times = false)
{
    // priprava casu
    if (!isset($time)) $time = time();
    $sunrise = date_sunrise($time, SUNFUNCS_RET_TIMESTAMP, 50.5, 14.26, 90.583333, date('Z') / 3600);
    $sunset = date_sunset($time, SUNFUNCS_RET_TIMESTAMP, 50.5, 14.26, 90.583333, date('Z') / 3600);

    // navrat vysledku
    if ($get_times) return array($time, $sunrise, $sunset);
    if ($time >= $sunrise && $time < $sunset) return true;
    return false;
}


/**
 * Vytvorit docasny soubor, automaticky smazan na konci skriptu
 * Pokud soubor uzavrete a smazete drive, pouzijte funkci {@link _tmpFileCleaned}
 * @param string $mode mod ve kterem se ma soubor nacist, viz fopen()
 * @return array|bool pole ve formatu array(handle, path) nebo false pri selhani
 */
function _tmpFile($mode = 'wb+')
{
    // inicializace
    static $init = false;
    if (!$init) {
        $init = true;
        @register_shutdown_function('_tmpFile_clean', false);
    }

    // vytvoreni souboru
    $path = _tmp_dir . uniqid('', false) . '.tmp';
    $handle = fopen($path, $mode);
    _tmpFile_clean(array(realpath($path), $handle)); // zaregistrovat pro smazani

    return array($handle, $path);
}

/**
 * Oznacit docasny soubor jako jiz uzavreny a smazany
 * @param string $path cesta k docasnemu souboru
 */
function _tmpFileCleaned($path)
{
    _tmpFile_clean($path, true);
}


/**
 * Interni callback pro spravu registrace a odstraneni
 * docasnych souboru vytvarenych funkci {@link _tmpFile}
 * @internal
 */
function _tmpFile_clean($path, $remove = false)
{
    static $list = array();
    if ($path === false) {
        // smazat
        foreach ($list as $path => $handle) {
            @fclose($handle);
            @unlink($path);
        }
    } elseif ($remove) {
        // jiz smazano
        unset($list[$path]);
    } else {
        // zaregistrovat
        $list[$path[0]] = $path[1];
    }
}


/**
 * Rekurzivne vyprazdnit adresar
 * @param string $dir cesta k adresari vcetne lomitka
 * @param bool $check_only pouze zkontrolovat pravo k zapisu 1/0
 * @return bool|string true pri uspechu, jinak nazev adresare/souboru ktery vyvolal chybu
 */
function _emptyDir($dir, $check_only = true, $recursing = false)
{
    // skenovat adersar
    $handle = opendir($dir);
    if (!is_resource($handle)) return $dir;
    $dirs = array();
    while (false !== ($item = readdir($handle))) {

        // preskocit blbosti
        if ($item === '.' || $item === '..') continue;

        // soubor
        if (is_file($dir . $item)) {
            if ($check_only) {
                if (!is_writeable($dir . $item)) return $dir . $item;
            } elseif (!unlink($dir . $item)) return $dir . $item;
            continue;
        }

        // adresar
        $dirs[] = $dir . $item . '/';

    }
    closedir($handle);

    // zpracovat podadresare
    for($i = 0; isset($dirs[$i]); ++$i)
        if (($s_dir = _emptyDir($dirs[$i], $check_only, true)) !== true) return $s_dir;

    // smazani/kontrola adresare
    if ($recursing)
        if (!$check_only && !rmdir($dir) || $check_only && !is_writeable($dir)) return $dir;

    // vse ok
    return true;
}


/**
 * Zjistit zakladani adresu systemu
 * V normalnich situacich se pouziva konstanta _url
 * @return string|bool false pri selhani, jinak URL vcetne koncoveho /
 */
function _getBaseUrl()
{
    // zjistit cestu z url
    $path = parse_url($_SERVER['REQUEST_URI']);
    if (!isset($path['path'])) return false;
    $path = $path['path'];

    // najit posledni lomitko
    $lslash = strrpos($path, '/');
    if ($lslash === false) return false;

    // uriznout nazev souboru
    if ($lslash + 1 !== strlen($path)) {
        $path = substr($path, 0, $lslash + 1);
    }

    return 'http://' . $_SERVER['SERVER_NAME'] . (($_SERVER['SERVER_PORT'] != 80) ? $_SERVER['SERVER_PORT'] : '') . '/' . _parsePath($path . _indexroot);
}


/**
 * Zjistit, zda je nazev souboru bezpecny
 * @param string $fname nazev souboru
 * @return bool
 */
function _isSafeFile($fname)
{
    // init
    static $unsafe_ext = array("php", "php3", "php4", "php5", "phtml", "shtml", "asp", "py", "cgi", "htaccess");
    static $unsafe_ext_pattern;
    if (!isset($unsafe_ext_pattern)) $unsafe_ext_pattern = implode('|', $unsafe_ext);

    // match
    return (preg_match('/\.(' . $unsafe_ext_pattern . ')(\..*){0,1}$/is', trim($fname)) === 0);
}



/**
 * Sestavit kod inputu pro vyber casu
 * @param string $name identifikator casove hodnoty
 * @param int|null|bool $timestamp cas, -1 (= aktualni) nebo null (= nevyplneno)
 * @param bool $updatebox zobrazit checkbox pro nastaveni na aktualni cas pri ulozeni
 * @param bool $updateboxchecked zaskrtnuti checkboxu 1/0
 * @return string
 */
function _editTime($name, $timestamp = null, $updatebox = false, $updateboxchecked = false)
{
    global $_lang;
    if (-1 === $timestamp) $timestamp = time();
    if (null !== $timestamp) $timestamp = getdate($timestamp);
    else $timestamp = array('seconds' => '', 'minutes' => '', 'hours' => '', 'mday' => '', 'mon' => '', 'year' => '');
    $return = "<input type='text' size='1' maxlength='2' name='{$name}[tday]' value='" . $timestamp['mday'] . "' />.<input type='text' size='1' maxlength='2' name='{$name}[tmonth]' value='" . $timestamp['mon'] . "' />&nbsp;&nbsp;<input type='text' size='3' maxlength='4' name='{$name}[tyear]' value='" . $timestamp['year'] . "' />&nbsp;&nbsp;<input type='text' size='1' maxlength='2' name='{$name}[thour]' value='" . $timestamp['hours'] . "' />:<input type='text' size='1' maxlength='2' name='{$name}[tminute]' value='" . $timestamp['minutes'] . "' />:<input type='text' size='1' maxlength='2' name='{$name}[tsecond]' value='" . $timestamp['seconds'] . "' />&nbsp;&nbsp;<small>" . $_lang['admin.content.form.timehelp'] . "</small>";
    if ($updatebox) {
        if ($updateboxchecked) $updateboxchecked = " checked='checked'";
        else $updateboxchecked = "";
        $return .= "&nbsp;&nbsp;<label><input type='checkbox' name='{$name}[tupdate]' value='1'" . $updateboxchecked . " /> " . $_lang['admin.content.form.timeupdate'] . "</label>";
    }

    return $return;
}

/**
 * Nacist casovou hodnotu vytvorenou a odeslanou pomoci {@link _editTime}
 * @param string $name identifikator casove hodnoty
 * @param int $default vychozi casova hodnota pro pripad chyby
 * @return int|null
 */
function _loadTime($name, $default = null)
{
    if (!isset($_POST[$name]) || !is_array($_POST[$name])) return $default;
    if (!isset($_POST[$name]['tupdate'])) {
        $day = intval($_POST[$name]['tday']);
        $month = intval($_POST[$name]['tmonth']);
        $year = intval($_POST[$name]['tyear']);
        $hour = intval($_POST[$name]['thour']);
        $minute = intval($_POST[$name]['tminute']);
        $second = intval($_POST[$name]['tsecond']);
        if (checkdate($month, $day, $year) and $hour >= 0 and $hour < 24 and $minute >= 0 and $minute < 60 and $second >= 0 and $second < 60) {
            return mktime($hour, $minute, $second, $month, $day, $year);
        } else return $default;
    }

    return time();
}
