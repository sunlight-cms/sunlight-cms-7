<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  definice funkce modulu  --- */
function _HCM_gallery2($cesta = "", $strankovani = null, $lightbox = 1)
{
    // priprava
    $result = "";
    $cesta = _indexroot . $cesta;
    $cesta_noroot = $cesta;
    if (mb_substr($cesta, -1, 1) != "/") {
        $cesta .= "/";
    }
    if (isset($strankovani) and $strankovani > 0) {
        $strankovat = true;
        $strankovani = intval($strankovani);
        if ($strankovani <= 0) $strankovani = 1;
    } else $strankovat = false;
    $lightbox = _boolean($lightbox);

    if (@file_exists($cesta) and @is_dir($cesta) and @file_exists($cesta . "prev/") and @is_dir($cesta . "prev/") and @file_exists($cesta . "full/") and @is_dir($cesta . "full/")) {
        $handle = @opendir($cesta . "prev/");

        // nacteni polozek
        $items = array();
        while (false !== ($item = @readdir($handle))) {
            $ext = pathinfo($item);
            if (isset($ext['extension'])) {
                $ext = mb_strtolower($ext['extension']);
            } else {
                $ext = "";
            }
            if (@is_dir($item) or $item == "." or $item == ".." or !in_array($ext, SL::$imageExt) or !@file_exists($cesta . "full/" . $item)) {
                continue;
            }
            $items[] = $item;
        }
        @closedir($handle);
        natsort($items);

        // priprava strankovani
        if ($strankovat) {
            $count = count($items);
            $paging = _resultPaging(_indexOutput_url, $strankovani, $count, "", "#hcm_gal" . SL::$hcmUid, "hcm_gal" . SL::$hcmUid . "p");
        }

        // vypis
        $result = "<div class='anchor'><a name='hcm_gal" . SL::$hcmUid . "'></a></div>\n<div class='gallery'>\n";
        $counter = 0;
        foreach ($items as $item) {
            if ($strankovat and $counter > $paging[6]) {
                break;
            }
            if (!$strankovat or ($strankovat and _resultPagingIsItemInRange($paging, $counter))) {
                $result .= "<a href='" . $cesta . "full/" . _htmlStr($item) . "' target='_blank'" . ($lightbox ? " class='lightbox' data-fancybox-group='lb_hcm" . SL::$hcmUid . "'" : '') . "><img src='" . $cesta . "prev/" . _htmlStr($item) . "' alt='" . $item . "' /></a>\n";
            }
            $counter++;
        }
        $result .= "</div>\n";
        if ($strankovat) {
            $result .= $paging[0];
        }

    }

    return $result;
}
