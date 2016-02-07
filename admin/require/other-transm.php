<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  akce  --- */
$message = "";
if (isset($_POST['user'])) {

    $user = DB::esc(_anchorStr(trim($_POST['user'])));
    $query = DB::query("SELECT id,password FROM `" . _mysql_prefix . "-users` WHERE username='" . $user . "'");
    if (DB::size($query) != 0) {
        $query = DB::row($query);
        _userLogout(false);
        $_SESSION[_sessionprefix . "user"] = $query['id'];
        $_SESSION[_sessionprefix . "password"] = $query['password'];
        $_SESSION[_sessionprefix . "ip"] = _userip;
        $_SESSION[_sessionprefix . "ipbound"] = true;
        define('_redirect_to', _indexroot . 'index.php?m=login');

        return;
    } else {
        $message = _formMessage(2, $_lang['global.baduser']);
    }

}

/* ---  vystup  --- */

$output .= "
<p class='bborder'>" . $_lang['admin.other.transm.p'] . "</p>
" . $message . "
<form action='index.php?p=other-transm' method='post'>
<strong>" . $_lang['global.user'] . ":</strong> <input type='text' name='user' class='inputsmall' /> <input type='submit' value='" . $_lang['global.login'] . "' />
" . _xsrfProtect() . "</form>
";
