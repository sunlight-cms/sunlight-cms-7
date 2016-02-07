<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  definice funkce modulu  --- */
function _HCM_recentposts($limit = null, $stranky = "", $typ = null)
{
    // priprava
    $result = "";
    if (isset($limit) and intval($limit) >= 1) {
        $limit = abs(intval($limit));
    } else {
        $limit = 10;
    }

    // filtr cisel sekci, knih nebo clanku
    if (isset($stranky) and isset($typ)) {
        $rtype = intval($typ);
        if ($rtype < 1 or $rtype > 3) {
            $rtype = 1;
        }
        $rroots = "(" . _sqlWhereColumn("home", $stranky) . ") AND type=" . $rtype;
    } else {
        $rroots = "type!=4 AND type!=6";
    }

    $query = DB::query("SELECT id,type,home,xhome,subject,author,guest,time,text FROM `" . _mysql_prefix . "-posts` WHERE " . $rroots . " ORDER BY id DESC LIMIT " . $limit);
    while ($item = DB::row($query)) {

        // nacteni titulku a odkazu na stranku
        switch ($item['type']) {
            case 1:
            case 3:
                $hometitle = DB::query_row("SELECT title,title_seo FROM `" . _mysql_prefix . "-root` WHERE id=" . $item['home']);
                $homelink = _linkRoot($item['home'], $hometitle['title_seo']);
                break;
            case 2:
                $hometitle = DB::query_row("SELECT art.title,art.title_seo,cat.title_seo AS cat_title_seo FROM `" . _mysql_prefix . "-articles` AS art JOIN `" . _mysql_prefix . "-root` AS cat ON(cat.id=art.home1) WHERE art.id=" . $item['home']);
                $homelink = _linkArticle($item['home'], $hometitle['title_seo'], $hometitle['cat_title_seo']);
                break;

            case 5:
                if ($item['xhome'] == -1) {
                    $tid = $item['id'];
                    $hometitle = array("title" => $item['subject']);
                } else {
                    $tid = $item['xhome'];
                    $hometitle = DB::query_row("SELECT subject FROM `" . _mysql_prefix . "-posts` WHERE id=" . $item['xhome']);
                    $hometitle = array("title" => $hometitle['subject']);
                }

                $homelink = "index.php?m=topic&amp;id=" . $tid;
                break;

        }

        // nacteni jmena autora
        if ($item['author'] != -1) {
            $authorname = _linkUser($item['author'], null, true, true);
        } else {
            $authorname = $item['guest'];
        }

        $hometitle = $hometitle['title'];

        $result .= "
<h2 class='list-title'><a href='" . $homelink . "'>" . $hometitle . "</a></h2>
<p class='list-perex'>" . _cutStr(strip_tags(_parsePost($item['text'])), 256) . "</p>
<div class='list-info'>
<span>" . $GLOBALS['_lang']['global.postauthor'] . ":</span> " . $authorname . _template_listinfoseparator . "
<span>" . $GLOBALS['_lang']['global.time'] . ":</span> " . _formatTime($item['time']) . "
</div>\n
";

    }

    return $result;
}
