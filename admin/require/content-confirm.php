<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  schvaleni zvoleneho clanku  --- */
$message = "";
if (isset($_GET['id'])) {
    DB::query("UPDATE `" . _mysql_prefix . "-articles` SET confirmed=1 WHERE id=" . intval($_GET['id']));
    $message = _formMessage(1, $_lang['global.done']);
}

/* ---  vystup  --- */

// nacteni filtru
if (isset($_GET['limit'])) {
    $catlimit = intval($_GET['limit']);
    $condplus = " AND (art.home1=" . $catlimit . " OR art.home2=" . $catlimit . " OR art.home3=" . $catlimit . ")";
} else {
    $catlimit = -1;
    $condplus = "";
}

$output .= "
<p class='bborder'>" . $_lang['admin.content.confirm.p'] . "</p>

<form class='cform' action='index.php' method='get'>
<input type='hidden' name='p' value='content-confirm' />
" . $_lang['admin.content.confirm.filter'] . ": " . _admin_rootSelect("limit", 2, $catlimit, true, $_lang['global.all']) . " <input type='submit' value='" . $_lang['global.do'] . "' />
</form>
<div class='hr'><hr /></div>

" . $message . "

<table class='list'>
<thead><tr><td>" . $_lang['global.article'] . "</td><td>" . $_lang['article.category'] . "</td><td>" . $_lang['article.posted'] . "</td><td>" . $_lang['article.author'] . "</td><td>" . $_lang['global.action'] . "</td></tr></thead>
<tbody>";

// vypis
$query = DB::query("SELECT art.id,art.title,art.title_seo,art.home1,art.home2,art.home3,art.author,art.time,art.visible,art.confirmed,art.public,cat.title_seo AS cat_title_seo FROM `" . _mysql_prefix . "-articles` AS art JOIN `" . _mysql_prefix . "-root` AS cat ON(cat.id=art.home1) WHERE art.confirmed=0" . $condplus . " ORDER BY art.time DESC");
if (DB::size($query) != 0) {
    while ($item = DB::row($query)) {

        // seznam kategorii
        $cats = "";
        for ($i = 1; $i <= 3; $i++) {
            if ($item['home' . $i] != -1) {
                $hometitle = DB::query_row("SELECT title FROM `" . _mysql_prefix . "-root` WHERE id=" . $item['home' . $i]);
                $cats .= $hometitle['title'];
            }
            if ($i != 3 and $item['home' . ($i + 1)] != -1) {
                $cats .= ", ";
            }
        }

        if (DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-articles` WHERE id=" . $item['id'] . _admin_artAccess()), 0) != 0) {
            $editlink = " / <a href='index.php?p=content-articles-edit&amp;id=" . $item['id'] . "&amp;returnid=load&amp;returnpage=1' class='small'>" . $_lang['global.edit'] . "</a>";
        } else {
            $editlink = "";
        }
        $output .= "<tr><td>" . _admin_articleEditLink($item, false) . "</td><td>" . $cats . "</td><td>" . _formatTime($item['time']) . "</td><td>" . _linkUser($item['author']) . "</td><td><a href='index.php?p=content-confirm&amp;id=" . $item['id'] . "&amp;limit=" . $catlimit . "' class='small'>" . $_lang['admin.content.confirm.confirm'] . "</a>" . $editlink . "</td></tr>\n";
    }
} else {
    $output .= "<tr><td colspan='5'>" . $_lang['global.nokit'] . "</td></tr>";
}

$output .= "</tbody></table>";
