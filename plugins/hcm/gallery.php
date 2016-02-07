<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  definice funkce modulu  --- */
function _HCM_gallery($cesta = "", $rozmery = null, $strankovani = null, $lightbox = 1)
{
    // priprava
    $result = "";
    $cesta = _indexroot . $cesta;
    $cesta_noroot = $cesta;
    if (mb_substr($cesta, -1, 1) != "/") {
        $cesta .= "/";
    }
    if (mb_substr($cesta_noroot, -1, 1) != "/") {
        $cesta_noroot .= "/";
    }
    if (isset($strankovani) and $strankovani > 0) {
        $strankovat = true;
        $strankovani = intval($strankovani);
        if ($strankovani <= 0) $strankovani = 1;
    } else $strankovat = false;
    $lightbox = _boolean($lightbox);

    if (isset($rozmery)) {
        $rozmery = explode('/', $rozmery);
        if (sizeof($rozmery) === 2) {
            // sirka i vyska
            $x = intval($rozmery[0]);
            $y = intval($rozmery[1]);
        } else {
            // pouze vyska
            $x = null;
            $y = intval($rozmery[0]);
        }
    } else {
        // neuvedeno
        $x = null;
        $y = 128;
    }

    if (@file_exists($cesta) and @is_dir($cesta)) {
        $handle = @opendir($cesta);

        // nacteni polozek
        $items = array();
        while (false !== ($item = @readdir($handle))) {
            $ext = pathinfo($item);
            if (isset($ext['extension'])) $ext = mb_strtolower($ext['extension']);
            else $ext = "";
            if (@is_dir($item) or $item == "." or $item == ".." or !in_array($ext, SL::$imageExt)) {
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
                $thumb = _pictureThumb($cesta_noroot . $item, array(
                    'x' => $x,
                    'y' => $y,
                ));
                $result .= "<a href='" . $cesta . _htmlStr($item) . "' target='_blank'" . ($lightbox ? " class='lightbox' data-fancybox-group='lb_hcm" . SL::$hcmUid . "'" : '') . "><img src='" . $thumb . "' alt='" . $item . "' /></a>\n";
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
