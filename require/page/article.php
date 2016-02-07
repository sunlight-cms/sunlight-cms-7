<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* --  navigace -- */
if ($query['visible'] == 1) {

    // pripravit obal
    $content .= "<div class='article-navigation'><span>" . $_lang['article.category'] . ": </span>";

    // nacist data vsech domovskych kategorii
    $homes = array();
    for($i = 1; $i <= 3; ++$i) if ($query['home' . $i] != -1) $homes[] = $query['home' . $i];
    $q = DB::query('SELECT id,title,title_seo,var3 FROM `' . _mysql_prefix . '-root` WHERE id IN(' . implode(',', $homes) . ')');

    // zpracovat data kategorii
    $homes = array();
    $cat_showinfo = false;
    while ($r = DB::row($q)) {
        if ($r['id'] == $query['home1']) $homei = 1;
        elseif ($r['id'] == $query['home2']) $homei = 2;
        else $homei = 3;
        $homes[$homei] = $r;
        if ($r['var3'] == 1) $cat_showinfo = true;
    }

    // vypsat kategorie
    for ($i = 1; $i <= 3; ++$i) {
        if (!isset($homes[$i])) continue;
        $homes[$i] = "<a href='" . _linkRoot($homes[$i]['id'], $homes[$i]['title_seo']) . "'>" . $homes[$i]['title'] . "</a>";
    }
    $content .= implode(', ', $homes) . "</div>\n";
    unset($homes);
} else $cat_showinfo = null;

/* --  titulek  -- */
$title = $query['title'];
// if (_template_autoheadings) {
    $content .= "<h1>" . $title . "</h1>\n";
// }

/* --  perex  -- */
_extend('call', 'article.perex.before', $extend_args); // rozsireni pred perexem
$content .= "<p class='article-perex'>" . (isset($query['picture_uid']) ? "<img class='article-perex-image' src='" . _pictureStorageGet(_indexroot . 'pictures/articles/', null, $query['picture_uid'], 'jpg') . "' alt='" . $query['title'] . "' />" : '') . $query['perex'] . "</p>\n";
// if (isset($query['picture_uid'])) $content .= "<div class='cleaner'></div>\n";
_extend('call', 'article.perex.after', $extend_args); // rozsireni za perexem

/* --  obsah  -- */
$content .= "<div class='article-content'>\n" . _parseHCM($query['content']) . "\n</div>\n";

/* --  informacni tabulka  -- */

// zalomeni
$content .= "<div class='cleaner'></div>\n";

// priprava
$info = array("basicinfo" => null, "idlink" => null, "rateresults" => null, "rateform" => null, "infobox" => null);

// zakladni informace
if ($query['showinfo'] == 1 && (!isset($cat_showinfo) || $cat_showinfo === true)) {
    $info['basicinfo'] = "
        <strong>" . $_lang['article.author'] . ":</strong> " . _linkUser($query['author']) . "<br />
        <strong>" . $_lang['article.posted'] . ":</strong> " . _formatTime($query['time']) . "<br />
        <strong>" . $_lang['article.readed'] . ":</strong> " . $query['readed'] . "x
        ";
}

// ID clanku
if (_loginright_adminart) {
    $info['idlink'] = (($info['basicinfo'] != null) ? "<br />" : '') . "<strong>" . $_lang['global.id'] . ":</strong> <a href='admin/index.php?p=content-articles-edit&amp;id=" . $id . "&amp;returnid=load&amp;returnpage=1'>" . $id . " <img src='" . _templateImage("icons/edit.png") . "' alt='edit' class='icon' /></a>";
}

// vysledky hodnoceni
if ($query['rateon'] == 1 and _ratemode != 0) {

    if ($query['ratenum'] != 0) {
        /*procenta*/
        if (_ratemode == 1) {
            $rate = (round($query['ratesum'] / $query['ratenum'])) . "%";
        }
        /*znamka*/  else {
            $rate = round(-0.04 * ($query['ratesum'] / $query['ratenum']) + 5);
        }
        $rate .= " (" . $_lang['article.rate.num'] . " " . $query['ratenum'] . "x)";
    } else {
        $rate = $_lang['article.rate.nodata'];
    }

    $info['rateresults'] = (($info['basicinfo'] != null or $info['idlink'] != null) ? "<br />" : '') . "<strong>" . $_lang['article.rate'] . ":</strong> " . $rate;
}

