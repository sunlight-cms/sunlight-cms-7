<?php
/* ----  kontrola jadra  ---- */
if (!defined('_core')) {
    exit;
}

/**
 * Vytvoreni vypisu prispevku
 *
 * Type Popis               Vars
 * 1    komentare sekce     zamknute komentare 1/0
 * 2    komentare článku    zamknute komentare 1/0
 * 3    prispevky v knize   [polozek na stranu, povoleno prispivani 1/0, zamknuto 1/0]
 * 5    temata ve foru      [polozek na stranu, povoleno prispivani 1/0, zamknuto 1/0]
 * 6    odpovedi na tema    [polozek na stranu, povoleno prispivani 1/0, zamknuto 1/0, id tematu]
 * 7    vypis vzkazu        [zamknuto 1/0]
 * 8    vypis pluginpostu   [polozek na stranu, povoleno prispivani 1/0, zamknuto 1/0, plugin flag, radit sestupne 1/0, [titulek / null]]
 *
 * @param int $type typ prispevku
 * @param int $home id polozky asociovane s komentari
 * @param mixed $vars promenna nastaveni podle typu
 * @param bool $force_locked vynutit zamknuty stav
 * @param string|null $url vlastni url nebo null (= automaticky)
 * @return string
 */
function _postsOutput($type, $home, $vars, $force_locked = false, $url = null)
{
    global $_lang;

    /* ---  typ  --- */

    // vychozi hodnoty
    $desc = "DESC ";
    $ordercol = 'id';
    $countcond = "type=" . $type . " AND xhome=-1 AND home=" . $home;
    $locked_textid = '';
    $autolast = false;
    $postlink = false;
    $pluginflag = null;

    // url
    if (!isset($url)) $url = _indexOutput_url;
    $url_html = _htmlStr($url);

    switch ($type) {

            // komentare v sekci
        case 1:
            $posttype = 1;
            $xhome = -1;
            $subclass = "comments";
            $title = $_lang['posts.comments'];
            $addlink = $_lang['posts.addcomment'];
            $nopostsmessage = $_lang['posts.nocomments'];
            $postsperpage = _commentsperpage;
            $canpost = _loginright_postcomments;
            $locked = _boolean($vars);
            $replynote = true;
            break;

            // komentare u clanku
        case 2:
            $posttype = 2;
            $xhome = -1;
            $subclass = "comments";
            $title = $_lang['posts.comments'];
            $addlink = $_lang['posts.addcomment'];
            $nopostsmessage = $_lang['posts.nocomments'];
            $postsperpage = _commentsperpage;
            $canpost = _loginright_postcomments;
            $locked = _boolean($vars);
            $replynote = true;
            break;

            // prispevky v knize
        case 3:
            $posttype = 3;
            $xhome = -1;
            $subclass = "book";
            $title = null;
            $addlink = $_lang['posts.addpost'];
            $nopostsmessage = $_lang['posts.noposts'];
            $postsperpage = $vars[0];
            $canpost = $vars[1];
            $locked = _boolean($vars[2]);
            $replynote = true;
            break;

            // temata ve foru
        case 5:
            $posttype = 5;
            $xhome = -1;
            $subclass = "book";
            $title = null;
            $addlink = $_lang['posts.addtopic'];
            $nopostsmessage = $_lang['posts.notopics'];
            $postsperpage = $vars[0];
            $canpost = $vars[1];
            $locked = _boolean($vars[2]);
            $replynote = true;
            $ordercol = 'bumptime';
            $locked_textid = '3';
            break;

            // odpovedi v tematu
        case 6:
            $posttype = 5;
            $xhome = $vars[3];
            $subclass = "book";
            $title = null;
            $addlink = $_lang['posts.addanswer'];
            $nopostsmessage = $_lang['posts.noanswers'];
            $postsperpage = $vars[0];
            $canpost = $vars[1];
            $locked = _boolean($vars[2]);
            $replynote = false;
            $desc = "";
            $countcond = "type=5 AND xhome=" . $xhome . " AND home=" . $home;
            $autolast = isset($_GET['autolast']);
            $postlink = true;
            break;

            // odpovedi v konverzaci
        case 7:
            $posttype = 6;
            $xhome = null;
            $subclass = "book";
            $title = null;
            $addlink = $_lang['posts.addanswer'];
            $nopostsmessage = $_lang['posts.noanswers'];
            $postsperpage = _messagesperpage;
            $canpost = true;
            $locked = _boolean($vars[0]);
            $replynote = false;
            $desc = "";
            $countcond = "type=6 AND home=" . $home;
            $locked_textid = '4';
            $autolast = true;
            break;

            // plugin posty
        case 8:
            $posttype = 7;
            $xhome = -1;
            $subclass = "book";
            $title = (isset($vars[5]) ? $vars[5] : null);
            $addlink = $_lang['posts.addpost'];
            $nopostsmessage = $_lang['posts.noposts'];
            $postsperpage = $vars[0];
            $canpost = $vars[1];
            $locked = _boolean($vars[2]);
            $replynote = true;
            $pluginflag = $vars[3];
            $countcond .= " AND flag=" . $pluginflag;
            if (!$vars[4]) $desc = '';
            break;

    }

    // vynutit uzamceni parametrem
    if ($force_locked) {
        $locked = true;
    }

    // extend
    $callback = null;
    _extend('call', 'posts.output', array(
        'type' => $type,
        'home' => $home,
        'xhome' => $xhome,
        'vars' => $vars,
        'post_type' => $posttype,
        'plugin_flag' => $pluginflag,
        'canpost' => &$canpost,
        'locked' => &$locked,
        'autolast' => &$autolast,
        'post_link' => &$postlink,
        'posts_per_page' => &$postsperpage,
        'sql_desc' => &$desc,
        'sql_ordercol' => &$ordercol,
        'sql_countcond' => &$countcond,
        'callback' => &$callback,
    ));

    /* ---  vystup  --- */
    $output = "
  <div class='anchor'><a name='posts'></a></div>
  <div class='posts-" . $subclass . "'>
  ";

    if ($title != null) $output .= "<h2>" . $title . _linkRss($home, $posttype) . "</h2>\n";

    $output .= "<div class='posts-form' id='post-form'>\n";

    /* ---  priprava strankovani  --- */
    $paging = _resultPaging($url_html, $postsperpage, "posts", $countcond, "#posts", null, $autolast);

    /* ---  zprava  --- */
    if (isset($_GET['r'])) {
        switch ($_GET['r']) {
            case 0:
                $output .= _formMessage(2, $_lang['posts.failed']);
                break;
            case 1:
                $output .= _formMessage(1, $_lang[(($type != 5) ? 'posts.added' : 'posts.topicadded')]);
                break;
            case 2:
                $output .= _formMessage(2, str_replace("*postsendexpire*", _postsendexpire, $_lang['misc.requestlimit']));
                break;
            case 3:
                $output .= _formMessage(2, $_lang['posts.guestnamedenied']);
                break;
            case 4:
                $output .= _formMessage(2, $_lang['xsrf.msg']);
                break;
        }
    }

    /* ---  formular nebo odkaz na pridani  --- */
    if (!$locked and (isset($_GET['addpost']) or isset($_GET['replyto']))) {

        // nacteni cisla prispevku pro odpoved
        if ($xhome == -1) {
            if (isset($_GET['replyto']) and $_GET['replyto'] != -1) {
                $reply = intval($_GET['replyto']);
                if ($replynote) {
                    $output .= "<p>" . $_lang['posts.replynote'] . " (<a href='" . $url_html . "#posts'>" . $_lang['global.cancel'] . "</a>).</p>";
                }
            } else {
                $reply = -1;
            }
        } else {
            $reply = $xhome;
        }

        // formular nebo prihlaseni
        if ($canpost) {
            $form = _uniForm("postform", array('posttype' => $type, 'pluginflag' => $pluginflag, 'posttarget' => $home, 'xhome' => $reply, 'url' => $url));
            $output .= $form[0];
        } else {
            $loginform = _uniForm("login", array(), true);
            $output .= "<p>" . $_lang['posts.loginrequired'] . "</p>" . $loginform[0];
        }

    } else {
        if (!$locked) $output .= "<a href='" . _addGetToLink($url_html, "addpost&amp;page=" . $paging[2]) . "#posts'><strong>" . $addlink . " &gt;</strong></a>";
        else $output .= "<img src='" . _templateImage("icons/lock.png") . "' alt='stop' class='icon' /> <strong>" . $_lang['posts.locked' . $locked_textid] . "</strong>";
    }

    $output .= "</div>
<div class='hr'><hr /></div>\n\n";

    /* ---  vypis  --- */
    if (_pagingmode == 1 or _pagingmode == 2) $output .= $paging[0];

    // zaklad query
    if ($type == 5) $sql = "SELECT id,author,guest,subject,time,ip,locked,bumptime,sticky,(SELECT COUNT(id) FROM `" . _mysql_prefix . "-posts` WHERE type=5 AND xhome=post.id) AS answer_count";
    else $sql = "SELECT id,xhome,subject,text,author,guest,time,ip" . _extend('buffer', 'posts.columns');
    $sql .= " FROM `" . _mysql_prefix . "-posts` AS post";

    // podminky a razeni
    $sql .= " WHERE post.type=" . $posttype . (isset($xhome) ? " AND post.xhome=" . $xhome : '') . " AND post.home=" . $home . (isset($pluginflag) ? " AND post.flag=" . $pluginflag : '');
    $sql .= " ORDER BY " . (($type == 5) ? 'sticky DESC,' : '') . $ordercol . ' ' . $desc . $paging[1];

    // dotaz
    $query = DB::query($sql);
    unset($sql);

    // nacteni prispevku do pole
    $items = array();
    if ($type == 5) $item_ids_with_answers = array();
    while ($item = DB::row($query)) {
        $items[$item['id']] = $item;
        if ($type == 5 && $item['answer_count'] != 0) $item_ids_with_answers[] = $item['id'];
    }

    // uvolneni dotazu
    DB::free($query);

    if ($type == 5) {
        // posledni prispevek (pro vypis temat)
        if (!empty($item_ids_with_answers)) {
            $topicextra = DB::query("SELECT * FROM (SELECT id,xhome,author,guest FROM `" . _mysql_prefix . "-posts` AS reply WHERE type=5 AND home=" . $home . " AND xhome IN(" . implode(',', $item_ids_with_answers) . ") ORDER BY reply.id DESC) AS replies GROUP BY xhome");
            while ($item = DB::row($topicextra)) {
                if (!isset($items[$item['xhome']])) {
                    if (_dev) trigger_error('Nenalezen domovsky prispevek pro odpoved #' . $item['id'], E_USER_WARNING);
                    continue;
                }
                $items[$item['xhome']]['_lastpost'] = $item;
            }
        }
    } elseif (!empty($items)) {
        // odpovedi (pro komentare)
        $answers = DB::query("SELECT id,xhome,text,author,guest,time,ip FROM `" . _mysql_prefix . "-posts` WHERE type=" . $posttype . " AND home=" . $home . (isset($pluginflag) ? " AND flag=" . $pluginflag : '') . " AND xhome IN(" . implode(',', array_keys($items)) . ") ORDER BY id");
        while ($item = DB::row($answers)) {
            if (!isset($items[$item['xhome']])) {
                if (_dev) trigger_error('Nenalezen domovsky prispevek pro odpoved #' . $item['id'], E_USER_WARNING);
                continue;
            }
            if (!isset($items[$item['xhome']]['_answers'])) $items[$item['xhome']]['_answers'] = array();
            $items[$item['xhome']]['_answers'][] = $item;
        }
        DB::free($answers);
    }

    // vypis
    if (!empty($items)) {

        // vypis prispevku nebo temat
        if ($type != 5) {
            $hl = true;
            foreach ($items as $item) {

                // nacteni autora
                if ($item['guest'] == "") $author = _linkUser($item['author'], "post-author");
                else $author = "<span class='post-author-guest' title='" . _showIP($item['ip']) . "'>" . $item['guest'] . "</span>";

                // odkazy pro spravu
                $post_access = _postAccess($item);
                if ($type < 6 or $type > 7 or $post_access) {
                    $actlinks = " <span class='post-actions'>";
                    if (($type < 6 or $type > 7) && !$locked) $actlinks .= "<a href='" . _addGetToLink($url_html, "replyto=" . $item['id']) . "#posts'>" . $_lang['posts.reply'] . "</a>";
                    if ($post_access) $actlinks .= (($type < 6 or $type > 7) ? " " : '') . "<a href='index.php?m=editpost&amp;id=" . $item['id'] . "'>" . $_lang['global.edit'] . "</a>";
                    $actlinks .= "</span>";
                } else {
                    $actlinks = "";
                }

                // avatar
                if (_show_avatars) {
                    $avatar = _getAvatar($item['author']);
                } else {
                    $avatar = null;
                }

                // prispevek
                $hl = !$hl;
                _extend('call', 'posts.post', array('item' => &$item, 'avatar' => &$avatar, 'type' => $type));
                if (null === $callback) {
                    $output .= "<div id='post-" . $item['id'] . "' class='post" . ($hl ? ' post-hl' : '') . (isset($avatar) ? ' post-withavatar' : '') . "'><div class='post-head'>" . $author;
                    if ($type < 6 || $type > 7) $output .= ", <span class='post-subject'>" . $item['subject'] . "</span> ";
                    $output .= "<span class='post-info'>(" . _formatTime($item['time']) . ")</span>" . $actlinks . ($postlink ? "<a class='post-postlink' href='" . _addGetToLink($url_html, 'page=' . $paging[2]) . "#post-" . $item['id'] . "'><span>#" . str_pad($item['id'], 6, '0', STR_PAD_LEFT) . "</span></a>" : '') . "</div><div class='post-body" . (isset($avatar) ? ' post-body-withavatar' : '') . "'>" . $avatar . '<div class="post-body-text">' . _parsePost($item['text']) . "</div></div></div>\n";
                } else {
                    $output .= call_user_func($callback, array(
                        'item' => $item,
                        'avatar' => $avatar,
                        'author' => $author,
                        'actlinks' => $actlinks,
                        'page' => $paging[2],
                        'postlink' => $postlink,
                    ));
                }

                // odpovedi
                if (($type < 6 || $type > 7) && isset($item['_answers'])) {
                    foreach ($item['_answers'] as $answer) {

                        // jmeno autora
                        if ($answer['guest'] == "") $author = _linkUser($answer['author'], "post-author");
                        else $author = "<span class='post-author-guest' title='" . _showIP($answer['ip']) . "'>" . $answer['guest'] . "</span>";

                        // odkazy pro spravu
                        if (_postAccess($answer)) $actlinks = " <span class='post-actions'><a href='index.php?m=editpost&amp;id=" . $answer['id'] . "'>" . $_lang['global.edit'] . "</a></span>";
                        else $actlinks = "";

                        // avatar
                        if (_show_avatars) $avatar = _getAvatar($answer['author']);
                        else $avatar = null;

                        _extend('call', 'posts.post', array('item' => &$answer, 'avatar' => &$avatar, 'type' => $type));
                        if (null === $callback) {
                            $output .= "<div id='post-" . $answer['id'] . "' class='post-answer" . (isset($avatar) ? ' post-answer-withavatar' : '') . "'><div class='post-head'>" . $author . " " . $_lang['posts.replied'] . " <span class='post-info'>(" . _formatTime($answer['time']) . ")</span>" . $actlinks . "</div><div class='post-body" . (isset($avatar) ? ' post-body-withavatar' : '') . "'>" . $avatar . '<div class="post-body-text">' . _parsePost($answer['text']) . "</div></div></div>\n";
                        } else {
                            $output .= call_user_func($callback, array(
                                'item' => $answer,
                                'avatar' => $avatar,
                                'author' => $author,
                                'actlinks' => $actlinks,
                                'page' => $paging[2],
                                'postlink' => $postlink,
                            ));
                        }
                    }
                }

            }
            if (_pagingmode == 2 or _pagingmode == 3) $output .= "<br />" . $paging[0];
        } else {

            // tabulka s tematy
            $hl = false;
            $output .= "\n<table class='topic-table'>\n<thead><tr><td colspan='2'><strong>" . $_lang['posts.topic'] . "</strong></td><td><strong>" . $_lang['global.answersnum'] . "</strong></td><td><strong>" . $_lang['global.lastanswer'] . "</strong></td></tr></thead>\n<tbody>\n";
            foreach ($items as $item) {

                // nacteni autora
                if ($item['guest'] == "") $author = _linkUser($item['author'], "post-author", false, false, 16);
                else $author = "<span class='post-author-guest' title='" . _showIP($item['ip']) . "'>" . _cutStr($item['guest'], 16) . "</span>";

                // nacteni jmena autora posledniho prispevku
                if (isset($item['_lastpost'])) {
                    if ($item['_lastpost']['author'] != -1) $lastpost = _linkUser($item['_lastpost']['author'], "post-author", false, false, 16);
                    else $lastpost = "<span class='post-author-guest'>" . _cutStr($item['_lastpost']['guest'], 16) . "</span>";
                } else {
                    $lastpost = "-";
                }

                // vyber ikony
                if ($item['sticky']) $icon = 'sticky';
                elseif ($item['locked']) $icon = 'locked';
                elseif ($item['answer_count'] == 0) $icon = 'new';
                elseif ($item['answer_count'] < _topic_hot_ratio) $icon = 'normal';
                else $icon = 'hot';

                // mini strankovani
                $tpages = '';
                $tpages_num = ceil($item['answer_count'] / _commentsperpage);
                if ($tpages_num == 0) $tpages_num = 1;
                if ($tpages_num > 1) {
                    $tpages .= '<span class=\'topic-pages\'>';
                    for ($i = 1; $i <= 3 && $i <= $tpages_num; ++$i) {
                        $tpages .= "<a href='index.php?m=topic&amp;id=" . $item['id'] . "&amp;page=" . $i . "#posts'>" . $i . '</a>';
                    }
                    if ($tpages_num > 3) $tpages .= "<a href='index.php?m=topic&amp;id=" . $item['id'] . "&amp;page=" . $tpages_num . "'>" . $tpages_num . ' &rarr;</a>';
                    $tpages .= '</span>';
                }

                // vystup radku
                $output .= "<tr class='topic-" . $icon . ($hl ? ' topic-hl' : '') . "'><td class='topic-icon-cell'><a href='index.php?m=topic&amp;id=" . $item['id'] . "'><img src='" . _templateImage('icons/topic-' . $icon . '.png') . "' alt='" . $_lang['posts.topic.' . $icon] . "' /></a></td><td class='topic-main-cell'><a href='index.php?m=topic&amp;id=" . $item['id'] . "'>" . $item['subject'] . "</a>" . $tpages . "<br />" . $author . " <small class='post-info'>(" . _formatTime($item['time']) . ")</small></td><td>" . $item['answer_count'] . "</td><td>" . $lastpost . (($item['answer_count'] != 0) ? "<br /><small class='post-info'>(" . _formatTime($item['bumptime']) . ")</small>" : '') . "</td></tr>\n";
                $hl = !$hl;
            }
            $output .= "</tbody></table><br />\n\n";
            if (_pagingmode == 2 or _pagingmode == 3) $output .= $paging[0] . "<br />";

            // posledni odpovedi
            $output .= "\n<div class='hr'><hr /></div><br />\n<h3>" . $_lang['posts.forum.lastact'] . "</h3>\n";
            $query = DB::query("SELECT topic.id AS topic_id,topic.subject AS topic_subject,answer.author,answer.guest,answer.time FROM `" . _mysql_prefix . "-posts` AS answer JOIN `" . _mysql_prefix . "-posts` AS topic ON(topic.type=5 AND topic.id=answer.xhome) WHERE answer.type=5 AND answer.home=" . $home . " AND answer.xhome!=-1 ORDER BY answer.id DESC LIMIT " . _extratopicslimit);
            if (DB::size($query) != 0) {
                $output .= "<ul>\n";
                while ($item = DB::row($query)) {
                    if ($item['guest'] == "") $author = _linkUser($item['author']);
                    else $author = "<span class='post-author-guest'>" . $item['guest'] . "</span>";
                    $output .= "<li><a href='index.php?m=topic&amp;id=" . $item['topic_id'] . "'>" . $item['topic_subject'] . "</a>&nbsp;&nbsp;<small>(" . $_lang['global.postauthor'] . " " . $author . " " . _formatTime($item['time']) . ")</small></li>\n";
                }
                $output .= "</ul>\n\n";

            } else {
                $output .= "<p>" . $_lang['global.nokit'] . "</p>";
            }

        }

    } else {
        $output .= "<p>" . $nopostsmessage . "</p>";
    }

    $output .= "</div>";

    return $output;
}

/**
 * Vymazani urciteho typu plugin postu
 * @param int $flag plugin post flag
 * @param int|null $home sloupec home
 * @param bool $get_count nemazat, ale vratit pocet 1/0
 */
function _postsDeleteByPluginFlag($flag, $home, $get_count = true)
{
    // sestaveni podminky
    $cond = "type=7 AND flag=" . $flag;
    if (isset($home)) $cond .= " AND home=" . $home;

    // smazani nebo pocet
    if ($get_count) return DB::count(_mysql_prefix . '-posts', $cond);
    DB::query('DELETE FROM `' . _mysql_prefix . '-posts` WHERE ' . $cond);
}
