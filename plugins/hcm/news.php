<?php
/* --- [News 1.1 by ShiraNai7  ] --- */

/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  definice funkce modulu  --- */
function _HCM_news($cesta = "", $limit = null)
{
    // priprava
    $output = "";
    $filename = $cesta;
    $filename_info = pathinfo($filename);
    if (isset($limit)) {
        $limit = intval($limit);
    } else {
        $limit = -1;
    }

    if (isset($filename_info['extension']) and mb_strtolower($filename_info['extension']) == "txt") {

        if (@file_exists($filename)) {

            $data = trim(@file_get_contents(_indexroot . $filename));
            $data = @explode("\n", $data);

            $output = "<ul>";

            $counter = 0;
            foreach ($data as $item) {
                $item = @explode(";", $item);
                $output .= "<li><strong>" . trim($item[0]) . "</strong> - " . trim($item[1]) . "</li>\n";
                $counter++;
                if ($counter == $limit) {
                    break;
                }
            }

            if ($counter == 0) {
                $output .= "<li>" . $GLOBALS['_lang']['global.nokit'] . "</li>\n";
            }

            $output .= "</ul>\n";

        } else {
            $output = "Soubor nenalezen.";
        }

    } else {
        $output = "Nepovolena pripona.";
    }

    return $output;
}
