<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  pripava promennych  --- */
$levelconflict = false;

// id
$continue = false;
if (isset($_GET['id']) && _xsrfCheck(true)) {
    $id = DB::esc(_anchorStr($_GET['id'], false));
    $query = DB::query("SELECT id FROM `" . _mysql_prefix . "-users` WHERE username='" . $id . "'");
    if (DB::size($query) != 0) {
        $query = DB::row($query);
        if (_levelCheck($query['id'])) {
            $continue = true;
        } else {
            $continue = false;
            $levelconflict = true;
        }
        $id = $query['id'];
    }
}

if ($continue) {

    /* ---  odstraneni  --- */
    if ($query['id'] != 0 and $query['id'] != _loginid) {
        if (_deleteUser($id)) $output .= _formMessage(1, $_lang['global.done']);
        else $output .= _formMessage(2, $_lang['global.error']);
    } else {
        if ($query['id'] == 0) {
            $output .= _formMessage(2, $_lang['global.rootnote']);
        } else {
            $output .= _formMessage(2, $_lang['admin.users.deleteuser.selfnote']);
        }
    }

} else {
    if ($levelconflict == false) {
        $output .= _formMessage(3, $_lang['global.baduser']);
    } else {
        $output .= _formMessage(3, $_lang['global.disallowed']);
    }
}
