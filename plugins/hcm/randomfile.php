<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  definice funkce modulu  --- */
function _HCM_randomfile($cesta = "", $typ = 1, $pocet = 1, $vyska_nahledu = null)
{
    $result = "";
    $cesta = _indexroot . $cesta;
    $cesta_noroot = $cesta;
    if (mb_substr($cesta, -1, 1) != "/") {
        $cesta .= "/";
    }
    if (mb_substr($cesta_noroot, -1, 1) != "/") {
        $cesta_noroot .= "/";
    }
    $pocet = intval($pocet);

    if (@file_exists($cesta) and @is_dir($cesta)) {
        $handle = @opendir($cesta);

        switch ($typ) {
            case 2:
                $allowed_extensions = SL::$imageExt;
                if (isset($vyska_nahledu)) {
                    $vyska_nahledu = intval($vyska_nahledu);
                } else {
                    $vyska_nahledu = 96;
                }
                break;
            default:
                $allowed_extensions = array("txt", "htm", "html");
                break;
        }

        $items = array();
        while (false !== ($item = @readdir($handle))) {
            $ext = pathinfo($item);
            if (isset($ext['extension'])) {
                $ext = mb_strtolower($ext['extension']);
            } else {
                $ext = "";
            }
            if (@is_dir($cesta . $item) or $item == "." or $item == ".." or !in_array($ext, $allowed_extensions)) {
                continue;
            }
            $items[] = $item;
        }

        if (count($items) != 0) {
            if ($pocet > count($items)) {
                $pocet = count($items);
            }
            $randitems = array_rand($items, $pocet);
            if (!is_array($randitems)) {
                $randitems = array($randitems);
            }

            foreach ($randitems as $item) {
                $item = $items[$item];
                switch ($typ) {
                    case 2:
                        $thumb = _pictureThumb($cesta_noroot . $item, array('x' => null, 'y' => $vyska_nahledu));
                        $result .= "<a href='" . $cesta . _htmlStr($item) . "' target='_blank' class='lightbox' data-fancybox-group='lb_hcm" . SL::$hcmUid . "'><img src='" . $thumb . "' alt='" . $item . "' /></a>\n";
                        break;
                    default:
                        $result .= @file_get_contents(_indexroot . $cesta . $item);
                        break;
                }
            }
        }

        @closedir($handle);
    }

    return $result;
}
