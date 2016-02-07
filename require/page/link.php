<?php
// kontrola jadra
if (!defined('_core')) {
    exit;
}

// vystup
$title = $query['title'];

// odkazani podle ID
if ($query['content'] !== '') {

    if (mb_substr($query['content'], 0, 1) == "*") {
        // stranka
        $lid = intval(mb_substr($query['content'], 1));
        $query['content'] = "";
        $rootdata = DB::query_row("SELECT id,title_seo FROM `" . _mysql_prefix . "-root` WHERE id=" . $lid);
        if ($rootdata !== false) $query['content'] = _linkRoot($rootdata['id'], $rootdata['title_seo']);
    } else {
        // clanek
        if (mb_substr($query['content'], 0, 1) == "%") {
            $lid = intval(mb_substr($query['content'], 1));
            $query['content'] = "";
            $artdata = DB::query_row("SELECT art.id,art.title_seo,cat.title_seo AS cat_title_seo FROM `" . _mysql_prefix . "-articles` AS art JOIN `" . _mysql_prefix . "-root` AS cat ON(cat.id=art.home1) WHERE art.id=" . $lid);
            if ($artdata !== false) $query['content'] = _linkArticle($artdata['id'], $artdata['title_seo']);
        }
    }

}

// aktivace presmerovani
if ($query['content'] != "") {
    define('_redirect_to', $query['content']);
}
