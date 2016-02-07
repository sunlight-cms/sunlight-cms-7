<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  definice funkce modulu  --- */
function _HCM_anchor($nazev = '')
{
    if (_modrewrite) {
        return _path . '#' . $nazev;
    } else {
        return '#' . $nazev;
    }
}
