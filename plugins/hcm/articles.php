<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  definice funkce modulu  --- */
function _HCM_articles($typ = 1, $pocet = null, $perex = true, $info = true, $kategorie = null)
{
    // priprava
    $result = "";
    $typ = intval($typ);
    if ($typ < 1 or $typ > 9) {
        $typ = 1;
    }
    $pocet = intval($pocet);
    if ($pocet < 1) {
        $pocet = 1;
    }
    $perex = intval($perex);
    $info = _boolean($info);

    // limitovani na kategorie
    $rcats = _sqlArticleWhereCategories($kategorie);

    // priprava casti sql dotazu
    switch ($typ) {
        case 1:
            $rorder = "art.time DESC";
            $rcond = "";
            break;
        case 2:
            $rorder = "art.readed DESC";
            $rcond = "art.readed!=0";
            break;
        case 3:
            $rorder = "art.ratesum/art.ratenum DESC";
            $rcond = "art.ratenum!=0";
            break;
        case 4:
            $rorder = "art.ratenum DESC";
            $rcond = "art.ratenum!=0";
            break;
        case 5:
            $rorder = "RAND()";
            $rcond = "";
            break;
        case 6:
            $rorder = "(SELECT time FROM `" . _mysql_prefix . "-iplog` WHERE type=2 AND var=art.id AND art.visible=1 AND art.time<=" . time() . " AND art.confirmed=1 ORDER BY id DESC LIMIT 1) DESC";
            $rcond = "art.readed!=0";
            break;
        case 7:
            $rorder = "(SELECT time FROM `" . _mysql_prefix . "-iplog` WHERE type=3 AND var=art.id AND art.visible=1 AND art.time<=" . time() . " AND art.confirmed=1 ORDER BY id DESC LIMIT 1) DESC";
            $rcond = "art.ratenum!=0";
            break;
        case 8:
            $rorder = "(SELECT time FROM `" . _mysql_prefix . "-posts` WHERE home=art.id AND type=2 ORDER BY time DESC LIMIT 1) DESC";
            $rcond = "(SELECT COUNT(id) FROM `" . _mysql_prefix . "-posts` WHERE home=art.id AND type=2)!=0";
            break;
        case 9:
            $rorder = "(SELECT COUNT(id) FROM `" . _mysql_prefix . "-posts` WHERE home=art.id AND type=2) DESC";
            $rcond = "(SELECT COUNT(id) FROM `" . _mysql_prefix . "-posts` WHERE home=art.id AND type=2)!=0";
            break;
    }

    // pripojeni casti
    if ($rcond != "") $rcond = " AND " . $rcond;
    $rcond = " WHERE " . _sqlArticleFilter(true) . $rcond;
    if ($rcats != "") $rcond .= " AND " . $rcats;

    // vypis
    $query = DB::query("SELECT art.id,art.title,art.title_seo,art.perex," . (($perex === 2) ? 'art.picture_uid,' : '') . "art.author,art.time,art.readed,art.comments,cat.title_seo AS cat_title_seo" . (($info !== 0) ? ",(SELECT COUNT(id) FROM `" . _mysql_prefix . "-posts` AS post WHERE home=art.id AND post.type=2) AS comment_count" : '') . " FROM `" . _mysql_prefix . "-articles` AS art JOIN `" . _mysql_prefix . "-root` AS cat ON(cat.id=art.home1)" . $rcond . " ORDER BY " . $rorder . " LIMIT " . $pocet);
    while ($item = DB::row($query)) {
        $result .= _articlePreview($item, $info, $perex !== 0, (($info !== 0) ? $item['comment_count'] : null));
    }

    return $result;
}
