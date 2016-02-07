<?php
/* ---  kontrola jadra --- */
if (!defined('_core')) {
    exit;
}

/* ---  priprava, odstraneni sloupce  --- */
$message = "";
if (isset($_GET['delcolumn']) && _xsrfCheck(true)) {
    DB::query("DELETE FROM `" . _mysql_prefix . "-boxes` WHERE `column`='" . DB::esc($_GET['delcolumn']) . "'");
    $message = _formMessage(1, $_lang['global.done']);
}

/* ---  vystup  --- */
$output .= "<p class='bborder'>" . $_lang['admin.content.boxes.p'] . "</p>
<p><a href='index.php?p=content-boxes-new'><img src='images/icons/new.png' alt='new' class='icon' />" . $_lang['admin.content.boxes.create'] . "</a></p>" . $message . "

<table class='listable'>
<thead><tr><td>" . $_lang['admin.content.boxes.column'] . "</td><td>" . $_lang['admin.content.boxes.totalboxes'] . "</td><td>" . $_lang['global.action'] . "</td></tr></thead>
<tbody>";

$query = DB::query("SELECT DISTINCT `column` FROM `" . _mysql_prefix . "-boxes` ORDER BY `column`");
while ($item = DB::row($query)) {
    $output .= "<tr><td><a href='index.php?p=content-boxes-edit&amp;c=" . urlencode($item['column']) . "' class='block'><img src='images/icons/dir.png' alt='col' class='icon' /><strong>" . _htmlStr($item['column']) . "</strong></a></td><td>" . DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-boxes` WHERE `column`='" . DB::esc($item['column']) . "'"), 0) . "</td><td><a href='" . _xsrfLink("index.php?p=content-boxes&amp;delcolumn=" . urlencode($item['column'])) . "' onclick='return _sysConfirm();'><img src='images/icons/delete.png' alt='del' class='icon' />" . $_lang['global.delete'] . "</a></td></tr>\n";
}

$output .= "</tbody></table>";
