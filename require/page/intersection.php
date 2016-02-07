<?php
// kontrola jadra
if (!defined('_core')) {
    exit;
}

// titulek
$title = $query['title'];
if (_template_autoheadings && $query['autotitle']) {
    $content .= "<h1>" . $query['title'] . "</h1>\n";
}
_extend('call', 'page.intersection.aftertitle', $extend_args);

// obsah
_extend('call', 'page.intersection.content.before', $extend_args);
if ($query['content'] != "") $content .= _parseHCM($query['content']) . "\n\n<div class='hr'><hr /></div>\n\n";
_extend('call', 'page.intersection.content.after', $extend_args);

// vypis polozek
$items = DB::query("SELECT id,title,title_seo,type,type_idt,intersectionperex,var1 FROM `" . _mysql_prefix . "-root` WHERE intersection=" . $id . " AND visible=1 ORDER BY ord");
if (DB::size($items) != 0) {
    while ($item = DB::row($items)) {

        // titulek
        $content .= "<h2 class='list-title'><a href='" . _linkRoot($item['id'], $item['title_seo']) . "'" . (($item['type'] == 6 and $item['var1'] == 1) ? " target='_blank'" : '') . ">" . $item['title'] . "</a></h2>\n";

        // perex
        if ($item['intersectionperex'] != "") {
            $content .= "<p class='list-perex'>" . $item['intersectionperex'] . "</p>\n";
        }

        // informace
        if ($query['var1'] == 1) {
            $iteminfo = "";

            switch ($item['type']) {

                    // sekce
                case 1:
                    if ($item['var1'] == 1) {
                        $iteminfo .= "<span>" . $_lang['article.comments'] . ":</span> " . DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-posts` WHERE type=1 AND home=" . $item['id']), 0);
                    }
                    break;

                    // kategorie
                case 2:
                    $iteminfo .= "<span>" . $_lang['global.articlesnum'] . ":</span> " . DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-articles` AS art WHERE (home1=" . $item['id'] . " OR home2=" . $item['id'] . " OR home3=" . $item['id'] . ") AND " . _sqlArticleFilter()), 0);
                    break;

                    // kniha
                case 3:

                    // nacteni jmena autora posledniho prispevku
                    $lastpost = DB::query("SELECT author,guest FROM `" . _mysql_prefix . "-posts` WHERE home=" . $item['id'] . " ORDER BY id DESC LIMIT 1");
                    if (DB::size($lastpost) != 0) {
                        $lastpost = DB::row($lastpost);
                        if ($lastpost['author'] != -1) {
                            $lastpost = _linkUser($lastpost['author'], null, true, true);
                        } else {
                            $lastpost = $lastpost['guest'];
                        }
                    } else {
                        $lastpost = "-";
                    }

                    $iteminfo .= "<span>" . $_lang['global.postsnum'] . ":</span> " . DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-posts` WHERE type=3 AND home=" . $item['id']), 0) . _template_listinfoseparator . "<span>" . $_lang['global.lastpost'] . ":</span> " . $lastpost;
                    break;

                    // galerie
                case 5:
                    $iteminfo .= "<span>" . $_lang['global.imgsnum'] . ":</span> " . DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-images` WHERE home=" . $item['id']), 0);
                    break;

                    // forum
                case 8:
                    $iteminfo .= "<span>" . $_lang['global.topicsnum'] . ":</span> " . DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-posts` WHERE type=5 AND home=" . $item['id'] . " AND xhome=-1"), 0) . _template_listinfoseparator . "<span>" . $_lang['global.answersnum'] . ":</span> " . DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-posts` WHERE type=5 AND home=" . $item['id'] . " AND xhome!=-1"), 0);
                    break;

                    // plugin stranka
                case 9:
                    $iteminfo = _extend('buffer', 'ppage.' . $item['type_idt'] . '.interinfo', array('item' => $item));
                    break;

            }

            if ($iteminfo != "") {
                $content .= "<div class='list-info'>" . $iteminfo . "</div>\n";
            }
        }

        $content .= "\n";
    }
} else {
    $content .= $_lang['global.nokit'];
}
