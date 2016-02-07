<?php
/* ---  inicializace jadra  --- */
require '../require/load.php';
SL::init('../');

if (_ratemode == 0) {
    exit;
}

/* ---  hodnoceni  --- */

// nacteni promennych
_checkKeys('_POST', array('id'));
$id = intval($_POST['id']);

$article_exists = false;

// kontrola promennych a pristupu
$continue = false;
$query = DB::query("SELECT art.id,art.title_seo,art.time,art.confirmed,art.public,art.home1,art.home2,art.home3,art.rateon,cat.title_seo AS cat_title_seo FROM `" . _mysql_prefix . "-articles` AS art  JOIN `" . _mysql_prefix . "-root` AS cat ON(cat.id=art.home1) WHERE art.id=" . $id);
if (DB::size($query) != 0) {
    $article_exists = true;
    $query = DB::row($query);
    if (isset($_POST['r'])) {
        $r = round($_POST['r'] / 10) * 10;
        if (_iplogCheck(3, $id) and _xsrfCheck() and $query['rateon'] == 1 and _articleAccess($query) == 1 and $r <= 100 and $r >= 0) {
            $continue = true;
        }
    }
}

// zapocteni hodnoceni
if ($continue) {
    DB::query("UPDATE `" . _mysql_prefix . "-articles` SET ratenum=ratenum+1,ratesum=ratesum+" . $r . " WHERE id=" . $id);
    _iplogUpdate(3, $id);
}

// presmerovani
if ($article_exists) {
    $aurl = _linkArticle($id, $query['title_seo']) . "#ainfo";
} else {
    $aurl = "";
}
header("location: " . _url . '/' . $aurl);
