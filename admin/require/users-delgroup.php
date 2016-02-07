<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  pripava promennych  --- */
$levelconflict = false;
$sysgroups_array = array(1, 2, 3);

// id
$continue = false;
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $systemgroup = in_array($id, $sysgroups_array);
    $query = DB::query("SELECT id,level FROM `" . _mysql_prefix . "-groups` WHERE id=" . $id);
    if (DB::size($query) != 0) {
        $query = DB::row($query);
        if (_loginright_level > $query['level']) {
            $continue = true;
        } else {
            $levelconflict = true;
        }
    }
}

if ($continue) {

    /* ---  odstraneni  --- */
    $done = false;
    if (isset($_POST['doit'])) {

        // smazani skupiny
        if (!$systemgroup) {
            DB::query("DELETE FROM `" . _mysql_prefix . "-groups` WHERE id=" . $id);
        }

        // zmena vychozi skupiny
        if (!$systemgroup and $id == _defaultgroup) {
            DB::query("UPDATE `" . _mysql_prefix . "-settings` SET val='3' WHERE var='defaultgroup'");
        }

        // smazani uzivatelu
        $users = DB::query("SELECT id FROM `" . _mysql_prefix . "-users` WHERE `group`=" . $id . " AND id!=0");
        while ($user = DB::row($users)) {
            _deleteUser($user['id']);
        }

        $done = true;

    }

    /* ---  vystup  --- */
    if ($done != true) {
        $output .= "
    <p class='bborder'>" . $_lang['admin.users.groups.delp'] . "</p>
    " . ($systemgroup ? _admin_smallNote($_lang['admin.users.groups.specialgroup.delnotice']) : '') . "
    <form class='cform' action='index.php?p=users-delgroup&amp;id=" . $id . "' method='post'>
    <input type='hidden' name='doit' value='1' />
    <input type='submit' value='" . $_lang['global.do'] . "' onclick='return _sysConfirm();' />
    " . _xsrfProtect() . "</form>
    ";
    } else {
        $output .= _formMessage(1, $_lang['global.done']);
    }

} else {
    if ($levelconflict == false) {
        $output .= _formMessage(3, $_lang['global.badinput']);
    } else {
        $output .= _formMessage(3, $_lang['global.disallowed']);
    }
}
