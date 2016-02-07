<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  definice funkce modulu  --- */
function _HCM_iperex($odstavec = true)
{
    if (isset($GLOBALS['query']) && is_array($GLOBALS['query']) && isset($GLOBALS['query']['intersectionperex'])) {
        if ($odstavec) return '<p>' . $GLOBALS['query']['intersectionperex'] . '</p>';
        return $GLOBALS['query']['intersectionperex'];
    }

    return '';
}
