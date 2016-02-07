<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  definice funkce modulu  --- */
function _HCM_linkuser($jmeno = "")
{
    $name = DB::esc(_anchorStr($jmeno, false));
    $query = DB::query("SELECT id FROM `" . _mysql_prefix . "-users` WHERE username='" . $name . "'");
    if (DB::size($query) != 0) {
        $query = DB::row($query);

        return _linkUser($query['id']);
    }
}
