<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  definice funkce modulu  --- */
function _HCM_linkroot($id = null, $text = null, $nove_okno = false)
{
    $is_id = is_numeric($id);
    if ($is_id) $id = intval($id);
    else $id = DB::val($id);
    $query = DB::query("SELECT title,title_seo FROM `" . _mysql_prefix . "-root` WHERE " . ($is_id ? 'id' : 'title_seo') . "=" . $id);
    if (isset($nove_okno) and _boolean($nove_okno)) {
        $target = " target='_blank'";
    } else {
        $target = "";
    }
    if (DB::size($query) != 0) {
        $query = DB::row($query);
        if (isset($text) and $text != "") {
            $query['title'] = $text;
        }

        return "<a href='" . _linkRoot($id, $query['title_seo']) . "'" . $target . ">" . $query['title'] . "</a>";
    }
}
