<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  pripava promennych  --- */
$message = "";
$continue = false;
$scriptbreak = false;
$backlink = _indexroot;
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $query = DB::query("SELECT * FROM `" . _mysql_prefix . "-posts` WHERE id=" . $id);
    if (DB::size($query) != 0) {
        $query = DB::row($query);
        if (_postAccess($query)) {
            $continue = true;

            $nobbcode = false;
            $backlink = null;
            _extend('call', 'mod.editpost.backlink', array('backlink' => &$backlink, 'query' => $query));
            if (null === $backlink) {
                switch ($query['type']) {
                    case 1:
                        $backlink = _addGetToLink(_linkRoot($query['home']), "page=" . _resultPagingGetItemPage(_commentsperpage, "posts", "id>" . $query['id'] . " AND type=1 AND xhome=-1 AND home=" . $query['home'])) . "#post-" . $query['id'];
                        break;
                    case 2:
                        $backlink = _addGetToLink(_linkArticle($query['home']), "page=" . _resultPagingGetItemPage(_commentsperpage, "posts", "id>" . $query['id'] . " AND type=2 AND xhome=-1 AND home=" . $query['home'])) . "#post-" . $query['id'];
                        break;
                    case 3:
                        $postsperpage = DB::query_row("SELECT var2 FROM `" . _mysql_prefix . "-root` WHERE id=" . $query['home']);
                        $backlink = _addGetToLink(_linkRoot($query['home']), "page=" . _resultPagingGetItemPage($postsperpage['var2'], "posts", "id>" . $query['id'] . " AND type=3 AND xhome=-1 AND home=" . $query['home'])) . "#post-" . $query['id'];
                        break;
                    case 4:
                        $nobbcode = true;
                        break;

                    case 5:
                        if ($query['xhome'] == -1) {
                            if (!_checkboxLoad("delete")) {
                                $backlink = "index.php?m=topic&amp;id=" . $query['id'];
                            } else {
                                $backlink = _linkRoot($query['home']);
                            }
                        } else {
                            $backlink = _addGetToLink("index.php?m=topic&amp;id=" . $query['xhome'], "page=" . _resultPagingGetItemPage(_commentsperpage, "posts", "id<" . $query['id'] . " AND type=5 AND xhome=" . $query['xhome'] . " AND home=" . $query['home'])) . "#post-" . $query['id'];
                        }
                        break;

                    case 6:
                        $backlink = "index.php?m=messages&amp;a=list&amp;read=" . $query['home'] . '&amp;page=' . _resultPagingGetItemPage(_messagesperpage, 'posts', 'id<' . $query['id'] . ' AND type=6 AND home=' . $query['home']) . '#posts-' . $query['id'];
                        break;

                    case 7:
                        $backlink = null;
                        _extend('call', 'posts.' . $query['flag'] . '.edit', array('query' => $query, 'backlink' => &$backlink));
                        if (null === $backlink) {
                            $module .= _formMessage(3, sprintf($_lang['plugin.error'], $query['flag']));

                            return;
                        }
                        break;
                    default:
                        $backlink = _indexroot;
                        break;
                }
            }

        }
    }
}

