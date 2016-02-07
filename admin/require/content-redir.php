<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  priprava, kontrola pristupovych prav  --- */
$message = "";
if (!(_loginright_adminsection or _loginright_admincategory or _loginright_adminbook or _loginright_adminseparator or _loginright_admingallery or _loginright_adminintersection or _loginright_adminpluginpage)) {
    $continue = false;
    $output .= _formMessage(3, $_lang['global.accessdenied']);
} else {
    $continue = true;
}

/* ---  vystup  --- */
if ($continue) {

    // text a menu
    $output .= "<p class='bborder'>" . $_lang['admin.content.redir.p'] . "</p>
<p>
    <a href='index.php?p=content-redir&amp;new'><img src='images/icons/new.png' alt='new' class='icon' /> " . $_lang['admin.content.redir.act.new'] . "</a>&nbsp;
    <a href='index.php?p=content-redir&amp;wipe'><img src='images/icons/delete.png' alt='wipe' class='icon' /> " . $_lang['admin.content.redir.act.wipe'] . "</a>
</p>
";

    // akce - uprava / vytvoreni
    if (isset($_GET['new']) || isset($_GET['edit']))
        do {

            // priprava
            $new = isset($_GET['new']);
            if (!$new) $edit_id = intval($_GET['edit']);

            // zpracovani
            if (isset($_POST['old'])) {

                // nacteni dat
                $q = array();
                $q['old'] = _anchorStr(trim($_POST['old']), true, array('/' => 0));
                $q['new'] = _anchorStr(trim($_POST['new']), true, array('/' => 0));
                $q['active'] = _checkboxLoad('act');

                // kontrola
                if ($q['old'] === '' || $q['new'] === '') {
                    $message = _formMessage(2, $_lang['admin.content.redir.emptyidt']);
                } elseif ($new) {
                    // vytvoreni
                    DB::query('INSERT INTO `' . _mysql_prefix . '-redir` (old,new,active) VALUES (\'' . DB::esc($q['old']) . '\',\'' . DB::esc($q['new']) . '\',' . $q['active'] . ')');
                    $new = false;
                    $message = _formMessage(1, $_lang['global.created']);
                    break;
                } else {
                    // ulozeni
                    DB::query('UPDATE `' . _mysql_prefix . '-redir` SET old=\'' . DB::esc($q['old']) . '\',new=\'' . DB::esc($q['new']) . '\',active=' . $q['active'] . ' WHERE id=' . $edit_id);
                    $message = _formMessage(1, $_lang['global.saved']);
                }

            }

            // nacteni dat
            if ($new) {
                if (!isset($q)) $q = array();
                $q += array('id' => null, 'old' => '', 'new' => '', 'active' => '1');
            } else {
                $q = DB::query_row('SELECT * FROM `' . _mysql_prefix . '-redir` WHERE id=' . $edit_id);
                if ($q === false) break;
            }

            // formular
            $output .= $message . "\n<form action='' method='post'>
<table class='formtable'>

<tr>
    <td class='rpad'><strong>" . $_lang['admin.content.redir.old'] . "</strong></td>
    <td><input type='text' name='old' value='" . $q['old'] . "' class='inputmedium' maxlength='255' /></td>
</tr>

<tr>
    <td class='rpad'><strong>" . $_lang['admin.content.redir.new'] . "</strong></td>
    <td><input type='text' name='new' value='" . $q['new'] . "' class='inputmedium' maxlength='255' /></td>
</tr>

<tr>
    <td class='rpad'><strong>" . $_lang['admin.content.redir.act'] . "</strong></td>
    <td><input type='checkbox' name='act' value='1'" . _checkboxActivate($q['active']) . " /></td>
</tr>

<tr>
    <td></td>
    <td><input type='submit' value='" . $_lang['global.' . ($new ? 'create' : 'save')] . "' /></td>
</tr>

</table>
" . _xsrfProtect() . "</form>";

        } while (false);
    elseif (isset($_GET['del']) && _xsrfCheck(true)) {

        // smazani
        DB::query('DELETE FROM `' . _mysql_prefix . '-redir` WHERE id=' . intval($_GET['del']));
        $output .= _formMessage(1, $_lang['global.done']);

    } elseif (isset($_GET['wipe'])) {

        // smazani vsech
        if (isset($_POST['wipe_confirm'])) {
            DB::query('TRUNCATE TABLE `' . _mysql_prefix . '-redir`');
            $output .= _formMessage(1, $_lang['global.done']);
        } else {
            $output .= "
<form action='' method='post' class='formbox'>
" . _formMessage(2, $_lang['admin.content.redir.act.wipe.confirm']) . "
<input type='submit' name='wipe_confirm' value='" . $_lang['admin.content.redir.act.wipe.submit'] . "' />
" . _xsrfProtect() . "</form>
";
        }

    }

    // tabulka
    $output .= "<table class='list'>
<thead><tr><td>" . $_lang['admin.content.redir.old'] . "</td><td>" . $_lang['admin.content.redir.new'] . "</td><td>" . $_lang['admin.content.redir.act'] . "</td><td>" . $_lang['global.action'] . "</td></tr></thead>
<tbody>
";

    // vypis
    $counter = 0;
    $q = DB::query('SELECT * FROM `' . _mysql_prefix . '-redir`');
    while ($r = DB::row($q)) {
        $output .= "<tr><td><code>" . $r['old'] . "</code></td><td><code>" . $r['new'] . "</code></td><td class='text-" . ($r['active'] ? 'green' : 'red') . "'>" . $_lang['global.' . ($r['active'] ? 'yes' : 'no')] . "</td><td><a href='index.php?p=content-redir&amp;edit=" . $r['id'] . "'><img src='images/icons/edit.png' alt='edit' class='icon' /></a>&nbsp;<a href='" . _xsrfLink("index.php?p=content-redir&amp;del=" . $r['id']) . "' onclick='return _sysConfirm();'><img src='images/icons/delete.png' alt='del' class='icon' /></a></td></tr>";
        ++$counter;
    }

    // zadna data?
    if ($counter === 0) $output .= "<tr><td colspan='4'>" . $_lang['global.nokit'] . "</td></tr>\n";

    // konec tabulky
    $output .= "</tbody>
</table>\n";

}
