<?php

// konstanty
define('_backup_idt', md5('THIS_IS_SL_BACKUP_FILE'));
define('_backup_db', 0);
define('_backup_partial', 1);
define('_backup_full', 2);

/**
 * [ADMIN] Backup API - vytvorit zalohu
 * @param int $type typ zalohy (0 = jen databaze, 1 = castecna, 2 = kompletni)
 * @param int $compress nastaveni komprese (0 = bez, 1 = rychla, 2 = nejlepsi)
 * @param string|resource|null $fname nazev souboru pro odeslani na vystup, resource jiz otevreneho souboru nebo null (= neodesilat, vratit)
 * @param string|null $note textova poznamka nebo null
 * @param array|null $extra_dirs extra adresare pro pridani (pouze typ >= 1) nebo null
 * @return string|null pokud je $fname null, vrati instanci KZip archivu, v pripade kompletni zalohy jako pole array(kzip_inst, script_php_code)), jinak null
 */
function _backupCreate($type, $compress, $fname = null, $note = null, $extra_dirs = null)
{
    // nastaveni komprese
    if ($compress === 1) $compress = 3; // rychla komprese
    elseif ($compress === 2) $compress = 9; // nejlepsi komprese
    else $compress = null; // bez komprese

    // vychozi konfigurace
    $installer = false;
    $dirs_root = false;
    $dirs_ensure = false;
    $dirs_merge = null;
    $add_voidfile = false;
    $add_cronlock = false;
    $version = _systemversion;
    $void_fname = '___void';
    $ext = _backupExt($type);

    // konfigurace dle typu
    switch ($type) {

            // jen databaze
        case _backup_db:
            $dirs = array();
            $version = _checkVersion('database', null, true);
            $version = $version[0];
            break;

            // castecna zaloha
        case _backup_partial:
            $dirs = array('pictures', 'plugins');
            $dirs_ensure = true;
            if (isset($extra_dirs)) {
                $dirs = array_merge($dirs, $extra_dirs);
                $dirs_merge = $extra_dirs;
            }
            break;

            // uplna zaloha
        case _backup_full:
            $installer = true;
            $dirs_root = true;
            $dirs = array('admin', 'pictures', 'plugins', 'remote', 'require', 'data/installer');
            $dirs_ensure = true;
            $add_voidfile = true;
            $add_cronlock = true;
            if (isset($extra_dirs)) {
                $dirs = array_merge($dirs, $extra_dirs);
                $dirs_merge = $extra_dirs;
            }
            break;

            // spatny typ
        default:
            trigger_error('Neplatny typ zalohy', E_USER_WARNING);

            return;

    }

    /* ---  tvorba zalohy  --- */

    // hlavicky
    if (is_string($fname)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/force-download');
        header('Content-Disposition: attachment; filename="' . $fname . '.' . $ext . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    }

    // tvorba a konfigurace archivu
    $kzip = new KZip;
    if (!$kzip->has_zlib) $compress = null; // komprese neni dostupna
    elseif (isset($compress)) $kzip->setComprLevel($compress);
    $kzip->vars = array(
        'idt' => _backup_idt, // oznaceni archivu se zalohou
        'version' => $version, // verze zalohy
        'system_version' => _systemversion, // verze systemu
        'system_state' => _systemstate, // stav systemu
        'system_revision' => _systemstate_revision, // revize systemu
        'type' => $type, // typ zalohy
        'void' => $void_fname, // nazev prazdnych souboru
        'time' => time(), // cas vytvoreni
        'url' => _url, // adresa systemu, kde byla zaloha vygenerovana
        'size' => 0, // velikost vsech vstupnich souboru (doplneni nize)
        'note' => (isset($note) ? strval($note) : null), // textova poznamka
        'merge' => $dirs_merge, // adresare ktere se slucuji
    );

    // databaze - struktura a data
    $dbdump = new DBDump;

    $struct = $dbdump->exportTables();
    $kzip->addFile($struct[1], '/database/struct');

    $data = $dbdump->exportData();
    $kzip->addFile($data[1], '/database/data');

    // adresare
    if (!empty($dirs)) {
        for($i = 0; isset($dirs[$i]); ++$i) {
            $localDirPath = _indexroot . $dirs[$i] . '/';
            $archiveDirPath = '/files/' . $dirs[$i] . '/';
            $kzip->addDir(
                $localDirPath,
                $archiveDirPath,
                true,
                ('pictures' === $dirs[$i]) ? '_backupFilterThumbnails' : null
            );
        }
    }

    // soubory z korenoveho adresare
    if ($dirs_root) {
        $kzip->addDir(_indexroot, '/files/');
        $kzip->removeFile('/files/.htaccess'); // soubor .htaccess se nezalohuje
        $kzip->removeFile('/files/config.php'); // soubor config.php se nezalohuje
    }

    // voidfile
    if ($add_voidfile) {
        $kzip->addFile(_void_file, '/files/data/');
    }

    // cron lock file
    if ($add_cronlock) {
        $kzip->addfile(_indexroot . 'data/cron.lock', '/files/data/');
    }

    // spocitat velikost vstupnich souboru
    $files = $kzip->listAll();
    for($i = 0; isset($files[$i]); ++$i) {
        $kzip->vars['size'] += $kzip->getFileSize($files[$i]);
    }

    // vynutit existenci adresaru
    if ($dirs_ensure) {
        _backupEnsureDirs($kzip, $void_fname, $type, !in_array('upload', $dirs));
    }

    // vlozeni instalatoru
    if ($installer) {

        global $_lang;
        require _indexroot . 'config.php';

        if (!isset($locale)) $locale = array('czech', 'utf8', 'cz_CZ');
        if (!isset($timezone)) $timezone = 'Europe/Prague';

        $inst = file_get_contents(_indexroot . 'data/installer/script.php');
        $inst = '<?php ' . substr($inst, strpos($inst, '/* @@@begin@@@ */') + 17);
        $inst = str_replace(
            array(
                '$__locale',
                '$__timezone',
                '$__time_format',
                '/* @@@core@@@ */',
                '/* @@@kzip@@@ */',
                '/* @@@dbdump@@@ */',
                '/* @@@lang@@@ */',
                '/* @@@s_states@@@ */',
            ),
            array(
                var_export($locale, true),
                var_export($timezone, true),
                var_export(_time_format, true),
                substr(file_get_contents(_indexroot . 'require/load.php'), 5),
                substr(file_get_contents(_indexroot . 'require/class/kzip.php'), 5),
                substr(file_get_contents(_indexroot . 'require/class/dbdump.php'), 5),
                '$_lang = ' . var_export($_lang['installer'] + array('type.full' => $_lang['admin.other.backup.backup.full'], 'dbdump' => $_lang['dbdump'], 'global.continue' => $_lang['global.continue'], 'global.cancel' => $_lang['global.cancel'], 'global.note' => $_lang['global.note']), true) . ';',
                '$_sys_states = ' . var_export(SL::$states, true) . ';',
            ), $inst);

        if (is_string($fname)) echo $inst;

    }

    // sestaveni archivu
    if (isset($fname)) {
        // do vystupu ci souboru
        if (is_string($fname)) $kzip->packToOutput(isset($compress));
        else {
            if ($installer) fwrite($fname, $inst);
            $kzip->packToFile(null, false, isset($compress), $fname);
        }
        $kzip->free();
    } elseif ($installer) {
        // vratit kzip + kod instalatoru
        return array($kzip, $inst);
    } else return $kzip;
}

/**
 * [ADMIN] Backup API - zajistit existenci prazdnych adresaru
 * @param KZip $kzip instance kzip archivu
 * @param string $void_fname nazev prazdneho souboru v archivu
 * @param bool $no_upload vynechat upload adresar 1/0
 * @return array
 */
function _backupEnsureDirs($kzip, $void_fname, $type = _backup_full, $no_upload = false)
{
    $dirs = array(
        'data/backup/',
        'data/tmp/',
        'pictures/articles/',
        'pictures/avatars/',
        'pictures/galleries/',
        'pictures/groupicons/',
        'pictures/thumb/',
        'plugins/admin/',
        'plugins/extend/',
        'plugins/hcm/',
        'plugins/common/',
        'upload/',
        );

    for ($i = (($type === _backup_full) ? 0 : 2); isset($dirs[$i]); ++$i) {
        if ($no_upload && 10 === $i) continue;
        if (sizeof($kzip->listFilesOnPath('/files/' . $dirs[$i])) === 0) $kzip->addFile(_void_file, '/files/' . $dirs[$i] . $void_fname);
    }
}

/**
 * [ADMIN] Backup API - zkontrolovat soubor se zalohou (castecnou nebo databazi)
 * @param string $path cesta k souboru
 * @param array|null $allowed_types povolene typy zaloh (_backup_x) nebo null (= vsechny)
 * @param bool $get_vars vratit atributy archivu namisto true pri uspechu 1/0
 * @return string|array|bool chybova hlaska pri chybe, jinak true nebo array (dle stavu get_vars)
 */
function _backupCheckFile($path, $allowed_types = null, $get_vars = false)
{
    global $_lang;

    // kontrola archivu
    $kzip = new KZip($path);
    if (!empty($kzip->error)) return str_replace('*errstr*', _htmlStr($kzip->error), $_lang['admin.other.backup.restore.upload.err.load']);

    // kontrola formatu a verze
    $req_keys = array('idt', 'version', 'system_version', 'system_state', 'system_revision', 'type', 'void', 'time', 'url', 'size', 'note', 'merge');
    $poss_types = array(_backup_db => 0, _backup_partial => 1, _backup_full => 2);
    do {

        // kontrola pole s hodnotami
        if (!is_array($kzip->vars)) break;
        for($i = 0; isset($req_keys[$i]); ++$i)
            if (!array_key_exists($req_keys[$i], $kzip->vars)) break 2;

        // kontrola identifikatoru a typu
        if ($kzip->vars['idt'] !== _backup_idt) break;
        if (!isset($poss_types[$kzip->vars['type']])) break;

        // kontrola typu
        if (isset($allowed_types) && !in_array($kzip->vars['type'], $allowed_types)) return $_lang['admin.other.backup.restore.upload.err.type'];

        // kontrola verze
        if ($kzip->vars['type'] === _backup_db && !_checkVersion('database', $kzip->vars['version'])) break;
        if ($kzip->vars['type'] !== _backup_db && $kzip->vars['version'] != _systemversion) break;

        // vse ok
        $kzip->free();
        if ($get_vars) return $kzip->vars;
        return true;

    } while (false);

    // neplatny format ci verze
    $kzip->free();

    return $_lang['admin.other.backup.restore.upload.err.format'];
}

/**
 * [ADMIN] Backup API - filtrovat soubory miniatur
 * @param string $locPath lokalni cesta k souboru
 * @param string $archPath cesta v archivu
 * @return bool
 */
function _backupFilterThumbnails($locPath, $archPath)
{
    return 0 !== strncmp($archPath, '/files/pictures/thumb/', 22);
}

/**
 * [ADMIN] Backup API - ziskat priponu souboru dle typu zalohy
 * @param int $type typ zalohy (_backup_x)
 * @return string|bool
 */
function _backupExt($type)
{
    switch ($type) {

        case _backup_db:
            return 'sld';

        case _backup_partial:
            return 'slp';

        case _backup_full:
            return 'php';

        default:
            trigger_error('Neplatny typ zalohy', E_USER_WARNING);

            return false;

    }
}

/**
 * [ADMIN] Backup API - obnovit zalohu (castecnou nebo jen databaze)
 * @param string $path cesta k souboru
 * @return array|bool true pri uspechu, jinak array(err_msg, fatal 1/0)
 */
function _backupRestore($path)
{
    // priprava
    global $_lang;
    $fatal = false;
    $path = realpath($path);

    // proces obnovy
    do {

        /* ----- nacteni a kontroly ----- */

        // kontrola souboru
        if (($err = _backupCheckFile($path, array(_backup_db, _backup_partial))) !== true) break;

        // nacteni souboru
        $kzip = new KZip($path);
        if (!empty($kzip->error)) {
            $err = str_replace('*errstr*', _htmlStr($kzip->error), $_lang['admin.other.backup.restore.upload.err.load']);
            break;
        }
        $type = $kzip->vars['type'];

        // uprava merge pole
        if (isset($kzip->vars['merge'])) $kzip->vars['merge'] = array_flip($kzip->vars['merge']);

        // kontrola prava pro zapis
        if ($type === _backup_partial) {
            $a_files = '/files/';
            $dirs = $kzip->listFiles($a_files, true);
            $dirs = $dirs[0];
            for ($i = 0; isset($dirs[$i]); ++$i) {
                if (($err = _emptyDir(realpath(dirname($_SERVER['SCRIPT_FILENAME']) . '/' . _indexroot . $dirs[$i]) . '/')) !== true) {
                    $err = str_replace('*path*', _htmlStr($err), $_lang['admin.other.backup.restore.err.access']);
                    break 2;
                }
            }
        }

        /* ----- provedeni ----- */

        // chyby na teto urovni jsou jiz fatalni
        $fatal = true;

        // databaze
        $dbdump = new DBDump;

        // tabulky
        $tbl_import = $dbdump->importTables($kzip->getFile('/database/struct'));
        if (!$tbl_import[0]) {
            $err = $tbl_import[1] . ': <code>' . _htmlStr($tbl_import[2]) . '</code>';
            break;
        }

        // data
        $dbstream = $kzip->getFileStream('/database/data');
        $data_import = $dbdump->importData($dbstream);
        $dbstream->free();
        if (!$data_import[0]) {
            $err = $data_import[1];
            break;
        }

        // aktualizovat hlavni URL, vynutit kontrolu instalace
        if (($url = _getBaseUrl()) !== false) DB::query('UPDATE `' . _mysql_prefix . '-settings` SET `val`=' . DB::val(rtrim($url, '/')) . ' WHERE `var`=\'url\'');
        DB::query('UPDATE `' . _mysql_prefix . '-settings` SET `val`=\'1\' WHERE `var`=\'installcheck\'');

        // deaktivovat modrewrite, pokud neexistuje .htaccess
        if (!file_exists(_indexroot . '.htaccess')) {
            DB::query('UPDATE `' . _mysql_prefix . '-settings` SET `val`=\'0\' WHERE `var`=\'modrewrite\'');
        }

        // soubory
        if ($type === _backup_partial) {
            for ($i = 0; isset($dirs[$i]); ++$i) {
                echo "\n\n";
                $dirpath = realpath(dirname($_SERVER['SCRIPT_FILENAME']) . '/' . _indexroot . $dirs[$i]) . '/';
                if (!isset($kzip->vars['merge'], $kzip->vars['merge'][$dirs[$i]])) _emptyDir($dirpath, false);
                $kzip->extractFiles($dirpath, $a_files . $dirs[$i] . '/', false, true, array($kzip->vars['void']));
            }
        }

        // hotovo
        $kzip->free();

        return true;

    } while (false);

    // chyba
    if (isset($kzip)) $kzip->free();
    return array($err, $fatal);
}
