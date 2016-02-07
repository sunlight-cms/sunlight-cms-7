<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  nacteni promennych  --- */
$continue = false;
if (isset($_GET['cat'])) {
    $cid = intval($_GET['cat']);
    if (DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-root` WHERE id=" . $cid . " AND type=2"), 0) != 0) {
        $catdata = DB::query_row("SELECT title,var1,var2 FROM `" . _mysql_prefix . "-root` WHERE id=" . $cid);
        $continue = true;
    }
}

/* ---  vystup --- */
if ($continue) {
    $output .= "
<p class='bborder'>" . $_lang['admin.content.articles.list.p'] . "</p>
";

    // nastaveni strankovani podle kategorie
    $artsperpage = $catdata['var2'];
    switch ($catdata['var1']) {
        case 1:
            $artorder = "art.time DESC";
            break;
        case 2:
            $artorder = "art.id DESC";
            break;
        case 3:
            $artorder = "art.title";
            break;
        case 4:
            $artorder = "art.title DESC";
            break;
    }

    // titulek kategorie
    $output .= "<h2>" . $catdata['title'] . " &nbsp; <a href='index.php?p=content-articles-edit&amp;new_cat=" . $cid . "'><img src='images/icons/new.png' alt='new' class='icon' />" . $_lang['admin.content.articles.create'] . "</a></h2>\n";

    // vypis clanku

    // zprava
    $message = "";
    if (isset($_GET['artdeleted'])) {
        $message = _formMessage(1, $_lang['admin.content.articles.delete.done']);
    }

    $cond = "(art.home1=" . $cid . " OR art.home2=" . $cid . " OR art.home3=" . $cid . ")" . _admin_artAccess('art');
    $paging = _resultPaging("index.php?p=content-articles-list&amp;cat=" . $cid, $catdata['var2'], "articles:art", $cond);
    $s = $paging[2];
    $output .= $paging[0] . "<div class='hr'><hr /></div>\n" . $message . "\n<table class='list'>\n<thead><tr><td>" . $_lang['global.article'] . "</td><td>" . $_lang['article.author'] . "</td><td>" . $_lang['article.posted'] . "</td><td>" . $_lang['global.action'] . "</td></tr></thead>\n<tbody>";
    $arts = DB::query("SELECT art.id,art.title,art.title_seo,art.time,art.author,art.confirmed,art.visible,art.public,cat.title_seo AS cat_title_seo FROM `" . _mysql_prefix . "-articles` AS art JOIN `" . _mysql_prefix . "-root` AS cat ON(cat.id=art.home1) WHERE " . $cond . " ORDER BY " . $artorder . " " . $paging[1]);
    if (DB::size($arts) != 0) {
        while ($art = DB::row($arts)) {
            $output .= "<tr><td>" . _admin_articleEditLink($art) . "</td><td>" . _linkUser($art['author']) . "</td><td>" . _formatTime($art['time']) . "</td><td><a href='index.php?p=content-articles-edit&amp;id=" . $art['id'] . "&amp;returnid=" . $cid . "&amp;returnpage=" . $s . "'><img src='images/icons/edit.png' alt='edit' class='icon' />" . $_lang['global.edit'] . "</a>&nbsp;&nbsp;&nbsp;<a href='index.php?p=content-articles-delete&amp;id=" . $art['id'] . "&amp;returnid=" . $cid . "&amp;returnpage=" . $s . "'><img src='images/icons/delete.png' alt='del' class='icon' />" . $_lang['global.delete'] . "</a></td></tr>\n";
        }
    } else {
        $output .= "<tr><td colspan='4'>" . $_lang['global.nokit'] . "</td></tr>";
    }
    $output .= "</tbody></table>";

} else {
    $output .= _formMessage(3, $_lang['global.badinput']);
}
