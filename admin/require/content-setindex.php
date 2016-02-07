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

/* ---  akce  --- */
if ($continue && isset($_POST['index'])) {

    DB::query("UPDATE `" . _mysql_prefix . "-settings` SET `val`=" . ($index_id = intval($_POST['index'])) . ' WHERE `var`=\'index_page_id\'');
    $message = _formMessage(1, $_lang['global.done']);

} else $index_id = _index_page_id;

/* ---  vystup  --- */
if ($continue) {
    $output .= "<p class='bborder'>" . $_lang['admin.content.setindex.p'] . "</p>" . $message . "
<form class='cform' action='index.php?p=content-setindex' method='post'>
" . _admin_rootSelect('index', null, $index_id, false) . "
<input type='submit' value='" . $_lang['global.do'] . "' />
" . _xsrfProtect() . "</form>
";
}
