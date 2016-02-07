<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  definice funkce modulu  --- */
function _HCM_path($absolutni = false)
{
    if ($absolutni) {
        return
            'http'
            . ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || 443 == $_SERVER['SERVER_PORT']) ? 's' : '')
            . '://'
            . _getDomain()
            . ((80 != $_SERVER['SERVER_PORT']) ? ":{$_SERVER['SERVER_PORT']}" : '')
            . _path
        ;
    } else {
        return _path;
    }
}
