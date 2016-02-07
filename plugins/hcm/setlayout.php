<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  definice funkce modulu  --- */
function _HCM_setlayout($name = null, $abs = false)
{
    _templateFileOverload($name, $abs == true);
}
