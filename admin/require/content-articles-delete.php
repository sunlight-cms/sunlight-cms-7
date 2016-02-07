<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  nacteni promennych  --- */
$continue = false;
if (isset($_GET['id']) and isset($_GET['returnid']) and isset($_GET['returnpage'])) {
    $id = intval($_GET['id']);
    $returnid = intval($_GET['returnid']);
    $returnpage = intval($_GET['returnpage']);
    $query = DB::query("SELECT title FROM `" . _mysql_prefix . "-articles` WHERE id=" . $id . _admin_artAccess());
    if (DB::size($query) != 0) {
        $query = DB::row($query);
        $continue = true;
    }
}

/* ---  ulozeni  --- */
if (isset($_POST['confirm'])) {

    // smazani komentaru
    DB::query("DELETE FROM `" . _mysql_prefix . "-posts` WHERE type=2 AND home=" . $id);

    // smazani clanku
    DB::query("DELETE FROM `" . _mysql_prefix . "-articles` WHERE id=" . $id);

    // udalost
    _extend('call', 'admin.article.delete', array('id' => $id));

    // presmerovani
    define('_redirect_to', 'index.php?p=content-articles-list&cat=' . $returnid . '&page=' . $returnpage . '&artdeleted');

    return;

}

/* ---  vystup  --- */
if ($continue) {

    $output .= "
<a href='index.php?p=content-articles-list&amp;cat=" . $returnid . "&amp;page=" . $returnpage . "' class='backlink'>&lt; " . $_lang['global.return'] . "</a>
<h1>" . $_lang['admin.content.articles.delete.title'] . "</h1>
<p class='bborder'>" . str_replace("*arttitle*", $query['title'], $_lang['admin.content.articles.delete.p']) . "</p>
<form class='cform' action='index.php?p=content-articles-delete&amp;id=" . $id . "&amp;returnid=" . $returnid . "&amp;returnpage=" . $returnpage . "' method='post'>
<input type='hidden' name='confirm' value='1' />
<input type='submit' value='" . $_lang['admin.content.articles.delete.confirmbox'] . "' />
" . _xsrfProtect() . "</form>
";

} else {
    $output .= _formMessage(3, $_lang['global.badinput']);
}
