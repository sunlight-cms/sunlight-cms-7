<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  definice funkce modulu  --- */
function _HCM_users($razeni = 1, $pocet = 5, $limit_delky_jmena = null)
{
    $result = "";
    $razeni = $razeni;
    $pocet = abs(intval($pocet));
    if (isset($limit_delky_jmena)) {
        $limit_delky_jmena = intval($limit_delky_jmena);
    } else {
        $limit_delky_jmena = false;
    }

    $rcond = "";
    switch ($razeni) {
        case 2:
            $rorder = "activitytime DESC";
            $rcond = " WHERE " . time() . "-activitytime<1800";
            break;
        case 3:
            $rorder = "(SELECT COUNT(id) FROM `" . _mysql_prefix . "-posts` WHERE author=`sunlight-users`.id) DESC";
            break;
        case 4:
            $rcond = " WHERE (SELECT COUNT(id) FROM `" . _mysql_prefix . "-articles` WHERE author=`" . _mysql_prefix . "-users`.id AND rateon=1 AND ratenum!=0)!=0";
            $rorder = "(SELECT ROUND(SUM(ratesum)/SUM(ratenum)) FROM `" . _mysql_prefix . "-articles` WHERE rateon=1 AND ratenum!=0 AND author=`" . _mysql_prefix . "-users`.id) DESC";
            break;
        default:
            $rorder = "id DESC";
            break;
    }

    if ($razeni != 4) {
        $result = "<ul>\n";
    } else {
        $result = "<ol>\n";
    }
    $query = DB::query("SELECT id FROM `" . _mysql_prefix . "-users`" . $rcond . " ORDER BY " . $rorder . " LIMIT " . $pocet);
    while ($item = DB::row($query)) {

        // pridani doplnujicich informaci
        switch ($razeni) {

                // pocet prispevku
            case 3:
                $rvar = DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-posts` WHERE author=" . $item['id']), 0);
                if ($rvar == 0) {
                    continue;
                } else {
                    $rext = " (" . $rvar . ")";
                }
                break;

                // hodnoceni autora
            case 4:
                $rvar = DB::query_row("SELECT ROUND(SUM(ratesum)/SUM(ratenum)),COUNT(id) FROM `" . _mysql_prefix . "-articles` WHERE rateon=1 AND ratenum!=0 AND author=" . $item['id']);
                $rext = " - " . $rvar[0] . "%, " . $GLOBALS['_lang']['global.articlesnum'] . ": " . $rvar[1];
                break;

                // nic
            default:
                $rext = "";
                break;

        }

        $result .= "<li>" . _linkUser($item['id'], null, false, false, $limit_delky_jmena) . $rext . "</li>\n";
    }
    if ($razeni != 4) {
        $result .= "</ul>\n";
    } else {
        $result .= "</ol>\n";
    }

    return $result;
}
