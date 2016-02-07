<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  pripava promennych  --- */
$message = "";
$continue = false;
$scriptbreak = false;
$backlink = _indexroot;
$unstick = '';
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $query = DB::query("SELECT id,author,time,subject,sticky FROM `" . _mysql_prefix . "-posts` WHERE id=" . $id . " AND type=5 AND xhome=-1");
    if (DB::size($query) != 0) {
        $query = DB::row($query);
        if (_postAccess($query) && _loginright_stickytopics) {

            $continue = true;
            $backlink = "index.php?m=topic&amp;id=" . $query['id'];
            if ($query['sticky']) $unstick = '2';

        }
    }
}

/* ---  ulozeni  --- */
if ($continue && isset($_POST['doit'])) {

    DB::query("UPDATE `" . _mysql_prefix . "-posts` SET sticky=" . (($query['sticky'] == 1) ? 0 : 1) . " WHERE id=" . $id);
    $message = _formMessage(1, $_lang['mod.stickytopic.ok' . $unstick]);
    $continue = false;
    $scriptbreak = true;

}

/* ---  vystup  --- */

// titulek
if (_template_autoheadings == 1) {
    $module .= "<h1>" . $_lang['mod.stickytopic' . $unstick] . "</h1><div class='hr'><hr /></div>";
}

// zpetny odkaz
$module .= "<p><a href='" . $backlink . "'>&lt; " . $_lang['global.return'] . "</a></p>";

// zprava
$module .= $message;

// formular
if ($continue) {

    $furl = 'index.php?m=stickytopic&amp;id=' . $id;

    $module .= '
<form action="' . $furl . '" method="post">
' . _formMessage(2, sprintf($_lang['mod.stickytopic.text' . $unstick], $query['subject'])) . '
<input type="submit" name="doit" value="' . $_lang['mod.stickytopic.submit' . $unstick] . '" />
' . _xsrfProtect() . '</form>
';

} else {
    /*neplatny vstup*/
    if (!$scriptbreak) {
        $module .= _formMessage(3, $_lang['global.badinput']);
        $found = false;
    }
}
