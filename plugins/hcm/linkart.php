<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  definice funkce modulu  --- */
function _HCM_linkart($id = null, $text = null, $nove_okno = false)
{
    if (null === $text) {
        $query = DB::query_row('SELECT art.title,art.title_seo,cat.title_seo AS cat_title_seo FROM `' . _mysql_prefix . '-articles` AS art JOIN `' . _mysql_prefix . '-root` AS cat ON(cat.id=art.home1) WHERE art.' . (is_numeric($id) ? 'id' : 'title_seo') . '=' . DB::val($id));
        if (false === $query) return '{' . _htmlStr($id) . '}';
        $text = $query['title'];
    } else {
        $query = array('title_seo' => null, 'cat_title_seo' => null);
    }

    return "<a href='" . _linkArticle($id, $query['title_seo'],$query['cat_title_seo']) . "'" . ($nove_okno ? ' target="_blank"' : '') . ">" . $text . "</a>";
}
