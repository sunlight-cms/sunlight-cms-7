<?php
// kontrola jadra
if (!defined('_core')) {
    exit;
}

// nastaveni strankovani
$artsperpage = $query['var2'];
switch ($query['var1']) {
    case 1:
        $artorder = "time DESC";
        break;
    case 2:
        $artorder = "id DESC";
        break;
    case 3:
        $artorder = "title";
        break;
    case 4:
        $artorder = "title DESC";
        break;
}

// titulek
$title = $query['title'];
if (_template_autoheadings && $query['autotitle']) {
    $content .= "<h1>" . $query['title'] . _linkRSS($id, 4) . "</h1>\n";
}
_extend('call', 'page.category.aftertitle', $extend_args);

// obsah
_extend('call', 'page.category.content.before', $extend_args);
if ($query['content'] != "") $content .= _parseHCM($query['content']) . "\n\n<div class='hr'><hr /></div>\n\n";
_extend('call', 'page.category.content.after', $extend_args);

// vypis clanku
$arts_cond = "(art.home1=" . $id . " OR art.home2=" . $id . " OR art.home3=" . $id . ") AND " . _sqlArticleFilter() . " ORDER BY " . $artorder;
$paging = _resultPaging(_indexOutput_url, $artsperpage, "articles:art", $arts_cond);
$arts = DB::query("SELECT art.id,art.title,art.title_seo,art.author,art.perex," . ($query['var4'] ? 'art.picture_uid,' : '') . "art.time,art.comments,art.readed,cat.title_seo AS cat_title_seo,(SELECT COUNT(id) FROM `" . _mysql_prefix . "-posts` AS post WHERE home=art.id AND post.type=2) AS comment_count FROM `" . _mysql_prefix . "-articles` AS art JOIN `" . _mysql_prefix . "-root` AS cat ON(cat.id=art.home1) WHERE " . $arts_cond . " " . $paging[1]);

if (DB::size($arts) != 0) {
    if (_pagingmode == 1 or _pagingmode == 2) {
        $content .= $paging[0];
    }
    while ($art = DB::row($arts)) {
        $extend_item_args = _extendArgs($content, array('query' => $query, 'item-query' => &$art));
        _extend('call', 'page.category.item.before', $extend_item_args);
        $content .= _articlePreview($art, $query['var3'] == 1, true, $art['comment_count']);
        _extend('call', 'page.category.item.after', $extend_item_args);
    }
    if (_pagingmode == 2 or _pagingmode == 3) {
        $content .= '<br />' . $paging[0];
    }
} else {
    $content .= '<p>' . $_lang['misc.category.noarts'] . '</p>';
}
