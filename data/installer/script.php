<?php

die;

/*

Potrebna nahrazeni kodu:
=========================

Promenne:
---------------------------------------------------------
$__locale       pole pro setlocale()
$__timezone     retezec pro date_default_timezone_set()
$__time_format  retezec jako format casu pro date()

Komentare
---------------------------------------------------------
@@@begin@@@     pocatek instalacniho skriptu

@@@core@@@      soubor require/load.php
@@@kzip@@@      soubor require/class/kzip.php
@@@dbdump@@@    soubor require/class/dbdump.php

@@@s_states@@@  hodnota promenne SL::$states

@@@lang@@@@     definice promenne $_lang
                    - zaklad je $_lang['installer']
                    - hodnoty navic:

                        global.cancel
                        global.continue
                        global.note
                        dbdump (pole)
                        admin.other.backup.backup.full jako type.full

*/

/* @@@begin@@@ */

/* --- inicializace --- */

define('_indexroot', './');
define('_tmp_dir', './');
define('_dev', false);

$cfg_locale = $__locale;
$cfg_timezone = $__timezone;

$err_rep = E_ALL & ~E_STRICT;
if (defined('E_DEPRECATED')) $err_rep &= ~E_DEPRECATED;
error_reporting($err_rep);
@setlocale(LC_TIME, $cfg_locale);
if (function_exists('date_default_timezone_set')) date_default_timezone_set($cfg_timezone);
define('_time_format', $__time_format);

header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

/* --- potrebne skripty a data --- */

/* @@@core@@@ */
/* @@@kzip@@@ */
/* @@@dbdump@@@ */
/* @@@lang@@@ */
/* @@@s_states@@@ */

// trida vyjimky instalace
class _InstallException extends Exception {}

// error handler
function _error_handler($code, $msg, $file = null, $line = null)
{
    if (error_reporting() === 0 || E_STRICT === $code || defined('E_DEPRECATED') && E_DEPRECATED === $code) return; // ignorovat potlacene chyby
    throw new ErrorException($msg, 0, $code, $file, $line);
}
set_error_handler('_error_handler', error_reporting());

/* --- nacteni archivu --- */

$self = new KZip(__FILE__, __COMPILER_HALT_OFFSET__);
$self_size = filesize(__FILE__) - __COMPILER_HALT_OFFSET__;
if (isset($self->error)) die($self->error);

// konfigurace dle typu
$is_clean = false;
switch ($self->vars['type']) {

    // cista instalace
    case -1:
    $type_idt = 'clean';
    $is_clean = true;
    break;

    // kompletni zaloha
    case 2:
    $type_idt = 'full';
    break;

    // neplatny typ
    default:
    die;

}

// url
$url = _htmlStr($self->vars['url']) . '/';

/* --- instalace --- */

