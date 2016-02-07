<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  definice funkce modulu  --- */
function _HCM_ximg($cesta = '', $extrakod = null)
{
    // alternativni text
    $ralt = basename($cesta);
    if (($dotpos = mb_strrpos($ralt, ".")) !== false) $ralt = mb_substr($ralt, 0, $dotpos);

    // kod
    if (isset($extrakod)) $rpluscode = " " . $extrakod;
    else $rpluscode = "";
    return "<img src='" . _htmlStr($cesta) . "' alt='" . $ralt . "'" . $rpluscode . " />";
}
