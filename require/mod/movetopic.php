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
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $query = DB::query("SELECT id,home,author,time,subject,sticky FROM `" . _mysql_prefix . "-posts` WHERE id=" . $id . " AND type=5 AND xhome=-1");
    if (DB::size($query) != 0) {
        $query = DB::row($query);
        if (_postAccess($query) && _loginright_movetopics) {

            $continue = true;
            $backlink = "index.php?m=topic&amp;id=" . $query['id'];

        }
    }
}

/* ---  nacteni moznych for  --- */

if ($continue) {

    $forums = array();
    $fq = DB::query("SELECT forum.id,forum.title FROM `" . _mysql_prefix . "-root` AS forum WHERE forum.id!=" . $query['home'] . " AND forum.type=8 AND forum.level<=" . _loginright_level);
    while($fr = DB::row($fq)) $forums[$fr['id']] = $fr['title'];

}

/* ---  ulozeni  --- */
if ($continue && isset($_POST['new_forum'])) {

    $new_forum_id = intval($_POST['new_forum']);
    if (isset($forums[$new_forum_id])) {
        DB::query("UPDATE `" . _mysql_prefix . "-posts` SET home=" . $new_forum_id . " WHERE id=" . $id . " OR (type=5 AND xhome=" . $id . ")");
        $message = _formMessage(1, $_lang['mod.movetopic.ok']);
        $continue = false;
        $scriptbreak = true;
    } else {
        $message = _formMessage(3, $_lang['global.badinput']);
    }

}

/* ---  vystup  --- */

// titulek
if (_template_autoheadings == 1) $module .= "<h1>" . $_lang['mod.movetopic'] . "</h1><div class='hr'><hr /></div>";

// zpetny odkaz
$module .= "<p><a href='" . $backlink . "'>&lt; " . $_lang['global.return'] . "</a></p>";

// zprava
$module .= $message;

// formular
if ($continue) {

    $furl = 'index.php?m=movetopic&amp;id=' . $id;

    $module .= '
<form action="' . $furl . '" method="post">
' . _formMessage(2, sprintf($_lang['mod.movetopic.text'], $query['subject'])) . '
<p>
    <select name="new_forum"' . (empty($forums) ? " disabled='disabled'" : '') . '>
';

    if (empty($forums)) $module .= "<option value='-1'>" . $_lang['mod.movetopic.noforums'] . "</option>\n";
    else
        foreach($forums as $fid => $ftitle) $module .= "<option value='" . $fid . "'>" . $ftitle . "</option>\n";

    $module .= '</select>
    <input type="submit" value="' . $_lang['mod.movetopic.submit'] . '" />
</p>
' . _xsrfProtect() . '</form>
';

} else {
    /*neplatny vstup*/
    if (!$scriptbreak) {
        $module .= _formMessage(3, $_lang['global.badinput']);
        $found = false;
    }
}
