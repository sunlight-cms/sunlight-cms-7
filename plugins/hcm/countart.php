<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  definice funkce modulu  --- */
function _HCM_countart($kategorie = null)
{
    if (isset($kategorie) and $kategorie != "") {
        $cond = " AND " . _sqlArticleWhereCategories($kategorie);
    } else {
        $cond = "";
    }

    return DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-articles` AS art WHERE " . _sqlArticleFilter() . $cond), 0);
}
