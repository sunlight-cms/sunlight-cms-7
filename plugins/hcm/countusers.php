<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  definice funkce modulu  --- */
function _HCM_countusers($kategorie = null)
{
    if (isset($kategorie)) {
        $cond = " WHERE " . _sqlWhereColumn("`group`", $kategorie);
    } else {
        $cond = "";
    }

    return DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-users`" . $cond), 0);
}
