<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  priprava  --- */
$topictitle = "";
$forumtitle = "";
$continue = false;

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $query = DB::query("SELECT * FROM `" . _mysql_prefix . "-posts` WHERE id=" . $id . " AND type=5 AND xhome=-1");
    if (DB::size($query) != 0) {
        $query = DB::row($query);
        $topictitle = $query['subject'];
        $homedata = DB::query_row("SELECT id,title,title_seo,public,level,var1,var2,var3 FROM `" . _mysql_prefix . "-root` WHERE id=" . $query['home']);
        $forumtitle = $homedata['title'];
        $continue = true;
        if (_publicAccess($homedata['public'], $homedata['level'])) $need2login = false;
        else $need2login = true;
    }
}

/* ---  vystup  --- */
if ($continue) {
    define('_indexOutput_url', "index.php?m=topic&id=" . $id);

    if (!$need2login) {

        // priprava zpetneho odkazu
        $backlink = _linkRoot($homedata['id'], $homedata['title_seo']);
        if (!$query['sticky']) $backlink = _addGetToLink($backlink, "page=" . _resultPagingGetItemPage($homedata['var1'], "posts", "bumptime>" . $query['bumptime'] . " AND xhome=-1 AND type=5 AND home=" . $homedata['id']));

        // zpetny odkaz, titulek
        $module .= "<a href='" . $backlink . "' class='backlink'>&lt; " . $_lang['global.return'] . "</a>\n";
        if (_template_autoheadings) $module .= "<h1>" . $homedata['title'] . "</h1>\n<div class='hr'><hr /></div>\n";

        // ikonky pro spravu
        if (_postAccess($query)) {
            $editlink = " &nbsp;
<a href='index.php?m=editpost&amp;id=" . $id . "'><img src='" . _templateImage("icons/edit.png") . "' alt='edit' class='icon' title='" . $_lang['mod.editpost'] . "' /></a>
" . (_loginright_locktopics ? "<a href='index.php?m=locktopic&amp;id=" . $id . "'><img src='" . _templateImage("icons/" . (($query['locked'] == 1) ? 'un' : '') . "lock.png") . "' alt='lock' class='icon' title='" . ($_lang['mod.locktopic' . (($query['locked'] == 1) ? '2' : '')]) . "' /></a>" : '') . "
" . (_loginright_stickytopics ? "<a href='index.php?m=stickytopic&amp;id=" . $id . "'><img src='" . _templateImage("icons/" . (($query['sticky'] == 1) ? 'un' : '') . "stick.png") . "' alt='sticky' class='icon' title='" . ($_lang['mod.stickytopic' . (($query['sticky'] == 1) ? '2' : '')]) . "' /></a>" : '') . "
" . (_loginright_movetopics ? "<a href='index.php?m=movetopic&amp;id=" . $id . "'><img src='" . _templateImage("icons/move.png") . "' alt='move' class='icon' title='" . ($_lang['mod.movetopic']) . "' /></a>" : '');
        } else {
            $editlink = "";
        }

        // nacteni autora a avataru
        $avatar = '';
        if ($query['guest'] == "") {
            $author = _linkUser($query['author'], "post-author");
            if (_show_avatars) {
                $avatar = _getAvatar($query['author'], true, true);
                if (null === $avatar) $avatar = '';
                else {
                    $author_name = _userDataCache($query['author']);
                    if ('' !== $author_name['publicname']) $author_name = $author_name['publicname'];
                    else $author_name = $author_name['username'];
                    $avatar = "<img src='" . $avatar . "' alt='" . $author_name . "' class='topic-avatar' />";
                }
            }
        } else {
            $author = "<span class='post-author-guest' title='" . _showIP($query['ip']) . "'>" . $query['guest'] . "</span>";
        }

        // vystup
        $module .= "
<h2>" . $_lang['posts.topic'] . ": " . $query['subject'] . _linkRSS($id, 6) . "</h2>
<p><small>" . $_lang['global.postauthor'] . " " . $author . " " . _formatTime($query['time']) . "</small>" . $editlink . "</p>
<p>" . $avatar . _parsePost($query['text']) . "</p>
<div class='cleaner'></div>
";

        // odpovedi
        require_once (_indexroot . 'require/functions-posts.php');
        $module .= _postsOutput(6, $homedata['id'], array(_commentsperpage, _publicAccess($homedata['var3']), $homedata['var2'], $id), ($query['locked'] == 1));

    } else {

        $form = _uniForm("notpublic");
        $module .= $form[0];

    }

} else {
    define('_indexOutput_url', "index.php?m=topic");
    if (_template_autoheadings) {
        $module .= "<h1>" . $_lang['global.error404.title'] . "</h1>\n";
    }
    $module .= _formMessage(2, $_lang['posts.topic.notfound']);
    $found = false;
}

/* ---  titulek  --- */
if ($forumtitle != "" and $topictitle != "") define('_indexOutput_title', $forumtitle . " " . _titleseparator . " " . $topictitle);
else define('_indexOutput_title', $_lang['mod.topic']);