// formular hodnoceni
$rateform_used = false;
if ($query['rateon'] == 1 and _ratemode != 0 and _loginright_artrate and _iplogCheck(3, $query['id'])) {
    $info['rateform'] = "
<strong>" . $_lang['article.rate.title'] . ":</strong>
<form action='" . _indexroot . "remote/artrate.php' method='post'>
<input type='hidden' name='id' value='" . $query['id'] . "' />
";

    if (_ratemode == 1) {
        // procenta
        $info['rateform'] .= "<select name='r'>\n";
        for ($x = 0; $x <= 100; $x += 10) {
            if ($x == 50) {
                $selected = " selected='selected'";
            } else {
                $selected = "";
            }
            $info['rateform'] .= "<option value='" . $x . "'" . $selected . ">" . $x . "%</option>\n";
        }
        $info['rateform'] .= "</select>&nbsp;\n<input type='submit' value='" . $_lang['article.rate.submit'] . "' />";
    } else {
        // znamky
        $info['rateform'] .= "<table class='ratetable'>\n";
        for ($i = 0; $i < 2; $i++) {
            $info['rateform'] .= "<tr class='r" . $i . "'>\n";
            if ($i == 0) {
                $info['rateform'] .= "<td rowspan='2'><img src='" . _templateImage("icons/rate-good.png") . "' alt='good' class='icon' /></td>\n";
            }
            for ($x = 1; $x < 6; $x++) {
                if ($i == 0) {
                    $info['rateform'] .= "<td><input type='radio' name='r' value='" . ((5 - $x) * 25) . "' /></td>\n";
                } else {
                    $info['rateform'] .= "<td>" . $x . "</td>\n";
                }
            }
            if ($i == 0) {
                $info['rateform'] .= "<td rowspan='2'><img src='" . _templateImage("icons/rate-bad.png") . "' alt='bad' class='icon' /></td>\n";
            }
            $info['rateform'] .= "</tr>\n";
        }
        $info['rateform'] .= "
<tr><td colspan='7'><input type='submit' value='" . $_lang['article.rate.submit'] . " &gt;' /></td></tr>
</table>
";
    }

    $info['rateform'] .= _xsrfProtect() . "</form>\n";
}

// infobox
if ($query['infobox'] != "") {
    $info['infobox'] = _parseHCM($query['infobox']);
}

// sestaveni kodu
if (count(_arrayRemoveValue($info, null)) != 0) {

    // zacatek tabulky
    $content .= "
<div class='anchor'><a name='ainfo'></a></div>
<table class='article-info'>
<tr class='valign-top'>
";

    // prvni bunka
    if ($info['basicinfo'] != null or $info['idlink'] != null or $info['rateresults'] != null or ($info['infobox'] != null and $info['rateform'] != null)) {
        $content .= "<td>" . $info['basicinfo'] . $info['idlink'] . $info['rateresults'];

        // vlozeni formulare pro hodnoceni, pokud je infobox obsazen
        if ($info['rateform'] != null and ($info['infobox'] != null or $info['basicinfo'] == null)) {
            $content .= (($info['basicinfo'] != null) ? "<br />" : '') . "<br />" . $info['rateform'];
            $rateform_used = true;
        }

        $content .= "\n</td>\n";

    }

    // druha bunka
    if ($info['infobox'] != null or ($rateform_used == false and $info['rateform'] != null)) {
        $content .= "<td>";
        if ($info['infobox'] != null) {
            $content .= $info['infobox'];
        }
        if ($rateform_used == false) {
            $content .= $info['rateform'];
        }
        $content .= "</td>";
    }

    // konec tabulky
    $content .= "\n</tr>\n</table>\n";

}

// odkaz na tisk
if (_printart) {
    $content .= "\n<p><a href='" . _indexroot . "printart.php?id=" . $id . "' target='_blank'><img src='" . _templateImage("icons/print.png") . "' alt='print' class='icon' /> " . $_lang['article.print'] . "</a></p>\n";
}

// rozsireni pred komentari
_extend('call', 'article.comments', $extend_args);

// komentare
if ($query['comments'] == 1 and _comments) {
    require_once (_indexroot . 'require/functions-posts.php');
    $content .= _postsOutput(2, $id, $query['commentslocked']);
}

// zapocteni precteni
if ($query['confirmed'] == 1 and $query['time'] <= time() and _iplogCheck(2, $id)) {
    DB::query("UPDATE `" . _mysql_prefix . "-articles` SET readed=" . ($query['readed'] + 1) . " WHERE id=" . $id);
    _iplogUpdate(2, $id);
}
