<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  definice funkce modulu  --- */
function _HCM_phpsource($kod = "")
{
    return "<div class='pre php-source'>" . highlight_string($kod, true) . "</div>";
}