function _tmp_installer_install()
{
    global $_lang, $self, $is_clean;

    // krok
    static $steps = 3;
    if (isset($_POST['step'])) {
        $step = intval($_POST['step']);
        if ($step < 1 || $step > $steps) $step = 1;
    } else $step = 1;

    $fname = basename(__FILE__);

    if (!empty($_POST)) echo '<a href="./' . $fname . '" id="cancelink">' . $_lang['global.cancel'] . '</a>';

    ?>

<h2><?php echo str_replace(array('*step*', '*steps*', '*name*'), array($step, $steps, $_lang['step.' . $step]), $_lang['install']) ?></h2>
<form action="./<?php echo $fname ?>" method="post" name="instform" autocomplete="off">
    <?php

    switch ($step) {

        // kontrola
        case 1:
        if (isset($_POST['check'])) {

            // nacteni a kontrola existence souboru
            $a_files = $self->listFilesOnPath('/files/');
            $conflicts = array();
            $counter = 0;
            $err_limit = 10;
            for ($i = 0; isset($a_files[$i]); ++$i) {
                $path = './' . substr($a_files[$i], 7);
                if (file_exists($path)) {
                    ++$counter;
                    if ($counter <= $err_limit) $conflicts[] = str_replace('*path*', $path, $_lang['step.1.err.file']);
                }
            }
            if ($counter > $err_limit) $conflicts[] = str_replace('*n*', ($counter - $err_limit), $_lang['step.1.err.file.etc']);

            // zprava nebo pokracovani
            if (empty($conflicts)) {

                // vse je ok
                $step = 2;
                echo '<p class="green center">' . $_lang['step.1.ok'] . '</p>';
                echo '<p class="center"><input type="submit" value="' . $_lang['global.continue'] . '"></p>';
                break;

            } else {

                // jsou chyby
                echo '<p class="red">' . $_lang['step.1.err'] . ':</p>';
                echo "<ul>\n";
                for($i = 0; isset($conflicts[$i]); ++$i) echo "<li>" . $conflicts[$i] . "</li>\n";
                echo "</ul>";

            }

        }
        echo '<p class="center"><input type="submit" name="check" value="' . $_lang['step.1.submit'] . '"></p>';
        break;

        // konfigurace & instalace
        case 2:
        case 3:

        // navrat z kroku 3
        if (isset($_POST['return_to_cfg'])) {
            $step = 2;
            unset($_POST['return_to_cfg']);
        }

        // instalace
        $install = ($step == 3);

        if (isset($_POST['sys_url'])) {

            // zpracovat url
            $_POST['sys_url'] = _removeSlashesFromEnd($_POST['sys_url']);

            // kontroly
            $err = null;
            do {

                // prefix
                $prefix = trim($_POST['db_prefix']);
                if ($prefix === '') {
                    $err = str_replace('*input*', $_lang['step.2.db.prefix'], $_lang['step.2.err.empty']);
                    break;
                }

                // ucet administratora
                $_POST['admin_name'] = _anchorStr(trim($_POST['admin_name']), false);
                $_POST['admin_email'] = trim($_POST['admin_email']);

                // pouze pro cistou instalaci
                if ($is_clean) {

                    // vynutit ucet administratora
                    if ($_POST['admin_name'] === '') {
                        $err = str_replace('*input*', $_lang['step.2.admin.name'], $_lang['step.2.err.empty']);
                        break;
                    }
                    if ($_POST['admin_pwd'] === '') {
                        $err = str_replace('*input*', $_lang['step.2.admin.pwd'], $_lang['step.2.err.empty']);
                        break;
                    }
                    if ($_POST['admin_email'] === '' || $_POST['admin_email'] === '@') {
                        $err = str_replace('*input*', $_lang['step.2.admin.email'], $_lang['step.2.err.empty']);
                        break;
                    }

                    // titulek stranek
                    $_POST['sys_title'] = trim($_POST['sys_title']);
                    if ($_POST['sys_title'] === '') {
                        $err = str_replace('*input*', $_lang['step.2.sys.title'], $_lang['step.2.err.empty']);
                        break;
                    }

                    // popis, klicova slova
                    $_POST['sys_descr'] = trim($_POST['sys_descr']);
                    $_POST['sys_kwrds'] = trim($_POST['sys_kwrds']);

                }

                // email administratora
                if ($_POST['admin_email'] !== '' && $_POST['admin_email'] !== '@' && !_validateEmail($_POST['admin_email'])) {
                    $err = $_lang['step.2.err.admin.email'];
                    break;
                }

                // heslo administratora
                if ($_POST['admin_pwd'] !== '' && $_POST['admin_pwd'] !== $_POST['admin_pwd2']) {
                    $err = $_lang['step.2.err.admin.pwd'];
                    break;
                }

                // pripojeni
                $con = @mysqli_connect($_POST['db_server'], $_POST['db_user'], $_POST['db_pwd']);
                if (!is_object($con)) {
                    $err = $_lang['step.2.err.con'] . '<br><code>' . _htmlStr(mysqli_connect_error()) . '</code>';
                    break;
                }

                // kodovani a konstanty
                DB::$con = $con;
                DB::$con->set_charset('utf8');
                define('_mysql_prefix', $prefix);

                // existence tabulek
                $prefix = DB::esc($prefix);
                $q = DB::query('SHOW TABLES LIKE \'' . $prefix . '-%\'');
                $tables = array();
                while($r = DB::rown($q)) $tables[] = $r[0];
                if (!empty($tables) && !isset($_POST['db_overwrite'])) {
                    $err = $_lang['step.2.err.tables'] . ':<br><br>&bull; ' . implode("<br>\n&bull; ", $tables);
                    break;
                }

                // vse ok
                if ($install) {

                    if (!isset($_POST['do_install'])) {

                        // potvrzeni
                        echo _getPostdata(false, null, array('step'));
                        echo '<p class="green center">' . $_lang['step.3.text'] . '</p>';
                        echo '<p class="center">
<input type="submit" name="do_install" value="' . $_lang['step.3.submit'] . '" onclick="if (window.sl_install_process) return false; else {window.sl_install_process = true; this.value=\'' . $_lang['step.3.wait'] . '\'}">&nbsp;
<input type="submit" name="return_to_cfg" value="' . $_lang['step.3.return'] . '">
</p>';

                    } else {

                        // provedeni
                        $err = null;
                        try {

                            // rozbalit soubory
                            $self->extractFiles('./', '/files/', false, true, array($self->vars['void']));

                            // vytvorit konfiguracni soubor
                            global $cfg_locale, $cfg_timezone;
                            file_put_contents('./config.php', str_replace(
                                array('/* @@@server@@@ */', '/* @@@user@@@ */', '/* @@@password@@@ */', '/* @@@database@@@ */', '/* @@@prefix@@@ */', '/* @@@locale@@@ */', '/* @@@timezone@@@ */'),
                                array(var_export($_POST['db_server'], true), var_export($_POST['db_user'], true), var_export($_POST['db_pwd'], true), var_export($_POST['db_name'], true), var_export($prefix, true), var_export($cfg_locale, true), var_export($cfg_timezone, true)),
                                $self->getFile('/files/data/installer/config.php.tpl')
                            ));

                            // smazat tabulky z databaze?
                            if (!empty($tables))
                                for ($i = 0; isset($tables[$i]); ++$i) {
                                    DB::query('DROP TABLE `' . $tables[$i] . '`', true);
                                    if (($sql_err = DB::error()) !== '') throw new _InstallException($_lang['step.3.err.drop'] . '<br><code>' . $sql_err . '</code>');
                                }

                            // deaktivovat kontrolu verze
                            function _checkVersion()
                            {
                                return true;
                            }

                            // vytvorit strukturu databaze
                            $dbdump = new DBDump;
                            $dbdump->importTables($self->getFile('/database/struct'));

                            // nacist data
                            $data_stream = $self->getFileStream('/database/data');
                            $dbdump->importData($data_stream);
                            $data_stream->free();

                            // aktualizovat url
                            DB::query('UPDATE `' . $prefix . '-settings` SET `val`=' . DB::val($_POST['sys_url']) . ' WHERE `var`=\'url\'');

                            // vypnout mod rewrite pokud neexistuje .htaccess
                            if (!file_exists(_indexroot . '.htaccess')) {
                                DB::query('UPDATE `' . $prefix . '-settings` SET `val`=0 WHERE `var`=\'modrewrite\'');
                            }

                            // upravit ucet administratora
                            $admin_upd = array();
                            if ($_POST['admin_name'] !== '') {
                                $admin_upd['username'] = $_POST['admin_name'];
                                if (!$is_clean) $admin_upd['publicname'] = '';
                            }
                            if ($_POST['admin_email'] !== '' && $_POST['admin_email'] !== '@') $admin_upd['email'] = $_POST['admin_email'];
                            if ($_POST['admin_pwd'] !== '') {
                                $admin_pwd = _md5Salt($_POST['admin_pwd']);
                                $admin_upd['password'] = $admin_pwd[0];
                                $admin_upd['salt'] = $admin_pwd[1];
                            }
                            if ($is_clean) {
                                $admin_upd['registertime'] = time();
                                $admin_upd['activitytime'] = time();
                            }
                            if (!empty($admin_upd)) {

                                $admin_upd_sql = '';
                                $counter = 0;
                                foreach ($admin_upd as $col => $val) {
                                    if ($counter !== 0) $admin_upd_sql .= ',';
                                    $admin_upd_sql .= '`' . $col . '`=' . DB::val($val);
                                    ++$counter;
                                }
                                DB::query('UPDATE `' . $prefix . '-users` SET ' . $admin_upd_sql . ' WHERE id=0');

                            }

                            // aktualizovat titulek, klic. slova a popis
                            if ($is_clean) {
                                DB::query('UPDATE `' . $prefix . '-settings` SET `val`=' . DB::val(_htmlStr($_POST['sys_title'])) . ' WHERE `var`=\'title\'');
                                DB::query('UPDATE `' . $prefix . '-settings` SET `val`=' . DB::val(_htmlStr($_POST['sys_kwrds'])) . ' WHERE `var`=\'keywords\'');
                                DB::query('UPDATE `' . $prefix . '-settings` SET `val`=' . DB::val(_htmlStr($_POST['sys_descr'])) . ' WHERE `var`=\'description\'');
                            }

                            // vypnout mod_rewrite
                            DB::query('UPDATE `' . $prefix . '-settings` SET `val`=\'0\' WHERE `var`=\'mod_rewrite\'');

                            // vynutit kontrolu instalace
                            DB::query('UPDATE `' . $prefix . '-settings` SET `val`=\'1\' WHERE `var`=\'install_check\'');

                        } catch (_InstallException $e) {
                            $err = $e->getMessage();
                        } catch (Exception $e) {
                            $err = _htmlStr($e->getMessage());
                        }

                        // uspech ci chyba
                        if (isset($err)) {
                            echo '<p class="red">' . $err . '</p>';
                            echo '<p class="red">' . $_lang['step.3.err.warning'] . '</p>';
                        } else echo '<p class="green center">' . str_replace('*fname*', $fname, $_lang['step.3.fin']) . '</p>';

                    }

                    break 2;

                } else {
                    $step = 3;
                    echo '<p class="green center">' . $_lang['step.2.ok'] . '</p>';
                }

            } while (false);

            // chyba
            if (isset($err)) echo '<p class="red">' . $err . '</p>';

        }

        ?>

<table>
<thead><th colspan="2"><?php echo $_lang['step.2.sys'] ?></th></thead>
<tbody>

    <tr>
        <th><?php echo $_lang['step.2.sys.url'] ?></th>
        <td><input type="text" name="sys_url"<?php echo _restorePostValue('sys_url') ?>></td>
    </tr>

    <?php if ($is_clean): ?>
    <tr>
        <th><?php echo $_lang['step.2.sys.title'] ?></th>
        <td><input type="text" name="sys_title"<?php echo _restorePostValue('sys_title') ?>></td>
    </tr>

    <tr>
        <th><?php echo $_lang['step.2.sys.descr'] ?></th>
        <td><input type="text" name="sys_descr"<?php echo _restorePostValue('sys_descr') ?>></td>
    </tr>

    <tr>
        <th><?php echo $_lang['step.2.sys.kwrds'] ?></th>
        <td><input type="text" name="sys_kwrds"<?php echo _restorePostValue('sys_kwrds') ?>></td>
    </tr>
    <?php endif ?>

</tbody>
</table>

<script type="text/javascript">
// predvyplneni adresy
if (document.instform.sys_url.value === '') {
    var loc = new String(document.location);
    var slash;
    var slash_last = 0;
    var limit = 0;
    while (true) {
        slash = loc.indexOf('/', slash_last);
        if (slash === -1) break;
        slash_last = slash + 1;
    }
    loc = loc.substr(0, slash_last);
    document.instform.sys_url.value = loc;
}
</script>

<table>
<thead>
    <tr><th colspan="2"><?php echo $_lang['step.2.admin'] ?></th></tr>
    <?php if (!$is_clean): ?><tr><th colspan="2"><small><?php echo $_lang['step.2.admin.notice'] ?></small></th></tr><?php endif ?>
</thead>
<tbody>

    <tr>
        <th><?php echo $_lang['step.2.admin.name'] ?></th>
        <td><input type="text" maxlength="24" name="admin_name"<?php echo _restorePostValue('admin_name') ?>></td>
    </tr>

    <tr>
        <th><?php echo $_lang['step.2.admin.email'] ?></th>
        <td><input type="text" maxlength="100" name="admin_email"<?php echo _restorePostValue('admin_email', ($is_clean ? '@' : null)) ?>></td>
    </tr>

    <tr>
        <th><?php echo $_lang['step.2.admin.pwd'] ?></th>
        <td><input type="password" name="admin_pwd"<?php echo _restorePostValue('admin_pwd') ?>></td>
    </tr>

    <tr>
        <th><?php echo $_lang['step.2.admin.pwd2'] ?></th>
        <td><input type="password" name="admin_pwd2"<?php echo _restorePostValue('admin_pwd2') ?>></td>
    </tr>

</tbody>
</table>

<table>
<thead><tr><th colspan="2"><?php echo $_lang['step.2.db'] ?></th></tr></thead>
<tbody>

    <tr>
        <th><?php echo $_lang['step.2.db.server'] ?></th>
        <td><input type="text" name="db_server"<?php echo _restorePostValue('db_server', 'localhost') ?>></td>
    </tr>

    <tr>
        <th><?php echo $_lang['step.2.db.name'] ?></th>
        <td><input type="text" name="db_name"<?php echo _restorePostValue('db_name') ?>></td>
    </tr>

    <tr>
        <th><?php echo $_lang['step.2.db.user'] ?></th>
        <td><input type="text" name="db_user"<?php echo _restorePostValue('db_user') ?>></td>
    </tr>

    <tr>
        <th><?php echo $_lang['step.2.db.pwd'] ?></th>
        <td><input type="password" name="db_pwd"<?php echo _restorePostValue('db_pwd') ?>></td>
    </tr>

    <tr>
        <th><?php echo $_lang['step.2.db.prefix'] ?></th>
        <td><input type="text" maxlength="24" name="db_prefix"<?php echo _restorePostValue('db_prefix', 'sunlight') ?>></td>
    </tr>

    <tr>
        <th><?php echo $_lang['step.2.db.tables'] ?></th>
        <td><label><input type="checkbox" name="db_overwrite"<?php echo _checkboxActivate(isset($_POST['db_overwrite'])) ?> value="1" onchange="if (this.checked && !confirm('<?php echo $_lang['step.2.db.tables.overwrite.confirm'] ?>')) this.checked = false"> <?php echo $_lang['step.2.db.tables.overwrite'] ?></label></td>
    </tr>

</tbody>
</table>

<p class="center"><input type="submit" value="<?php echo $_lang[(($step != 3) ? 'step.2.submit' : 'global.continue')] ?>"></p>

        <?php

        //<p class="warning"><?php echo $_lang['step.2.warning']</p>
        break;

    }

    ?>
<input type="hidden" name="step" value="<?php echo $step ?>">
</form>
    <?php
}

