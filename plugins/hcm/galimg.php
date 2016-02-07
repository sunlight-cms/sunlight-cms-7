<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  definice funkce modulu  --- */
function _HCM_galimg($galerie = "", $typ = 1, $rozmery = null, $limit = null)
{
    // nacteni parametru
    $result = "";
    $galerie = _sqlWhereColumn("home", $galerie);
    if (isset($limit)) $limit = abs(intval($limit));
    else $limit = 1;

    // rozmery
    if (isset($rozmery)) {
        $rozmery = explode('/', $rozmery, 2);
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

    // urceni razeni
    switch ($typ) {
        case 2:
            $razeni = "RAND()";
            break;
        default:
            $razeni = "id DESC";
    }

    // vypis obrazku
    $rimgs = DB::query("SELECT id,title,prev,full FROM `" . _mysql_prefix . "-images` WHERE " . $galerie . " ORDER BY " . $razeni . " LIMIT " . $limit);
    while($rimg = DB::row($rimgs)) $result .= _galleryImage($rimg, "hcm" . SL::$hcmUid, $x, $y);

    return $result;
}