/* ---  ulozeni  --- */
if (isset($_POST['text']) and $continue) {

    if (!_checkboxLoad("delete")) {

        /* -  uprava  - */

        // nacteni promennych

        // jmeno hosta
        if ($query['guest'] != "") {
            $guest = $_POST['guest'];
            if (mb_strlen($guest) > 24) $guest = mb_substr($guest, 0, 24);
            $guest = _anchorStr($guest, false);
        } else {
            $guest = "";
        }

        $text = DB::esc(_htmlStr(_wsTrim(_cutStr($_POST['text'], (($query['type'] != 4) ? 16384 : 255), false))));
        if ($query['xhome'] == -1 and $query['type'] != 4) {
            $subject = DB::esc(_htmlStr(_wsTrim(_cutStr($_POST['subject'], (($query['type'] == 5) ? 48 : 22), false))));
        } else {
            $subject = "";
        }

        // vyplneni prazdnych poli
        if ($subject == "" and $query['xhome'] == -1 and $query['type'] != 4) {
            $subject = "-";
        }
        if ($guest == null and $query['guest'] != "") {
            $guest = $_lang['posts.anonym'];
        }

        // ulozeni
        if ($text != "") {
            DB::query("UPDATE `" . _mysql_prefix . "-posts` SET text='" . $text . "',subject='" . $subject . "'" . (isset($guest) ? ",guest='" . $guest . "'" : '') . " WHERE id=" . $id);
            define('_redirect_to', 'index.php?m=editpost&id=' . $id . '&saved');

            return;
        } else {
            $message = _formMessage(2, $_lang['mod.editpost.failed']);
        }

    } else {

        /* -  odstraneni  - */
        if ($query['type'] != 6 || $query['xhome'] != -1) {

            // debump topicu
            if ($query['type'] == 5 && $query['xhome'] != -1) {
                // kontrola, zda se jedna o posledni odpoved
                $chq = DB::query('SELECT id,time FROM `' . _mysql_prefix . '-posts` WHERE type=5 AND xhome=' . $query['xhome'] . ' ORDER BY id DESC LIMIT 2');
                $chr = DB::row($chq);
                if ($chr !== false && $chr['id'] == $id) {
                    // ano, debump podle casu predchoziho postu nebo samotneho topicu (pokud se smazala jedina odpoved)
                    $chr = DB::row($chq);
                    DB::query('UPDATE `' . _mysql_prefix . '-posts` SET bumptime=' . (($chr !== false) ? $chr['time'] : 'time') . ' WHERE id=' . $query['xhome']);
                }
            }

            // smazani prispevku a odpovedi
            DB::query("DELETE FROM `" . _mysql_prefix . "-posts` WHERE id=" . $id);
            if ($query['xhome'] == -1) DB::query("DELETE FROM `" . _mysql_prefix . "-posts` WHERE xhome=" . $id . " AND home=" . $query['home'] . " AND type=" . $query['type']);

            // info
            $message = _formMessage(1, $_lang['mod.editpost.deleted']);
            $scriptbreak = true;
            $continue = false;

           }

    }

}

/* ---  vystup  --- */

// titulek
if (_template_autoheadings == 1) {
    $module .= "<h1>" . $_lang['mod.editpost'] . "</h1><div class='hr'><hr /></div>";
}

// zpetny odkaz
$module .= "<p><a href='" . $backlink . "'>&lt; " . $_lang['global.return'] . "</a></p>";

// zprava
if (isset($_GET['saved']) and $message == "") $message = _formMessage(1, $_lang['global.saved']);
$module .= $message;

// formular
if ($continue) {

    // pole
    $inputs = array();
    $module .= _jsLimitLength((($query['type'] != 4) ? 16384 : 255), "postform", "text");
    if ($query['guest'] != "") $inputs[] = array($_lang['posts.guestname'], "<input type='text' name='guest' class='inputsmall' value='" . $query['guest'] . "' />");
    if ($query['xhome'] == -1 and $query['type'] != 4) $inputs[] = array($_lang[(($query['type'] != 5) ? 'posts.subject' : 'posts.topic')], "<input type='text' name='subject' class='input" . (($query['type'] == 5) ? 'medium' : 'small') . "' maxlength='" . (($query['type'] == 5) ? 48 : 22) . "' value='" . $query['subject'] . "' />");
    $inputs[] = array($_lang['posts.text'], "<textarea name='text' class='areamedium' rows='5' cols='33'>" . $query['text'] . "</textarea>", true);

    // formoutput
    $module .= _formOutput('postform', 'index.php?m=editpost&amp;id=' . $id, $inputs, null, $_lang['global.save'], _getPostformControls("postform", "text", $nobbcode) . (($query['type'] !=6 || $query['xhome'] != -1) ? "<br /><br /><label><input type='checkbox' name='delete' value='1' /> " . $_lang['mod.editpost.delete'] . "</label>" : ''));

} else {
    /*neplatny vstup*/
    if (!$scriptbreak) {
        $module .= _formMessage(3, $_lang['global.badinput']);
        $found = false;
    }
}