?><!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="robots" content="noindex,follow">
<title><?php echo $_lang['title'] ?></title>

<style type="text/css">
* {margin: 0; padding: 0;}
body {background-color: #878787; color: #594D35; font-size: 84%; font-family: "Lucida Sans Unicode","Lucida Grande",Verdana,Arial,Helvetica,sans-serif;}
a {color: #EE9E00; text-decoration: none;}
a:hover {text-decoration: underline;}
h1 {font-size: 1.5em; padding: 0.5em; background-color: #FFAA00; color: #FFF; border-bottom: 1px solid #E09500;}
h2 {font-size: 1.1em; font-weight: normal; padding-bottom: 0.3em; margin-bottom: 0.5em; border-bottom: 1px solid #D8CEBC;}
p {margin: 0.7em 0;}
ul, ol {padding-left: 2em; margin: 0.5em 0;}
li {line-height: 140%;}
input[type=text], input[type=password], input[type=submit], input[type=button], input[type=reset], button, select {padding: 3px;}
input[type=text], input[type=password] {width: 100%;}

#layout {width: 600px; margin: 1em auto; background-color:#E9E9E9; border: 1px solid #926100; -moz-box-shadow: 0px 0px 2px 2px #686868; box-shadow: 0px 0px 2px 2px #686868;}
#copyright {padding: 0.5em 0 0.8em 0; text-align: center; font-size: 0.8em; color: #FFF; border-top: 1px solid #E09500; background-color: #FFAA00;}
#copyright a {color: inherit;}
#cancelink {float: right;}

table {border-collapse: collapse; width: 100%; margin: 0.2em 0;}
table td, table th {border: 1px solid #F0F0F0; padding: 0.3em 1em;}
table th {background-color: #DDD; font-weight: bold; width: 30%;}
.block {margin: 0.6em; padding: 0.5em 0.5em 1em 0.5em; background-color: #FFF;}
/*div.hr {height: 0; margin: 1em 0; border-bottom: 1px solid #9A9A9A;}
div.hr > hr {display: none;}*/

.green {color: #080;}
.red {color: #F00000;}
.center {text-align: center;}
</style>

</head>

<body>

<div id="layout">
<h1><?php echo $_lang['title'] ?> (<?php echo $self->vars['system_version'] ?>)</h1>

<?php if (empty($_POST)): ?>
<!-- info -->
<div class="block">
<h2><?php echo $_lang['info'] ?></h2>

    <table>
    <tbody>

    <tr>
        <th><?php echo $_lang['type'] ?></th>
        <td><?php echo $_lang['type.' . $type_idt] ?></td>
    </tr>

    <tr>
        <th><?php echo $_lang['time'] ?></th>
        <td><?php echo date(_time_format, $self->vars['time']) ?></td>
    </tr>

    <tr>
        <th><?php echo $_lang['url'] ?></th>
        <td><a href="<?php echo $url ?>" target="_blank"><?php echo $url ?></a></td>
    </tr>

    <tr>
        <th><?php echo $_lang['version'] ?></th>
        <td><?php echo $self->vars['system_version'] . ' <small>' . (($self->vars['system_state'] === 2) ? '(rev.' . $self->vars['system_revision'] . ')' : $_sys_states[$self->vars['system_state']] . $self->vars['system_revision']) . '</small>' ?></td>
    </tr>

    <tr>
        <th><?php echo $_lang['fsize'] ?></th>
        <td><?php echo number_format($self_size /1024, 0, '.', ' ') ?>kB</td>
    </tr>

    <tr>
        <th><?php echo $_lang['size'] ?></th>
        <td><?php echo number_format($self->vars['size'] /1024, 0, '.', ' ') ?>kB</td>
    </tr>

    <tr>
        <th><?php echo $_lang['global.note'] ?></th>
        <td><?php echo _htmlStr($self->vars['note']) ?></td>
    </tr>

    </tbody>
    </table>

</div>

<!--<div class="hr"><hr></div>-->

<?php endif ?>

<div class="block">
<?php _tmp_installer_install() ?>
</div>

<!-- coypright -->
<p id="copyright">
    Copyright &copy; <?php echo date('Y') ?> <a href="http://sunlight.shira.cz/">SunLight CMS</a> by <a href="http://shira.cz/">ShiraNai7</a>
</p>

</div>

</body>
</html>
<?php __halt_compiler();