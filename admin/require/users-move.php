<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  ulozeni  --- */
$message = "";
if (isset($_POST['sourcegroup'])) {
    $source = intval($_POST['sourcegroup']);
    $target = intval($_POST['targetgroup']);
    $source_data = DB::query("SELECT level FROM `" . _mysql_prefix . "-groups` WHERE id=" . $source);
    $target_data = DB::query("SELECT level FROM `" . _mysql_prefix . "-groups` WHERE id=" . $target);

    if (DB::size($source_data) != 0 and DB::size($target_data) != 0 and $source != 2 and $target != 2) {
        if ($source != $target) {
            $source_data = DB::row($source_data);
            $target_data = DB::row($target_data);
            if (_loginright_level > $source_data['level'] and _loginright_level > $target_data['level']) {
                DB::query("UPDATE `" . _mysql_prefix . "-users` SET `group`=" . $target . " WHERE `group`=" . $source . " AND id!=0");
                $message = _formMessage(1, $_lang['global.done']);
            } else {
                $message = _formMessage(2, $_lang['admin.users.move.failed']);
            }
        } else {
            $message = _formMessage(2, $_lang['admin.users.move.same']);
        }
    } else {
        $message = _formMessage(3, $_lang['global.badinput']);
    }

}

/* ---  vystup  --- */

$output .= "<p class='bborder'>" . $_lang['admin.users.move.p'] . "</p>\n" . $message . "
<form class='cform' action='index.php?p=users-move' method='post'>
" . $_lang['admin.users.move.text1'] . " " . _admin_authorSelect("sourcegroup", -1, "id!=2", null, null, true) . " " . $_lang['admin.users.move.text2'] . " " . _admin_authorSelect("targetgroup", -1, "id!=2", null, null, true) . " <input type='submit' value='" . $_lang['global.do'] . "' onclick='return _sysConfirm();' />
" . _xsrfProtect() . "</form>
";
