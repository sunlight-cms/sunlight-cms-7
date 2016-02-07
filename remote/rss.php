<?php
/* ---  incializace jadra  --- */
require '../require/load.php';
define('_header', '');
SL::init('../');
if (!_rss) {
    exit;
}

/* ---  priprava promennych  --- */
$continue = false;
_checkKeys('_GET', array('tp', 'id'));
$type = intval($_GET['tp']);
$id = intval($_GET['id']);

// cast sql dotazu - verejnost
if (!_loginindicator) {
    if (!_notpublicsite) {
        $public = " AND public=1";
    } else {
        exit;
    }
} else {
    $public = "";
}

// nastaveni titulku, typu, casti sql...
$donottestsource = false;
$homelimit = " home=" . $id;
$pagetitle_column = "title";
$custom_cond = true;

switch ($type) {

        // komentare v sekci a prispevky v knize
    case 1:
    case 3:
        $query = DB::query("SELECT title FROM `" . _mysql_prefix . "-root` WHERE type=" . $type . ($type == 1 ? " AND var1=1" : '') . $public . " AND id=" . $id);
        $feedtitle = $_lang[(($type == 1) ? 'rss.recentcomments' : 'rss.recentposts')];
        $typelimit = " AND type=" . $type;
        break;

        // komentare u clanku
    case 2:
        $query = DB::query("SELECT id,time,confirmed,public,home1,home2,home3,title FROM `" . _mysql_prefix . "-articles` WHERE id=" . $id . " AND comments=1");
        $donottestsource = true;

        // test pristupu k clanku
        $custom_cond = false;
        if (DB::size($query) != 0) {
            $custom_cond = _articleAccess(DB::row($query));
            if ($custom_cond == 1) {
                $custom_cond = true;
            }
        }

        $feedtitle = $_lang['rss.recentcomments'];
        $typelimit = " AND type=2";
        break;

        // nejnovejsi clanky
    case 4:

        if ($id != -1) {
            $query = DB::query("SELECT title FROM `" . _mysql_prefix . "-root` WHERE type=2" . $public . " AND id=" . $id);
            $catlimit = " AND (home1=" . $id . " OR home2=" . $id . " OR home3=" . $id . ")";
        } else {
            $donottestsource = true;
            $query = array("title" => null);
            $catlimit = "";
        }

        $feedtitle = $_lang['rss.recentarticles'];
        break;

        // nejnovejsi temata
    case 5:
        $query = DB::query("SELECT title FROM `" . _mysql_prefix . "-root` WHERE type=8" . $public . " AND id=" . $id);
        $feedtitle = $_lang['rss.recenttopics'];
        $typelimit = " AND type=5 AND xhome=-1";
        break;

        // nejnovejsi odpovedi na tema
    case 6:
        $query = DB::query("SELECT subject FROM `" . _mysql_prefix . "-posts` WHERE type=5 AND id=" . $id . " AND (" . _loginindicator . "=1 OR (SELECT public FROM `" . _mysql_prefix . "-root` WHERE id=`" . _mysql_prefix . "-posts`.id)=1)");
        $feedtitle = $_lang['rss.recentanswers'];
        $typelimit = "type=5 AND xhome=" . $id;
        $homelimit = "";
        $pagetitle_column = "subject";
        break;

        // nelegalni typ
    default:
        exit;
        break;

}

// nacteni polozek
if ($custom_cond and ($donottestsource or DB::size($query) != 0)) {
    $feeditems = array();
    if (!$donottestsource) {
        $query = DB::row($query);
    }
    $pagetitle = $query[$pagetitle_column];

    switch ($type) {

            // komentare/prispevky/temata
        case 1:
        case 2:
        case 3:
        case 5:
        case 6:
            $items = DB::query("SELECT * FROM `" . _mysql_prefix . "-posts` WHERE " . $homelimit . $typelimit . " ORDER BY id DESC LIMIT " . _rsslimit);
            $titlebonus = "";
            while ($item = DB::row($items)) {

                // nacteni jmena autora
                if ($item['author'] != -1) {
                    $author = _linkUser($item['author'], null, true, true);
                } else {
                    $author = $item['guest'];
                }

                // odkaz na stranku
                switch ($item['type']) {
                    case 1:
                    case 3:
                        $homelink = _linkRoot($item['home']);
                        break;
                    case 2:
                        $homelink = _linkArticle($item['home']);
                        break;
                    case 5:
                        if ($item['xhome'] == -1) {
                            $homelink = "index.php?m=topic&amp;id=" . $item['id'];
                        } else {
                            $homelink = "index.php?m=topic&amp;id=" . $item['xhome'];
                        }
                        break;
                }

                // ulozeni zaznamu
                $feeditems[] = array($author . ": " . $item['subject'], $homelink . "#posts", _cutStr(strip_tags(_parsePost($item['text'])), 255, false), $item['time']);

            }
            break;

            // nejnovejsi clanky
        case 4:
            $items = DB::query("SELECT art.id,art.time,art.confirmed,art.public,art.home1,art.home2,art.home3,art.title,art.title_seo,art.perex,cat.title_seo AS cat_title_seo FROM `" . _mysql_prefix . "-articles` AS art JOIN `" . _mysql_prefix . "-root` AS cat ON(cat.id=art.home1) WHERE " . _sqlArticleFilter(true) . $catlimit . " ORDER BY art.time DESC LIMIT " . _rsslimit);
            while ($item = DB::row($items)) {
                $feeditems[] = array($item['title'], _linkArticle($item['id'], $item['title_seo'], $item['cat_title_seo']), strip_tags($item['perex']), $item['time']);
            }
            break;

    }

    $continue = true;
}

/* ---  vystup  --- */

if ($continue) {
    header("Content-Type: application/xml; charset=UTF-8");
    $maintitle = _title . ' ' . _titleseparator . (($pagetitle != null) ? ' ' . $pagetitle . ' ' . _titleseparator : '') . ' ' . $feedtitle;
    echo '<?xml version="1.0" encoding="UTF-8"?' . '>
<rss version="0.91">
  <channel>
    <title>' . $maintitle . '</title>
    <link>' . _url . '/</link>
    <description>' . _description . '</description>
    <language>' . $_lang['main.languagespecification'] . '</language>
    <image>
      <title>' . $maintitle . '</title>
      <url>' . _url . '/plugins/templates/' . _template . '/images/system/rss-logo.gif</url>
      <link>' . _url . '/</link>
      <width>60</width>
      <height>60</height>
    </image>
';

    // polozky
    foreach ($feeditems as $feeditem) {
        echo '
    <item>
       <title>' . $feeditem[0] . '</title>
       <link>' . _url . '/' . $feeditem[1] . '</link>
       <pubDate>' . date('r', $feeditem[3]) . '</pubDate>
       <description><![CDATA[' . $feeditem[2] . ']]></description>
    </item>
  ';
    }

    echo '
  </channel>
</rss>';
}
