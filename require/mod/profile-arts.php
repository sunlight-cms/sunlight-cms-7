<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  priprava  --- */
$list = false;
$message = "";
$id = null;

if (isset($_GET['id'])) {
    $id = DB::esc(_anchorStr($_GET['id'], false));
    $query = DB::query("SELECT id FROM `" . _mysql_prefix . "-users` WHERE username='" . $id . "'");
    if (DB::size($query) != 0) {
        $query = DB::row($query);
        $list = true;
    } else {
        $message = _formMessage(2, $_lang['global.baduser']);
        $found = false;
    }
}

/* ---  modul  --- */

// titulek
if (_template_autoheadings == 1) {
    $module .= "<h1>" . $_lang['mod.profile.arts'] . "</h1><br />";
}

// vyhledavaci pole

// odkaz zpet na profil
if ($list) {
    $module .= "\n<a href='index.php?m=profile&amp;id=" . $id . "' class='backlink'>&lt; " . $_lang['global.return'] . "</a>\n";
}

$module .= "
  <form action='index.php' method='get'>
  <input type='hidden' name='m' value='profile-arts' />
  <input type='text' name='id'" . (($id != null) ? " value='" . $id . "'" : '') . " class='inputmedium' /> <input type='submit' value='" . $_lang['global.open'] . "' />
  </form><br />
  " . $message;

// tabulka
if ($list == true) {

    $cond = "art.author=" . $query['id'] . " AND " . _sqlArticleFilter(true);
    $paging = _resultPaging("index.php?m=profile-arts&amp;id=" . $id, 15, "articles:art", $cond);
    if (_pagingmode == 1 or _pagingmode == 2) {
        $module .= $paging[0];
    }
    $arts = DB::query("SELECT art.id,art.title,art.title_seo,art.author,art.perex,art.picture_uid,art.time,art.comments,art.public,art.readed,cat.title_seo AS cat_title_seo,(SELECT COUNT(id) FROM `" . _mysql_prefix . "-posts` AS post WHERE home=art.id AND post.type=2) AS comment_count FROM `" . _mysql_prefix . "-articles` AS art JOIN `" . _mysql_prefix . "-root` AS cat ON(cat.id=art.home1) WHERE " . $cond . " ORDER BY art.time DESC " . $paging[1]);
    if (DB::size($arts) != 0) {
        while ($art = DB::row($arts)) {
            $module .= _articlePreview($art, true, true, $art['comment_count']);
        }
        if (_pagingmode == 2 or _pagingmode == 3) {
            $module .= '<br />' . $paging[0];
        }
    } else {
        $module .= $_lang['global.nokit'];
    }

}
