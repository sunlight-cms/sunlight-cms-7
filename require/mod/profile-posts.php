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
    $module .= "<h1>" . $_lang['mod.profile.posts'] . "</h1><br />";
}

// vyhledavaci pole

// odkaz zpet na profil
if ($list) {
    $module .= "\n<a href='index.php?m=profile&amp;id=" . $id . "' class='backlink'>&lt; " . $_lang['global.return'] . "</a>\n";
}

$module .= "
  <form action='index.php' method='get'>
  <input type='hidden' name='m' value='profile-posts' />
  <input type='text' name='id'" . (($id != null) ? " value='" . $id . "'" : '') . " class='inputmedium' /> <input type='submit' value='" . $_lang['global.open'] . "' />
  </form><br />
  " . $message;

// tabulka
if ($list == true) {

    $cond = "author=" . $query['id'] . " AND `type`!=4 AND `type`!=6 AND `type`!=7";
    $paging = _resultPaging("index.php?m=profile-posts&amp;id=" . $id, 15, "posts", $cond);
    if (_pagingmode == 1 or _pagingmode == 2) $module .= $paging[0];

    $posts = DB::query("SELECT id,type,home,xhome,subject,text,author,time FROM `" . _mysql_prefix . "-posts` WHERE " . $cond . " ORDER BY time DESC " . $paging[1]);
    if (DB::size($posts) != 0) {
        while ($post = DB::row($posts)) {
            switch ($post['type']) {
                case 1:
                case 3:
                    $hometitle = DB::query_row("SELECT title,title_seo FROM `" . _mysql_prefix . "-root` WHERE id=" . $post['home']);
                    $homelink = _linkRoot($post['home'], $hometitle['title_seo']);
                    $hometitle = $hometitle['title'];
                    break;
                case 2:
                    $hometitle = DB::query_row("SELECT art.title,art.title_seo,cat.title_seo AS cat_title_seo FROM `" . _mysql_prefix . "-articles` AS art JOIN `" . _mysql_prefix . "-root` AS cat ON(cat.id=art.home1) WHERE art.id=" . $post['home']);
                    $homelink = _linkArticle($post['home'], $hometitle['title_seo']);
                    $hometitle = $hometitle['title'];
                    break;
                case 5:
                    $homelink = 'index.php?m=topic&amp;id=' . $post[(($post['xhome'] == '-1') ? 'id' : 'xhome')];
                    if ($post['xhome'] == '-1') {
                        $hometitle = $post['subject'];
                    } else {
                        $hometitle = DB::query_row("SELECT subject FROM `" . _mysql_prefix . "-posts` WHERE id=" . $post['xhome']);
                        $hometitle = $hometitle['subject'];
                    }
                    break;
            }
            $module .= "<div class='post-head'><a href='" . $homelink . "#post-" . $post['id'] . "' class='post-author'>" . $hometitle . "</a> <span class='post-info'>(" . _formatTime($post['time']) . ")</span></div><p class='post-body'>" . _parsePost($post['text']) . "</p>\n";
        }
        if (_pagingmode == 2 or _pagingmode == 3) {
            $module .= '<br />' . $paging[0];
        }
    } else {
        $module .= $_lang['global.nokit'];
    }

}
