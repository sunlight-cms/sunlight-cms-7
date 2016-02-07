<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  priprava, ulozeni  --- */
$message = "";
if (isset($_POST['banned'])) {

    $banned = explode("\n", $_POST['banned']);
    $banned = _arrayRemoveValue($banned, "");

    $new_banned = array();
    foreach ($banned as $item) {
        $item = explode(".", $item);
        $item = _arrayRemoveValue($item, "");
        foreach ($item as $index => $isub) {
            $isub = intval(trim($isub));
            if ($isub < 0) {
                $isub = 0;
            }
            if ($isub > 255) {
                $isub = 255;
            }
            $item[$index] = $isub;
        }
        $new_banned[] = implode(".", $item);
    }

    $new_banned = trim(implode("\n", array_unique($new_banned)));

    DB::query("UPDATE `" . _mysql_prefix . "-settings` SET val='" . DB::esc($new_banned) . "' WHERE var='banned'");
    $message = _formMessage(1, $_lang['global.saved']);
    $data = $new_banned;
    unset($new_banned);

} else {
    $data = DB::query_row('SELECT `val` FROM `' . _mysql_prefix . '-settings` WHERE `var`=\'banned\'');
    $data = $data['val'];
}

/* ---  vystup  --- */

$output .= "
<p>" . $_lang['admin.other.bans.p'] . "</p>
" . $message . "

<table class='wintable'>
<tr class='valign-top'>

<td>
<form action='index.php?p=other-bans' method='post'>
<textarea rows='25' cols='94' class='areamedium' name='banned'>" . $data . "</textarea><br /><br />
<input type='submit' value='" . $_lang['global.save'] . "' />
" . _xsrfProtect() . "</form>
</td>

<td>
<h2>" . $_lang['admin.other.bans.getuserip'] . "</h2><br />
<form action='index.php' method='get'>
<input type='hidden' name='p' value='other-bans' />
" . $_lang['global.user'] . ": <input type='text' name='getip' class='inputsmall'" . _restoreGetValue("getip") . " /> <input type='submit' value='" . $_lang['global.do'] . "' />
</form>
";

// zjisteni ip adres uzivatele
if (isset($_GET['getip'])) {

    $user = _anchorStr(trim($_GET['getip']), false);
    $query = DB::query("SELECT ip,id FROM `" . _mysql_prefix . "-users` WHERE username='" . $user . "'");
    if (DB::size($query) != 0) {
        $query = DB::row($query);

        // vyhledani adres
        $ips = array();
        $iquery = DB::query("SELECT DISTINCT ip FROM `" . _mysql_prefix . "-posts` WHERE author=" . $query['id']);
        while ($iip = DB::row($iquery)) {
            $ips[] = $iip['ip'];
        }

        // pridani naposledy pouzite
        if (!in_array($query['ip'], $ips)) {
            $ips[] = $query['ip'];
        }

        // vypis
        $output .= "<br /><h2>" . $_lang['global.result'] . "</h2>\n<ul>\n";
        foreach ($ips as $ip) {
            $output .= "<li>" . $ip . "</li>\n";
        }
        $output .= "\n</ul>\n";

    } else {
        $output .= _formMessage(2, $_lang['global.baduser']);
    }

}

// dokonceni tabulky
$output .= "
</td>

</tr>
</table>
";
