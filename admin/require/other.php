<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  vystup  --- */
$output .= "
<p>" . $_lang['admin.other.p'] . "</p>

<table class='listable'>
<tr class='valign-top'>

<td" . (($modules_other_count != 0) ? " class='rbor'" : '') . ">
<ul class='ex-list'>
";

// pole polozek (nazev, pravo(null=loginright), remote skript?)
$items = array(
    array("backup", _loginright_adminbackup || _loginright_adminrestore, false),
    array("massemail", _loginright_adminmassemail, false),
    array("sqlex", _loginright_level == 10001, true),
    array("php", _loginright_level == 10001, true),
    array("cleanup", _loginright_level == 10001, false),
    array("bans", _loginright_adminbans, false),
    array("transm", _loginid == 0, false),
);

// vypis
foreach ($items as $item) {
    if ($item[1] == true or ($item[1] === null and constant('_loginright_admin' . $item[0]))) {
        $output .= "<li><a href='" . ($item[2] ? "remote/" . $item[0] . ".php' target='_blank" : "index.php?p=other-" . $item[0]) . "'>" . $_lang['admin.other.' . $item[0] . '.title'] . "</a></li>\n";
    }
}
$output .= "</ul>\n</td>\n";

// vypis rozsireni
if ($modules_other_count != 0) {
    $output .= "<td>\n<ul class='ex-list'>\n";
    foreach (array_slice($modules['other'][3], - $modules_other_count) as $plug) {
        if (!$modules[$plug][1]) continue;
        $output .= "<li><a href='index.php?p=" . $plug . "'>" . $modules[$plug][0] . "</a></li>\n";
    }
    $output .= "</ul>\n</td>\n";
}

$output .= "\n</tr>\n</table>";
