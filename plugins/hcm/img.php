<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  definice funkce modulu  --- */
function _HCM_img($cesta = "", $vyska_nahledu = null, $titulek = null, $lightbox = null)
{
    if (isset($vyska_nahledu) and $vyska_nahledu > 0) {
        $vyska_nahledu = intval($vyska_nahledu);
    } else {
        $vyska_nahledu = 96;
    }
    if (isset($titulek) and $titulek != "") {
        $titulek = _htmlStr($titulek);
    }
    if (!isset($lightbox)) {
        $lightbox = SL::$hcmUid;
    }

    $thumb = _pictureThumb($cesta, array('x' => null, 'y' => $vyska_nahledu));

    return "<a href='" . _htmlStr($cesta) . "' target='_blank' class='lightbox' data-fancybox-group='lb_hcm" . $lightbox . "'" . (($titulek != "") ? ' title=\'' . $titulek . '\'' : '') . "><img src='" . $thumb . "' alt='" . (($titulek != "") ? $titulek : 'img') . "' /></a>\n";
}
