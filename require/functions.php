<?php
/* ----  kontrola jadra  ---- */
if (!defined('_core')) {
    exit;
}

/* ----  funkce systemu  ---- */

/**
 * Vytvoreni nahledu clanku pro vypis
 * @param array $art pole s daty clanku vcetne cat_title_seo
 * @param bool $info vypisovat radek s informacemi 1/0
 * @param bool $perex vypisovat perex 1/0
 * @param int|null pocet komentaru (null = nezobrazi se)
 * @return string
 */
function _articlePreview($art, $info = true, $perex = true, $comment_count = null)
{
    // podpora nahrazeni
    static $overload;
    if (null === $overload) {
        _extend('call', 'article.preview', array('callback' => &$overload));
        if (null === $overload) $overload = false;
    }
    if (false !== $overload) return call_user_func($overload, $art, $info, $perex, $comment_count);

    global $_lang;

    // titulek
    $link = _linkArticle($art['id'], $art['title_seo'], $art['cat_title_seo']);
    $output = "<h2 class='list-title'><a href='" . $link . "'>" . $art['title'] . "</a></h2>";

    // perex a obrazek
    if ($perex == true) {
        $output .= "<p class='list-perex'>" . (isset($art['picture_uid']) ? "<a href='" . $link . "'><img class='list-perex-image' src='" . _pictureStorageGet(_indexroot . 'pictures/articles/', null, $art['picture_uid'], 'jpg') . "' alt='" . $art['title'] . "' /></a>" : '') . $art['perex'] . "</p>";
    }

    // info
    if ($info == true) {

        // pocet komentaru
        if ($art['comments'] == 1 and _comments and $comment_count !== null) $info_comments = _template_listinfoseparator . "<span>" . $_lang['article.comments'] . ":</span> " . $comment_count;
        else $info_comments = "";

        $output .= "
  <div class='list-info'>
  <span>" . $_lang['article.author'] . ":</span> " . _linkUser($art['author'], null, true) . _template_listinfoseparator . "<span>" . $_lang['article.posted'] . ":</span> " . _formatTime($art['time']) . _template_listinfoseparator . "<span>" . $_lang['article.readed'] . ":</span> " . $art['readed'] . "x" . $info_comments . "</div>";

    } elseif ($perex && isset($art['picture_uid'])) $output .= "<div class='cleaner'></div>\n";

    return $output . "\n";
}

/**
 * Sestavit kod obrazku v galerii
 * @param array $img pole s daty obrazku
 * @param string|null $lightboxid sid lightboxu nebo null (= nepouzivat)
 * @param int|null $width pozadovana sirka nahledu
 * @param int|null $height pozadovana vyska nahledu
 * @return string
 */
function _galleryImage($img, $lightboxid, $width, $height)
{
    $path = (!_isAbsolutePath($img['full']) ? _indexroot : '') . $img['full'];
    $content = "<a href='" . $path . "' target='_blank'" . (isset($lightboxid) ? " class='lightbox' data-fancybox-group='lb_" . $lightboxid . "'" : '') . (($img['title'] != "") ? " title='" . $img['title'] . "'" : '') . ">";
    if ($img['prev'] != "") $content .= "<img src='" . (!_isAbsolutePath($img['prev']) ? _indexroot : '') . $img['prev'] . "' alt='" . (($img['title'] != "") ? $img['title'] : _htmlStr(basename($img['full']))) . "' />";
    else $content .= "<img src='" . _pictureThumb($path, array('x' => $width, 'y' => $height)) . "' alt='" . (($img['title'] != "") ? $img['title'] : _htmlStr(basename($img['full']))) . "' />";
    $content .= "</a>\n";

    return $content;
}

/**
 * Inicializace captchy
 * @return array radek pro funkci {@link _formOutput}
 */
function _captchaInit()
{
    $output = _extend('fetch', 'sys.captcha.init');
    if (null !== $output) {
        return $output;
    }
    if (_captcha and !_loginindicator) {
        global $_lang;
        ++SL::$captchaCounter;
        if (!isset($_SESSION[_sessionprefix . 'captcha_code']) or !is_array($_SESSION[_sessionprefix . 'captcha_code'])) {
            $_SESSION[_sessionprefix . 'captcha_code'] = array();
        }
        $_SESSION[_sessionprefix . 'captcha_code'][SL::$captchaCounter] = array(_captchaCode(8), false);

        return array($_lang['captcha.input'], "<input type='text' name='_cp' class='inputc' /><img src='" . _indexroot . "remote/cimage.php?n=" . SL::$captchaCounter . "' alt='" . $_lang['captcha.help'] . "' title='" . $_lang['captcha.help'] . "' class='cimage' /><input type='hidden' name='_cn' value='" . SL::$captchaCounter . "' />", true);
    } else {
        return array("", "");
    }
}

/**
 * Zkontrolovat vyplneni captcha obrazku
 * @return bool
 */
function _captchaCheck()
{
    // extend
    $output = _extend('fetch', 'sys.captcha.check');
    if (null !== $output) {
        return $output;
    }

    // pole pro nahradu matoucich znaku
    $disambiguation = array(
        '0' => 'O',
        'Q' => 'O',
        '1' => 'I',
        '6' => 'G',
    );

    // kontrola
    if (_captcha and !_loginindicator) {
        if (isset($_POST['_cp']) and isset($_POST['_cn']) and isset($_SESSION[_sessionprefix . 'captcha_code'][$_POST['_cn']])) {
            if (strtr($_SESSION[_sessionprefix . 'captcha_code'][$_POST['_cn']][0], $disambiguation) === strtr(mb_strtoupper($_POST['_cp']), $disambiguation)) {
                $return = true;
            } else $return = false;
            unset($_SESSION[_sessionprefix . 'captcha_code'][$_POST['_cn']]);

            return $return;
        } else return false;
    } else return true;
}

/**
 * Vygenerovani nahodneho retezce pro pouziti v CAPTCHA
 *
 * @param int $length
 * @return string
 */
function _captchaCode($length)
{
    $word = strtoupper(_wordGenMarkov($length));

    $maxNumbers = max(ceil($length / 3), 1);

    for ($i = 0; $i < $maxNumbers; ++$i) {
        $word[mt_rand(0, $length - 1)] = (string) mt_rand(2, 9);
    }

    return strtr($word, array(
        'W' => 'X',
        'Q' => 'O',
    ));
}

/**
 * Kontrola kompatibility komponent
 * @param string $type typ komponenty (database, database_backup, language_file, template)
 * @param string $version verze k porovnani
 * @param bool $get vratit seznam verzi namisto vysledku srovnani 1/0
 * @return bool|array
 */
function _checkVersion($type, $version, $get = false)
{
    // pole verzi podle typu
    switch ($type) {
        case 'database':
            $cmp_versions = array('7.5.3', '7.5.4');
            break;
        case 'language_file':
            $cmp_versions = array('7.5.3', '7.5.4');
            break;
        case 'template':
            $cmp_versions = array('2.1');
            break;
        default:
            $cmp_versions = array();
            break;
    }

    // vystup
    if (!$get) {
        return in_array($version, $cmp_versions);
    } else {
        return $cmp_versions;
    }
}

/**
 * Odstraneni uzivatele
 * @param int $id id uzivatele
 * @return bool
 */
function _deleteUser($id)
{
    // nacist jmeno
    if ($id == 0) return;
    $udata = DB::query_row("SELECT username,avatar FROM `" . _mysql_prefix . "-users` WHERE id=" . $id);
    if ($udata === false) return false;

    // udalost
    $allow = true;
    _extend('call', 'user.delete', array('id' => $id, 'username' => $udata['username'], 'allow' => &$allow));
    if (!$allow) return false;

    // vyresit vazby
    DB::query("DELETE FROM `" . _mysql_prefix . "-users` WHERE id=" . $id);
    DB::query("DELETE `" . _mysql_prefix . "-pm`,post FROM `" . _mysql_prefix . "-pm` LEFT JOIN `" . _mysql_prefix . "-posts` AS post ON (post.type=6 AND post.home=`" . _mysql_prefix . "-pm`.id) WHERE receiver=" . $id . " OR sender=" . $id);
    DB::query("UPDATE `" . _mysql_prefix . "-posts` SET guest='" . $udata['username'] . "',author=-1 WHERE author=" . $id);
    DB::query("UPDATE `" . _mysql_prefix . "-articles` SET author=0 WHERE author=" . $id);
    DB::query("UPDATE `" . _mysql_prefix . "-polls` SET author=0 WHERE author=" . $id);

    // odstraneni uploadovaneho avataru
    if (isset($udata['avatar'])) @unlink(_indexroot . 'pictures/avatars/' . $udata['avatar'] . '.jpg');
    return true;
}

/**
 * Vyhodnoceni PHP kodu uvnitr funkce
 * @param string $code PHP kod
 * @return mixed navrati vystup funkce eval nebo promennou $output
 */
function _evalBox($code)
{
    $output = "";
    $eval = eval($code);
    if (isset($eval)) return $eval;
    return $output;
}

/**
 * Sestavit formatovany seznam udalosti (chyb)
 * @param array $events texty udalosti
 * @param string|null $into uvodni text, 'errors' (misc.errorlog.intro z jazyk. souboru) nebo null (= zadne)
 * @return string
 */
function _eventList($events, $intro = null)
{
    // text
    if ($intro != null) {
        if ($intro != "errors") $output = $intro;
        else {
            global $_lang;
            $output = $_lang['misc.errorlog.intro'];
        }
        $output .= "\n";
    } else {
        $output = "";
    }

    // polozky
    $output .= "<ul>\n";
    foreach($events as $item) $output .= "<li>" . $item . "</li>\n";
    $output .= "</ul>";

    return $output;
}

/**
 * Zformatovat timestamp na zaklade nastaveni systemu
 * @param number $timestamp UNIX timestamp
 * @return string
 */
function _formatTime($timestamp)
{
    return date(_time_format, $timestamp);
}

/**
 * Systemovy box se zpravou
 * @param int $type typ zpravy (1 - ok, 2 - upozorneni, 3 - chyba)
 * @param string $string text zpravy
 * @return string
 */
function _formMessage($type, $string)
{
    return "\n<div class='message" . $type . "'>$string</div>\n";
}

/**
 * Sestaveni formulare
 *
 * Format $cells:
 *
 *  array(
 *      array(
 *          0 => popisek
 *          1 => obsah radku
 *          2 => [vertikalni zarovnani 1/0]
 *          3 => [obsah po tabulce]
 *          4 => class atribut pro <tr>
 *      ),
 *      ...
 *  )
 *
 * - radek je preskocen, pokud je popisek radku prazdny
 * - popisek radku bude zobrazen i pres bunku pro obsah radku, pokud je obsah radku prazdny
 *
 * Dalsi klice v $cells:
 *
 *  attrs     dalsi atributy pro <form> tag (HTML bez mezer)
 *  method    metoda, vychozi je post
 *
 * @param string $name nazev formulare
 * @param string $action cil formulare
 * @param array $cells radky formulare ve formatu viz vyse
 * @param array|null $check pole s nazvy poli pro kontrolu javascriptem nebo null
 * @param string|null $submittext text tlacitka pro odeslani formulare nebo null (= vychozi)
 * @param string|null $codenexttosubmit kod vedle odesilaciho tlacitka nebo null
 * @return string
 */
function _formOutput($name, $action, $cells, $check = null, $submittext = null, $codenexttosubmit = null)
{
    $extend_buffer = _extend('buffer', 'sys.form.output', array(
        'name' => &$name,
        'action' => &$action,
        'cells' => &$cells,
        'check' => &$check,
        'submittext' => &$submittext,
        'codenexttosubmit' => &$codenexttosubmit,
    ));

    if ('' !== $extend_buffer) {
        return $extend_buffer;
    }

    global $_lang;

    /* ---  kontrola poli javascriptem, text odesilaciho tlacidla  --- */
    if ($check != null) {
        $checkcode = _jsCheckForm($name, $check);
    } else {
        $checkcode = "";
    }

    // submit text
    if ($submittext != null) {
        $submit = $submittext;
    } else {
        $submit = $_lang['global.send'];
    }

    // metoda
    if (isset($cells['method'])) {
        $method = $cells['method'];
        unset($cells['method']);
    } else {
        $method = 'post';
    }

    // atributy
    if (isset($cells['attrs'])) {
        $attrs = ' ' . $cells['attrs'];
        unset($cells['attrs']);
    } else {
        $attrs = '';
    }

    /* ---  vystup  --- */
    $hidden_content = '';
    $output = "
<form action='" . $action . "' method='" . $method . "' name='" . $name . "'" . $attrs . $checkcode . ">
<table>";

    // bunky
    foreach ($cells as $cell) {
        if ($cell[0] != "") {
            $class = '';
            if (isset($cell[2]) && $cell[2]) {
                $class .= 'valign-top';
            }
            if (isset($cell[4])) {
                if ('' !== $class) {
                    $class .= ' ';
                }
                $class .= $cell[4];
            }

            $output .= "
    <tr" . (('' === $class) ? '' : ' class="' . $class . '"') . ">
    <td class='rpad'" . (($cell[1] == "") ? " colspan='2'" : '') . ">" . (($cell[1] != "") ? "<strong>" : '') . $cell[0] . (($cell[1] != "") ? "</strong>" : '') . "</td>
    " . (($cell[1] != "") ? "<td>" . $cell[1] . "</td>" : '') . "
    </tr>";
            $lastcell = $cell;
        }
        if (isset($cell[3])) {
            $hidden_content .= $cell[3];
        }
    }

    // odesilaci tlacidlo, konec tabulky
    $output .= "
  <tr>
  " . ((isset($lastcell[1]) and $lastcell[1] != "") ? "<td></td><td>" : "<td colspan='2'>") . "
  <input type='submit' value='" . $submit . "' />" . $codenexttosubmit . "</td>
  </tr>

</table>
" . $hidden_content . "
" . _xsrfProtect() . "</form>";

    return $output;
}

/**
 * Vratit pole se jmeny vsech existujicich opravneni
 * @return array
 */
function _getRightsArray()
{
    return array("level", "administration", "adminsettings", "adminusers", "admingroups", "admincontent", "adminsection", "admincategory", "adminbook", "adminseparator", "admingallery", "adminlink", "adminintersection", "adminforum", "adminpluginpage", "adminart", "adminallart", "adminchangeartauthor", "adminpoll", "adminpollall", "adminsbox", "adminbox", "adminconfirm", "adminneedconfirm", "adminfman", "adminfmanlimit", "adminfmanplus", "adminhcmphp", "adminbackup", "adminrestore", "adminmassemail", "adminbans", "adminposts", "changeusername", "unlimitedpostaccess", "locktopics", "stickytopics", "movetopics", "postcomments", "artrate", "pollvote", "selfdestruction");
}

/**
 * Zjistit, zda uzivatel NEMA dane pravo
 * @param string $name nazev prava
 * @return bool
 */
function _userHasNotRight($name)
{
    if (mb_substr($name, 0, 1) != "-") {
        $negations = array("adminfmanlimit", "adminneedconfirm");
        if (!in_array($name, $negations)) {
            return !constant('_loginright_' . $name);
        } else {
            return constant('_loginright_' . $name);
        }
    } else {
        return true;
    }
}

/**
 * Sestavit kod ovladaciho panelu na smajliky a BBCode tagy
 * @param string $form nazev formulare
 * @param string $area nazev textarey
 * @param bool $ignorebbcode nezobrazit bbcode i kdyz je aktivni 1/0
 * @return string
 */
function _getPostformControls($form, $area, $ignorebbcode = false)
{
    global $_lang;

    $output = "";

    // nahled
    if (_bbcode || _smileys) {
        $output .= '<button onclick="_sysPostPreview(this, \'' . $form . '\', \'' . $area . '\'); return false;">' . $_lang['global.preview'] . '</button> &nbsp;&nbsp;';
    }

    // bbcode
    if (_bbcode and _template_bbcode_buttons and !$ignorebbcode) {

        // nacteni tagu
        static $bbtags;
        if (!isset($bbtags)) $bbtags = _parseBBCode(null, true);

        // pridani kodu
        foreach ($bbtags as $tag => $vars) {
            if (!isset($vars[4])) continue; // tag bez tlacitka
            $icon = (($vars[4] === 1) ? _templateImage("bbcode/" . $tag . ".png") : $vars[4]);
            $output .= "<a href=\"#\" onclick=\"return _sysAddBBCode('" . $form . "','" . $area . "','" . $tag . "', " . ($vars[0] ? 'true' : 'false') . ");\" class='bbcode-button'><img src=\"" . $icon . "\" alt=\"" . $tag . "\" /></a> ";
        }
    }

    // smajly
    if (_smileys) {

        if (_bbcode and !$ignorebbcode) $output .= "&nbsp;&nbsp;";
        for($x = 1; $x <= _template_smileys; ++$x) $output .= "<a href=\"#\" onclick=\"return _sysAddSmiley('" . $form . "','" . $area . "'," . $x . ");\"><img src=\"" . _templateImage("smileys/" . $x . "." . _template_smileys_format) . "\" alt=\"" . $x . "\" title=\"" . $x . "\" /></a> ";

        // odkaz na vypis dalsich smajlu
        if (_template_smileys_list) {
            global $_lang;
            $output .= "&nbsp;<a href=\"" . _indexroot . "remote/smileys.php\" target=\"_blank\" onclick=\"return _sysOpenWindow('" . _indexroot . "remote/smileys.php', 320, 475);\">" . $_lang['misc.smileys.list.link'] . "</a>";
        }

    }

    return "<span class='posts-form-buttons'>" . trim($output) . "</span>";
}

/**
 * Zkontrolovat log IP adres
 *
 * Typ  Popis                   Var
 *
 * 1    přihlášení              -
 * 2    přečtení článku         id clanku
 * 3    hodnocení článku        id clanku
 * 4    hlasování v anketě      id ankety
 * 5    zaslání požadavku       -
 * 6    pokus o aktivaci účtu   -
 * 7    žádost o obnovu hesla   -
 *
 * @param int $type typ zaznamu
 * @param mixed $var promenny argument dle typu
 * @return bool
 */
function _iplogCheck($type, $var = null)
{
    // vycisteni iplogu
    static $cleaned = false;
    if (!$cleaned) {
        DB::query("DELETE FROM `" . _mysql_prefix . "-iplog` WHERE (type=1 AND " . time() . "-time>" . _maxloginexpire . ") OR (type=2 AND " . time() . "-time>" . _artreadexpire . ") OR (type=3 AND " . time() . "-time>" . _artrateexpire . ") OR (type=4 AND " . time() . "-time>" . _pollvoteexpire . ") OR (type=5 AND " . time() . "-time>" . _postsendexpire . ") OR (type=6 AND " . time() . "-time>" . _accactexpire . ") OR (type=7 AND " . time() . "-time>" . _lostpassexpire . ")");
        $cleaned = true;
    }

    // priprava
    $return = true;
    $querybasic = "SELECT * FROM `" . _mysql_prefix . "-iplog` WHERE ip='" . _userip . "' AND type=" . $type;

    switch ($type) {

            // pokusy o prihlaseni
        case 1:
            $query = DB::query($querybasic);
            if (DB::size($query) != 0) {
                $query = DB::row($query);
                if ($query['var'] >= _maxloginattempts) {
                    $return = false;
                }
            }
            break;

            // precteni clanku, hodnoceni clanku, hlasovani v ankete
        case 2:
        case 3:
        case 4:
            $query = DB::query($querybasic . " AND var=" . $var);
            if (DB::size($query) != 0) {
                $return = false;
            }
            break;

            // zaslani komentare, prispevku nebo vzkazu; zadost o obnovu hesla
        case 5:
        case 7:
            $query = DB::query($querybasic);
            if (DB::size($query) != 0) {
                $return = false;
            }
            break;

            // pokus o aktivaci uctu
        case 6:
            $query = DB::query($querybasic);
            if (DB::size($query) != 0) {
                $query = DB::row($query);
                if ($query['var'] >= 5) {
                    $return = false;
                }
            }
            break;

    }

    return $return;
}

/**
 * Aktualizace logu IP adres
 * Pro info o argumentech viz {@link _ipLogCheck}
 * @param int $type typ zaznamu
 * @param mixed $var promenny argument dle typu
 */
function _iplogUpdate($type, $var = null)
{
    $querybasic = "SELECT * FROM `" . _mysql_prefix . "-iplog` WHERE ip='" . _userip . "' AND type=" . $type;

    switch ($type) {

            // prihlaseni
        case 1:
            $query = DB::query($querybasic);
            if (DB::size($query) != 0) {
                $query = DB::row($query);
                DB::query("UPDATE `" . _mysql_prefix . "-iplog` SET var=" . ($query['var'] + 1) . " WHERE id=" . $query['id']);
            } else {
                DB::query("INSERT INTO `" . _mysql_prefix . "-iplog` (ip,type,time,var) VALUES ('" . _userip . "',1," . time() . ",1)");
            }
            break;

            // precteni clanku
        case 2:
            DB::query("INSERT INTO `" . _mysql_prefix . "-iplog` (ip,type,time,var) VALUES ('" . _userip . "',2," . time() . "," . $var . ")");
            break;

            // hodnoceni clanku
        case 3:
            DB::query("INSERT INTO `" . _mysql_prefix . "-iplog` (ip,type,time,var) VALUES ('" . _userip . "',3," . time() . "," . $var . ")");
            break;

            // hlasovani v ankete
        case 4:
            DB::query("INSERT INTO `" . _mysql_prefix . "-iplog` (ip,type,time,var) VALUES ('" . _userip . "',4," . time() . "," . $var . ")");
            break;

            // odeslani komentare, prispevku nebo vzkazu; zadost o obnovu hesla
        case 5:
        case 7:
            DB::query("INSERT INTO `" . _mysql_prefix . "-iplog` (ip,type,time,var) VALUES ('" . _userip . "'," . $type . "," . time() . ",0)");
            break;

            // pokus o aktivaci uctu
        case 6:
            $query = DB::query($querybasic);
            if (DB::size($query) != 0) {
                $query = DB::row($query);
                DB::query("UPDATE `" . _mysql_prefix . "-iplog` SET var=" . ($query['var'] + 1) . " WHERE id=" . $query['id']);
            } else {
                DB::query("INSERT INTO `" . _mysql_prefix . "-iplog` (ip,type,time,var) VALUES ('" . _userip . "',6," . time() . ",1)");
            }
            break;

    }
}

/**
 * Sestaveni kodu pro kontrolu poli formulare javascriptem
 * @param string $form nazev formulare
 * @param array $inputs pole s nazvy poli ke kontrole
 * @return string
 */
function _jsCheckForm($form, $inputs)
{
    // kod
    $output = " onsubmit=\"if (";
    $count = count($inputs);
    for ($x = 1; $x <= $count; $x++) {
        $output .= $form . "." . $inputs[$x - 1] . ".value==''";
        if ($x != $count) {
            $output .= " || ";
        }
    }
    $output .= "){_sysAlert(1); return false;}\"";

    // return
    return $output;
}

/**
 * Sestavit kod pro limitovani delky textarey javascriptem
 * @param int $maxlength maximalni povolena delka textu
 * @param string $form nazev formulare
 * @param string $name nazev textarey
 * @return string
 */
function _jsLimitLength($maxlength, $form, $name)
{
    return "
<script type='text/javascript'>
//<![CDATA[
$(document).ready(function(){
    var events = ['keyup', 'mouseup', 'mousedown'];
    for (var i = 0; i < events.length; ++i) $(document)[events[i]](function() {
        _sysLimitTextArea(document.{$form}.{$name}, {$maxlength});
    });
});

//]]>
</script>
";
}

/**
 * Vyhodnotit pravo pristupu k cilovemu uzivateli
 * @param int $targetuserid ID ciloveho uzivatele
 * @return bool
 */
function _levelCheck($targetuserid)
{
    if (_loginindicator) {

        // nacteni dat uzivatele
        $data = _userDataCache($targetuserid);

        // kontrola
        if (_loginright_level > $data['level'] or $data['id'] == _loginid) {
            return true;
        } else {
            return false;
        }

    } else {
        return false;
    }
}

/**
 * Zpracovat identifikator stranky
 * @param string $idt
 * @return array array(array(anchor, segment), first_segment, full_combined_idt)
 */
function _parseIndexPath($idt)
{
    $idt = strval($idt);
    $idt_arr = array();
    $idt_size = 0;

    $segment = '';
    $slash = true;
    for ($i = 0, $last = (strlen($idt) - 1); isset($idt[$i]); ++$i) {

        $char = $idt[$i];
        if ('/' === $char) {
            if ($slash || $last === $i) $segment .= '/';
            else {
                $idt_arr[] = $segment;
                ++$idt_size;
                $segment = '';
            }
            $slash = true;
        } else {
            $segment .= $char;
            $slash = false;
        }

        if ($last === $i && '' !== $segment) {
            $idt_arr[] = $segment;
            ++$idt_size;
        }

    }

    $idt_first = (isset($idt_arr[0]) ? $idt_arr[0] : '');
    if ($idt_size > 2) {
        $idt_arr = array(implode('/', array_slice($idt_arr, 0, $idt_size - 1)), $idt_arr[$idt_size - 1]);
    }
    if (!isset($idt_arr[1])) {
        $idt_arr[1] = null;
    }

    return array($idt_arr, $idt_first, $idt);
}

/**
 * Nacist data stranky
 * @param string|array|null $title_seo 1 title_seo stranky, vice v poli (OR), nebo null
 * @param array|null $types pole s povolenymi typy stranek
 * @param string|null $extra_cols sql po vypisu sloupcu
 * @param string|null $extra_joins sql po nazvu hlavni tabulky
 * @param string|null $extra_conds sql na konci where (AND)
 * @return array|bool false pri nenalezeni
 */
function _fetchPageData($title_seo, $types = null, $extra_cols = null, $extra_joins = null, $extra_conds = null)
{
    $title_seo_multi = is_array($title_seo);

    // sestavit dotaz
    $sql = "SELECT page.*,inter.id AS inter_id,inter.title_seo AS inter_title_seo";
    if (null !== $extra_cols) $sql .= ',' . $extra_cols;
    $sql .= " FROM `" . _mysql_prefix . "-root` AS page LEFT JOIN `" . _mysql_prefix . "-root` AS inter ON(page.intersection=inter.id)";
    if (null !== $extra_joins) $sql .= ' ' . $extra_joins;

    $conds = array();
    if (null !== $title_seo) {
        if ($title_seo_multi) $conds[] = 'page.`title_seo` IN(' . DB::arr($title_seo) . ')';
        else $conds[] = 'page.`title_seo`=' . DB::val($title_seo);
    }
    if (null !== $types) $conds[] =  ' page.type IN(' . implode(',', $types) . ')';
    if (null !== $extra_conds) $conds[] = '(' . $extra_conds . ')';
    if (!empty($conds)) $sql .= ' WHERE ' . implode(' AND ', $conds);

    // nacist data
    $query = DB::query($sql);
    $pages = array();
    while ($row = DB::row($query)) {
        $pages[$row['title_seo']] = $row;
    }

    // zvolit zaznam a vratit
    if ($title_seo_multi) {
        // pri vice title_seo maji prioritu identifikatory ktere jsou v poli drive
        for ($i = 0; isset($title_seo[$i]); ++$i) {
            if (isset($pages[$title_seo[$i]])) {
                return $pages[$title_seo[$i]];
            }
        }
    } else {
        return current($pages);
    }

    return false;
}

/**
 * Sestavit adresu clanku
 * @param int $id ID clanku
 * @param string|null $anchor jiz nacteny identifikator clanku nebo null
 * @param string|null $category_anchor jiz nacteny identifikator kategorie nebo null
 * @return string
 */
function _linkArticle($id, $anchor = null, $category_anchor = null)
{
    static $cache = array();
    if (!isset($anchor) || !isset($category_anchor)) {
        if (isset($cache[$id])) $anchor = $cache[$id];
        else {
            $anchor = DB::rown(DB::query("SELECT art.`title_seo` AS art_ts, cat.`title_seo` AS cat_ts FROM `" . _mysql_prefix . "-articles` AS art JOIN `" . _mysql_prefix . "-root` AS cat ON(cat.id=art.home1) WHERE art.id=" . $id));
            if ($anchor === false) $anchor = array('---', '---');
            $cache[$id] = $anchor;
        }
    } elseif (!isset($cache[$id])) {
        $cache[$id] = $anchor = array($anchor, $category_anchor);
    } else {
        $anchor = array($anchor, $category_anchor);
    }

    return (_modrewrite ? $anchor[1] . '/' . $anchor[0] : "index.php?a=" . $anchor[1] . '/' . $anchor[0]);
}

/**
 * Sestavit adresu stranky
 * @param int $id ID stranky
 * @param string|null $anchor jiz nacteny identifikator nebo null
 * @param string|null $segment identifikator segmentu nebo null
 * @return string
 */
function _linkRoot($id, $anchor = null, $segment = null)
{
    static $cache = array();
    if ($id == _index_page_id) return ((_indexroot === './') ? './' : '');
    if (!isset($anchor)) {
        if (isset($cache[$id])) $anchor = $cache[$id];
        else {
            $anchor = DB::query_row("SELECT `title_seo` FROM `" . _mysql_prefix . "-root` WHERE id=" . $id);
            if ($anchor === false) $anchor = array('title_seo' => '---');
            $cache[$id] = $anchor = $anchor['title_seo'];
        }
    } elseif (!isset($cache[$id])) {
        $cache[$id] = $anchor;
    }
    if (null !== $segment) $anchor .= '/' . $segment;
    return (_modrewrite ? $anchor : "index.php?p=" . $anchor);
}

/**
 * Sestavit vlastni adresu stranky (napr. pro pluginy)
 * @param string $anchor cely identifikator stranky
 * @return string
 */
function _linkCustom($anchor)
{
    return (_modrewrite ? $anchor : 'index.php?p=' . $anchor);
}

/**
 * Sestavit kod odkazu na RSS zdroj
 *
 * $type    Popis               $id
 *
 * 1        komentare sekce     ID sekce
 * 2        komentare článku    ID článku
 * 3        prispevky knihy     ID knihy
 * 4        nejnovejsi články   ID kategorie nebo -1 pro vsechny kategorie
 * 5        nejnovějsi témata   ID fóra
 * 6        nejnovějsi odpovědi ID příspevku (tématu) ve foru
 *
 * @param int $id id polozky
 * @param int $type typ
 * @param bool $space vlozit mezeru pred odkaz 1/0
 * @return string
 */
function _linkRSS($id, $type, $space = true)
{
    global $_lang;
    if (_rss) return ($space ? ' ' : '') . "<a href='" . _indexroot . "remote/rss.php?tp=" . $type . "&amp;id=" . $id . "' target='_blank' title='" . $_lang['rss.linktitle'] . "'><img src='" . _templateImage("icons/rss.png") . "' alt='rss' class='icon' /></a>";
    return '';
}

/**
 * Ziskat data uzivatele z cache
 * @param int $id id uzivatele
 * @return array pole s klici id, publicname, username, group, avatar, icon, level, color
 */
function _userDataCache($id)
{
    // priprava cache
    static $cache = array('-1' => array('id' => -1, 'publicname' => '', 'username' => '---', 'group' => '2', 'avatar' => null, 'avatar_mode' => null, 'icon' => '', 'level' => 0, 'color' => '')),
        $extended = false,
        $extra_cols = array(), $extra_joins = ''
    ;

    // rozsireni nacitani
    if (!$extended) {
        $extended = true;
        $extend = array(); // array(array(array(extracol1, ...), join_sql), ...)
        _extend('call', 'user.cache.init', array('extend' => &$extend));
        for ($i = 0; isset($extend[$i]); ++$i) {
            for($ii = 0; isset($extend[$i][0][$ii]); ++$ii) $extra_cols[$extend[$i][0][$ii]] = $ii;
            if (isset($extend[$i][1])) $extra_joins .= ' ' . $extend[$i][1];
        }
    }

    // ziskani dat
    if (isset($cache[$id])) return $cache[$id];
    else {
        $q = DB::query_row("SELECT u.id,u.publicname,u.username,u.group,u.avatar,g.icon,g.level,g.color" . (!empty($extra_cols) ? ',' . implode(',', array_keys($extra_cols)) : '') . " FROM `" . _mysql_prefix . "-users` AS u JOIN `" . _mysql_prefix . "-groups` AS g ON (u.group=g.id)" . $extra_joins . " WHERE u.id=" . $id);
        if ($q === false) $q = $cache[-1];
        return ($cache[$id] = $q);
    }
}

/**
 * Sestaveni kodu odkazu na uzivatele
 * @param int $id id uzivatele
 * @param string|null $class trida na odkazu nebo null
 * @param bool $plain nezobrazovat ikonu ani barvu 1/0
 * @param bool $onlyname zobrazit jen jmeno bez odkazu 1/0
 * @param int|null $namelengthlimit limit delky zobrazeneho jmena nebo null
 * @param string $namesuffix retezec vlozeny za jmeno
 * @param bool $ignore_publicname zcela ignorovat publicname (vzdy pouzit username) 1/0
 * @return string
 */
function _linkUser($id, $class = null, $plain = false, $onlyname = false, $namelengthlimit = null, $namesuffix = "", $ignore_publicname = false)
{
    // nacteni dat uzivatele a skupiny
    $data = _userDataCache($id);

    if ($onlyname == false) {

        // ikona
        if ($plain == false) $icon = (($data['icon'] != "") ? "<img src='" . _indexroot . "pictures/groupicons/" . $data['icon'] . "' alt='icon' class='icon' /> " : '');
        else $icon = "";

        // vyber zobrazovaneho jmena
        if ($data['publicname'] != "" && !$ignore_publicname) $publicname = $data['publicname'];
        else $publicname = $data['username'];

        // trida
        $class = " class='user-link-" . $id . " user-link-group-" . $data['group'] . (isset($class) ? ' ' . $class : '') . "'";

        // kod odkazu
        if ($namelengthlimit != null) $publicname = _cutStr($publicname, $namelengthlimit);
        $link = "<a href='" . _indexroot . "index.php?m=profile&amp;id=" . $data['username'] . "'" . $class . (_administration ? " target='_blank'" : '') . (($data['color'] !== '' && !$plain) ? " style='color:" . $data['color'] . ";'" : '') . ">" . $publicname . $namesuffix . "</a>";

    } else {
        $icon = "";
        if ($data['publicname'] != "" && !$ignore_publicname) $link = $data['publicname'] . $namesuffix;
        else $link = $data['username'] . $namesuffix;
    }

    return $icon . $link;
}

/**
 * Sestavit kod odkazu na e-mail s ochranou
 * @param string $email emailova adresa
 * @return string
 */
function _mailto($email)
{
    $email = str_replace("@", _atreplace, $email);

    return "<a href='#' onclick='return _sysMai_lto(this);'>" . $email . "</a>";
}

/**
 * Vyhodnotit HCM moduly v retezci
 * @param string $input vstupni retezec
 * @param string $handler callback vyhodnocovace modulu
 * @return string
 */
function _parseHCM($input, $handler = '_parseHCM_module')
{
    return preg_replace_callback('|\[hcm\](.*?)\[/hcm\]|s', $handler, $input);
}

/**
 * @internal
 */
function _parseHCM_loadmodule($module, $f_name)
{
    // zjistit cestu modulu
    $module = explode('/', $module, 2);
    if (isset($module[1])) $module = basename($module[0]) . '/' . basename($module[1]);
    else $module = basename($module[0]);

    // nacist modul
    $file = _indexroot . 'plugins/hcm/' . $module . '.php';
    if (file_exists($file)) {
        require $file;

        return function_exists($f_name);
    }
}

/**
 * @internal
 */
function _parseHCM_module($match)
{
    SL::$hcmUid++;
    $params = _parseStr($match[1]);
    if (isset($params[0])) {
        $f_name = '_HCM_' . str_replace('/', '_', $params[0]);
        if (function_exists($f_name) or _parseHCM_loadmodule($params[0], $f_name)) {
            return call_user_func_array($f_name, array_splice($params, 1));
        }
    }
}

/**
 * @internal
 */
function _parseHCM_filter($match)
{
    global $__input;

    /*

    Mozne hodnoty promenne $__input:

    null - vsechny HCM moduly budou odstraneny
    array(true, array('one', 'two')) - jen hcm moduly 'one' a 'two' budou odstraneny
    array(false, array('one', 'two')) - jen hcm moduly 'one' a 'two' budou zachovany

    */

    $paramarray = _parseStr($match[1]);
    $mresult = $match[0];
    if (isset($paramarray[0])) {
        $paramarray[0] = mb_strtolower($paramarray[0]);
        if ($__input == null or (isset($paramarray[0][0]) and $paramarray[0][0] === '_') or ($__input[0] and in_array($paramarray[0], $__input[1])) or (!$__input[0] and !in_array($paramarray[0], $__input[1]))) {
            $mresult = "";
        }
    }

    return $mresult;
}

/**
 * Filtorvat HCM moduly v textu na zaklade opravneni
 * @param string $content vstupni text
 * @return string
 */
function _filtrateHCM($content)
{
    // odstraneni HCM php modulu
    if (!_loginright_adminhcmphp) {
        $filter = array('php', 'setlayout');
        _extend('call', 'hcm.filter.php', array('filter' => &$filter));
        $GLOBALS['__input'] = array(true, $filter);
        $content = _parseHCM($content, '_parseHCM_filter');
        unset($GLOBALS['__input']);
    }

    return $content;
}

/**
 * Vyhodnotit BBCode tagy
 * @param string $s vstupni retezec
 * @param bool $get_tags navratit seznam tagu namisto parsovani 1/0
 * @return string|array
 */
function _parseBBCode($s, $get_tags = false)
{
    // tag => array(0 => pair 1/0, 1 => arg 1/0, 2 => nestable 1/0, 3 => can-contain-children 1/0, 4 => button-icon(null = none | 1 = template | string = path))
    static $tags = array(
            'b' => array(true, false, true, true, 1), // bold
            'i' => array(true, false, true, true, 1), // italic
            'u' => array(true, false, true, true, 1), // underline
            'q' => array(true, false, true, true, null), // quote
            's' => array(true, false, true, true, 1), // strike
            'img' => array(true, false, false, false, 1), // image
            'code' => array(true, true, false, true, 1), // code
            'c' => array(true, false, true, true, null), // inline code
            'url' => array(true, true, true, false, 1), // link
            'hr' => array(false, false, false, false, 1), // horizontal rule
            'color' => array(true, true, true, true, null), // color
            'size' => array(true, true, true, true, null), // size
        ),
        $syntax = array('[', ']', '/', '=', '"'), // syntax
        $merged = false // status of merge with _extend
    ;

    // merge tags with _extend
    if (!$merged) {
        _extend('call', 'bbcode.extend.tags', array('tags' => &$tags));
        $merged = true;
    }

    // get tags only?
    if ($get_tags) return $tags;

    // prepare
    $mode = 0;
    $submode = 0;
    $closing = false;
    $parents = array();
    $parents_n = -1;
    $tag = '';
    $output = '';
    $buffer = '';
    $arg = '';
    $reset = 0;

    // scan
    for ($i = 0; isset($s[$i]); ++$i) {

        // get char
        $char = $s[$i];

        // mode step
        switch ($mode) {

                ########## look for tag ##########
            case 0:
                if ($char === $syntax[0]) {
                    $mode = 1;
                    if ($parents_n === -1) $output .= $buffer;
                    else $parents[$parents_n][2] .= $buffer;
                    $buffer = '';
                }
                break;

                ########## scan tag ##########
            case 1:
                if (($ord = ord($char)) > 47 && $ord < 59 || $ord > 64 && $ord < 91 || $ord > 96 && $ord < 123) $tag .= $char;
                elseif ($tag === '' && $char === $syntax[2]) {
                    // closing tag
                    $closing = true;
                    break;
                } elseif ($char === $syntax[1]) {
                    // tag end
                    $tag = mb_strtolower($tag);
                    if (isset($tags[$tag])) {
                        if ($parents_n === -1 || $tags[$tag][2] || $tags[$tag][0] && $closing) {
                            if ($tags[$tag][0]) {
                                // paired tag
                                if ($closing) {
                                    if ($parents_n === -1 || $parents[$parents_n][0] !== $tag) $reset = 2; // reset - invalid closing tag
                                    else {
                                        --$parents_n;
                                        $pop = array_pop($parents);
                                        $buffer = _parseBBCode_processTag($pop[0], $pop[1], $pop[2]);
                                        if ($parents_n === -1) $output .= $buffer;
                                        else $parents[$parents_n][2] .= $buffer;
                                        $reset = 1;
                                        $char = '';
                                    }
                                } elseif ($parents_n === -1 || $tags[$parents[$parents_n][0]][3]) {
                                    // opening tag
                                    $parents[] = array($tag, $arg, '');
                                    ++$parents_n;
                                    $buffer = '';
                                    $char = '';
                                    $reset = 1;
                                } else {
                                    // reset - disallowed children
                                    $reset = 7;
                                }
                            } else {
                                // standalone tag
                                $buffer = _parseBBCode_processTag($tag, $arg);
                                if ($parents_n === -1) $output .= $buffer;
                                else $parents[$parents_n][2] .= $buffer;
                                $reset = 1;
                            }
                        } else {
                            // reset - disallowed nesting
                            $reset = 3;
                        }
                    } else {
                        // reset - bad tag
                        $reset = 4;
                    }
                } elseif ($char === $syntax[3]) {
                    if (isset($tags[$tag]) && $tags[$tag][1] === true && $arg === '' && !$closing) {
                        $mode = 2; // scan tag argument
                    } else {
                        // reset - bad / no argument
                        $reset = 5;
                    }
                }
                break;

                ########## scan tag argument ##########
            case 2:

                // detect submode
                if ($submode === 0) {
                    if ($char === $syntax[4]) {
                        // quoted mode
                        $submode = 1;
                        break;
                    } else {
                        // unquoted mode
                        $submode = 2;
                    }
                }

                // gather argument
                if ($submode === 1) {
                    if ($char !== $syntax[4]) {
                        // char ok
                        $arg .= $char;
                        break;
                    }
                } elseif ($char !== $syntax[1]) {
                    // char ok
                    $arg .= $char;
                    break;
                }

                // end
                if ($submode === 2) {
                    // end of unquoted
                    $mode = 1;
                    $char = '';
                    --$i;
                } else {
                    // end of quoted
                    if (isset($s[$i + 1]) && $s[$i + 1] === $syntax[1]) {
                        $mode = 1;
                    } else {
                        // reset - bad syntax
                        $reset = 6;
                    }
                }

                break;

        }

        // buffer char
        $buffer .= $char;

        // reset
        if ($reset !== 0) {
            if ($reset > 1) {
                if ($parents_n === -1) $output .= $buffer;
                else $parents[$parents_n][2] .= $buffer;
            }
            $buffer = '';
            $reset = 0;
            $mode = 0;
            $submode = 0;
            $closing = false;
            $tag = '';
            $arg = '';
        }

    }

    // flush remaining parents or buffer
    if ($parents_n !== -1)
        for($i = 0; isset($parents[$i]); ++$i) $output .= $parents[$i][2];
        else $output .= $buffer;

    // return output
    return $output;
}

/**
 * @internal
 */
function _parseBBCode_processTag($tag, $arg = '', $buffer = null)
{
    // merge processor with _extend
    static $ext;
    if (!isset($ext)) {
        $ext = array();
        _extend('call', 'bbcode.extend.proc', array('tags' => &$ext));
    }

    // process
    if (isset($ext[$tag])) return call_user_func($ext[$tag], $arg, $buffer);
    switch ($tag) {

        case 'b':
            if ($buffer === '') return;
            return '<strong>' . $buffer . '</strong>';

        case 'i':
            if ($buffer === '') return;
            return '<em>' . $buffer . '</em>';

        case 'u':
            if ($buffer === '') return;
            return '<u>' . $buffer . '</u>';

        case 'q':
            if ($buffer === '') return;
            return '<q>' . $buffer . '</q>';

        case 's':
            if ($buffer === '') return;
            return '<del>' . $buffer . '</del>';

        case 'code':
            if ($buffer === '') return;
            return '<span class="pre">' . $buffer . '</span>';

        case 'c':
            if ($buffer === '') return;
            return '<code>' . $buffer . '</code>';

        case 'url':
            if ($buffer === '') return;
            if ($arg !== '') $url = $arg;
            else $url = $buffer;
            $url = trim($url);
            if (!_isSafeUrl($url)) $url = '#';
            else $url = _addSchemeToURL($url);
            return '<a href="' . str_replace(array("\r", "\n"), '', $url) . '" rel="nofollow" target="_blank">' . $buffer . '</a>';

        case 'hr':
            return '<span class="hr"></span>';

        case 'color':
            static $colors = array('aqua' => 0, 'black' => 1, 'blue' => 2, 'fuchsia' => 3, 'gray' => 4, 'green' => 5, 'lime' => 6, 'maroon' => 7, 'navy' => 8, 'olive' => 9, 'orange' => 10, 'purple' => 11, 'red' => 12, 'silver' => 13, 'teal' => 14, 'white' => 15, 'yellow' => 16);
            if ($buffer === '') return;
            if (preg_match('/^#[0-9A-Fa-f]{3,6}$/', $arg) !== 1) {
                $arg = mb_strtolower($arg);
                if (!isset($colors[$arg])) return $buffer;
            }

            return '<span style="color:' . $arg . ';">' . $buffer . '</span>';

        case 'size':
            if ($buffer === '') return;
            $arg = intval($arg);
            if ($arg < 1 || $arg > 8) return $buffer;
            return '<span style="font-size:' . round((0.5 + ($arg / 6)) * 100) . '%;">' . $buffer . '</span>';

        case 'img':
            $buffer = trim($buffer);
            if ($buffer === '' || !_isSafeUrl($buffer)) return;
            $buffer = _addSchemeToURL($buffer);

            return '<img src="' . str_replace(array("\r", "\n"), '', $buffer) . '" alt="img" class="bbcode-img" />';

    }
}

/**
 * Vyhodnotit text prispevku
 * @param string $input vstupni text
 * @param bool $smileys vyhodnotit smajliky 1/0
 * @param bool $bbcode vyhodnotit bbcode 1/0
 * @param bool $nl2br prevest odrakovani na <br />
 * @returns string
 */
function _parsePost($input, $smileys = true, $bbcode = true, $nl2br = true)
{
    // vyhodnoceni smajlu
    if (_smileys and $smileys) {
        $input = preg_replace('/\*(\d{1,3})\*/s', '<img src=\'' . _indexroot . 'plugins/templates/' . _template . '/images/smileys/$1.' . _template_smileys_format . '\' alt=\'$1\' class=\'post-smiley\' />', $input, 32);
    }

    // vyhodnoceni BBCode
    if (_bbcode and $bbcode) $input = _parseBBCode($input);

    // prevedeni novych radku
    if ($nl2br) $input = nl2br($input);

    // navrat vystupu
    return $input;
}

/**
 * Vyhodnotit pravo aktualniho uzivatele k pristupu ke clanku
 * @param array $res pole s daty clanku (potreba id,time,confirmed,public,home1,home2,home3)
 * @return int 0 - pristup odepren, 1 - pristup povolen, 2 - vyzadovano prihlaseni
 */
function _articleAccess($res)
{
    // nevydany / neschvaleny clanek
    if (!$res['confirmed'] || $res['time'] > time()) {
        if (_loginright_adminconfirm || $res['author'] == _loginid) return 1;
        return 0;
    }

    // kontrola kategorii
    $homes = array($res['home1']);
    if ($res['home2'] != -1) $homes[] = $res['home2'];
    if ($res['home3'] != -1) $homes[] = $res['home3'];
    $q = DB::query('SELECT public,level FROM `' . _mysql_prefix . '-root` WHERE id IN(' . implode(',', $homes) . ')');
    while ($r = DB::row($q)) {
        if (_publicAccess($r['public'], $r['level'])) {
            // do kategorie je pristup (staci alespon 1)
            return 1;
        }
    }

    return 2; // neni pristup
}

/**
 * Vyhodnotit pravo uzivatele na pristup k prispevku
 * @param array $post data prispevku (potreba author, time)
 * @return bool
 */
function _postAccess($post)
{
    // uzivatel je prihlasen
    if (_loginindicator) {
        // je uzivatel autorem prispevku?
        if (_loginid == $post['author'] && ($post['time'] + _postadmintime > time() || _loginright_unlimitedpostaccess)) {
            return true;
        } elseif (_loginright_adminposts) {
            // uzivatel ma pravo spravovat cizi prispevky
            $data = _userDataCache($post['author']);
            if (_loginright_level > $data['level']) return true;
        }
    }

    return false;
}

/**
 * Vyhodnocenu prava aktualniho uzivatele pro pristup na zaklade verejnosti, urovne a stavu prihlaseni
 * @param bool $public polozka je verejna 1/0
 * @param int|null $level minimalni pozadovana uroven
 * @return bool
 */
function _publicAccess($public, $level = 0)
{
    if ((_loginindicator || $public == 1) && _loginright_level >= $level) return true;
    return false;
}

/**
 * Obnoveni hodnoty pole formulare na zaklade _formData v GET
 * @param string $name jmeno hodnoty pro obnovu
 * @param string|null $else vychozi hodnota
 * @param bool $noparam nepouzivat 'value=' pri vypisu hodnoty 1/0
 * @return string
 */
function _restoreGetFdValue($name, $else = null, $noparam = false)
{
    if ($noparam) {
        $param_start = "";
        $param_end = "";
    } else {
        $param_start = " value='";
        $param_end = "'";
    }

    if (isset($_GET['_formData'][$name])) {
        return $param_start . _htmlStr($_GET['_formData'][$name]) . $param_end;
    } else {
        if ($else != null) {
            return $param_start . _htmlStr($else) . $param_end;
        }
    }
}

/**
 * Strankovani vysledku
 *
 * Format vystupu:
 * array(
 * 0 => html kod seznamu stran,
 * 1 => cast sql dotazu - limit,
 * 2 => aktualni strana,
 * 3 => celkovy pocet stran,
 * 4 => pocet polozek,
 * 5 => cislo prvni zobrazene polozky,
 * 6 => cislo posledni zobrazene polozky,
 * 7 => pocet polozek na jednu stranu
 * )
 * Cisla polozek zacinaji od 0.
 *
 * @param string $url vychozi adresa
 * @param int $limit limit polozek na 1 stranu
 * @param string|int $table nazev tabulky (tabulka[:alias]) nebo celkovy pocet polozek
 * @param string $conditions kod SQL dotazu za WHERE v SQL dotazu pro zjistovani poctu polozek; pokud je $table cislo, nema tato promenna zadny vyznam
 * @param string $linksuffix retezec pridavany za kazdy odkaz generovany strankovanim
 * @param string|null $param nazev parametru pro cislo strany (null = 'page')
 * @param bool $autolast posledni strana je vychozi strana 1/0
 * @return array
 */
function _resultPaging($url, $limit, $table, $conditions = "1", $linksuffix = "", $param = null, $autolast = false)
{
    global $_lang;

    // alias tabulky
    if (is_string($table)) {
        $table = explode(':', $table);
        $alias = (isset($table[1]) ? $table[1] : null);
        $table = $table[0];
    } else $alias = null;

    // priprava promennych
    if (!isset($param)) $param = 'page';
    if (is_string($table)) $count = DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-" . $table . "`" . (isset($alias) ? " AS {$alias}" : '') . " WHERE " . $conditions), 0);
    else $count = $table;
    if ($count == 0) $count = 1;

    $pages = ceil($count / $limit);
    if (isset($_GET[$param])) $s = abs(intval($_GET[$param]) - 1);
    elseif ($autolast) $s = $pages - 1;
    else $s = 0;

    if ($s + 1 > $pages) $s = $pages - 1;
    $start = $s * $limit;
    $beginpage = $s + 1 - _showpages;
    if ($beginpage < 1) {
        $endbonus = abs($beginpage) + 1;
        $beginpage = 1;
    } else {
        $endbonus = 0;
    }
    $endpage = $s + 1 + _showpages + $endbonus;
    if ($endpage > $pages) {
        $beginpage -= $endpage - $pages;
        if ($beginpage < 1) {
            $beginpage = 1;
        }
        $endpage = $pages;
    }

    // vypis stran

    // oddelovaci symbol v url
    if (mb_substr_count($url, "?") == 0) {
        $url .= "?";
    } else {
        $url .= "&amp;";
    }

    if ($pages > 1) {
        $paging = "<span>";
        for ($x = $beginpage; $x <= $endpage; $x++) {
            if ($x == $s + 1) {
                $class = " class='act'";
            } else {
                $class = "";
            }
            $paging .= "<a href='" . $url . $param . "=" . $x . $linksuffix . "'" . $class . ">" . $x . "</a>\n";
            if ($x != $endpage) {
                $paging .= " ";
            }
        }
        $paging .= "</span>";

        // ovladaci prvky

        // minus
        if ($s + 1 != 1) {
            $paging = "<a href='" . $url . $param . "=" . ($s) . $linksuffix . "'>&laquo; " . $_lang['global.previous'] . "</a>&nbsp;&nbsp;" . $paging;
        }
        if ($beginpage > 1) {
            $paging = "<a href='" . $url . $param . "=1" . $linksuffix . "' title='" . $_lang['global.first'] . "'>1</a> ... " . $paging;
        }

        // plus
        if ($s + 1 != $pages) {
            $paging .= "&nbsp;&nbsp;<a href='" . $url . $param . "=" . ($s + 2) . $linksuffix . "'>" . $_lang['global.next'] . " &raquo;</a>";
        }
        if ($endpage < $pages) {
            $paging .= " ... <a href='" . $url . $param . "=" . $pages . $linksuffix . "' title='" . $_lang['global.last'] . "'>" . $pages . "</a>";
        }

        $paging = "\n<div class='paging'>\n" . $_lang['global.paging'] . ":&nbsp;&nbsp;" . $paging . "\n</div>\n\n";
    } else {
        $paging = "";
    }

    // return
    $end_item = ($start + $limit - 1);

    return array($paging, "LIMIT " . $start . ", " . $limit, ($s + 1), $pages, $count, $start, (($end_item > $count - 1) ? $count - 1 : $end_item), $limit);
}

/**
 * Zjistit stranku, na ktere se polozka nachazi pri danem strankovani a podmince razeni
 * @param int $limit pocet polozek na jednu stranu
 * @param string $table nazev tabulky v databazi (bez prefixu)
 * @param string $conditions kod SQL dotazu za WHERE v SQL dotazu pro zjistovani poctu polozek
 * @return int
 */
function _resultPagingGetItemPage($limit, $table, $conditions = "1")
{
    $count = DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-" . $table . "` WHERE " . $conditions), 0);

    return intval($count / $limit) + 1;
}

/**
 * Zjisteni, zda je polozka s urcitym cislem v rozsahu aktualni strany strankovani
 * @param array $pagingdata pole, ktere vraci funkce {@link _resultPaging}
 * @param int $itemnumber poradove cislo polozky (poradi zacina nulou)
 * @return bool
 */

function _resultPagingIsItemInRange($pagingdata, $itemnumber)
{
    if ($itemnumber >= $pagingdata[5] and $itemnumber <= $pagingdata[6]) return true;
    return false;
}

/**
 * Navrat na predchozi stranku
 * Zarizeno pomoci $_GET['_return'] nebo $_SERVER['HTTP_REFERER']
 */
function _returnHeader()
{
    // odeslani headeru
    if (isset($_GET['_return']) and $_GET['_return'] != "") {
        $url = $_GET['_return'];
        if ($url[0] === '/') $url = 'http://' . _getDomain() . $url;
        else $url = _url . '/' . $url;
        header("Location: " . $url);
        exit;
    }

    // alternativa pomoci refereru anebo zakladni url
    if (isset($_SERVER['HTTP_REFERER']) and $_SERVER['HTTP_REFERER'] != "") header("Location: " . $_SERVER['HTTP_REFERER']);
    else header("Location: " . _url . '/');
    exit;
}

/**
 * Sestaveni casti SQL dotazu po WHERE pro vyhledani clanku v urcitych kategoriich.
 * @param string|null $ids seznam ID kategorii oddelenych pomlckami nebo null
 * @return string
 */
function _sqlArticleWhereCategories($ids)
{
    if ($ids != null) {
        $ids = _arrayRemoveValue(@explode("-", $ids), "");
        $sql_code = "(";
        $sql_count = count($ids);
        $counter = 1;
        foreach ($ids as $rcat) {
            $rcat = intval($rcat);
            $sql_code .= "(home1=" . $rcat . " OR home2=" . $rcat . " OR home3=" . $rcat . ")";
            if ($counter != $sql_count) $sql_code .= " OR ";
            ++$counter;
        }
        $sql_code .= ")";

        return $sql_code;
    }

    return "";
}

/**
 * Sestaveni casti SQL dotazu po WHERE pro filtrovani neviditelnych, nevydanych a nepristupnych clanku
 * @param bool $check_category_public kontrolovat verejnost vsech domovskych kategorii 1/0
 * @param bool $exclude_invisible odfiltrovat neviditelne clanky 1/0
 * @return string
 */
function _sqlArticleFilter($check_category_public = false, $exclude_invisible = true)
{
    $output = "art.confirmed=1 AND art.time<=" . time();
    if ($exclude_invisible) $output .= " AND art.visible=1";
    if (!_loginindicator) $output .= " AND art.public=1";
    if ($check_category_public and !_loginindicator) $output .= " AND ((SELECT public FROM `" . _mysql_prefix . "-root` WHERE id=art.home1)=1 OR (SELECT public FROM `" . _mysql_prefix . "-root` WHERE id=art.home2)=1 OR (SELECT public FROM `" . _mysql_prefix . "-root` WHERE id=art.home3)=1)";
    return $output;
}

/**
 * Sestaveni casti SQL dotazu po WHERE pro filtrovani zaznamu podle moznych hodnot daneho sloupce
 * @param string $column nazev sloupce v tabulce
 * @param string|array $values mozne hodnoty sloupce v poli, oddelene pomlckami nebo "all" pro vypnuti limitu
 * @return string
 */
function _sqlWhereColumn($column, $values)
{
    if ($values !== 'all') {
        if (!is_array($values)) {
            $values = explode('-', $values);
        }
        return $column . ' IN(' . DB::val($values, true) . ')';
    } else {
        return '1';
    }
}

/**
 * Sestavit kod systemoveho formulare
 *
 * $id          Popis                                       $vars
 *
 * login        prihlasovaci formular                       -
 * notpublic    prihlasovaci formular (neverejny obsah)     [wholesite 1/0]
 * postform     formular pro zaslani prispevku/komentare    [posttype => viz fce _postsOutput, posttarget => id_home, xhome => id_xhome, [pluginflag(pouze pro typ 7)] => xx)]
 *
 * @param string $id identifikator formulare
 * @param array $vars promenne dle typu
 * @param bool $notitle nevkladat titulek do formulare 1/0
 * @param bool $extend volat extend udalosti 1/0
 * @return array array(content, title)
 */
function _uniForm($id, $vars = array(), $notitle = false, $extend = true)
{
    // priprava
    global $_lang;
    $content = "";
    $title = "";

    // extend
    if ($extend) {
        _extend('call', 'sys.form', array(
            'id' => $id,
            'vars' => $vars,
            'notitle' => &$notitle,
            'content' => &$content,
        ));
    }

    // typ
    if ('' === $content) {
        switch ($id) {

                /* ---  prihlaseni  --- */
            case "login":

                // titulek
                $title = $_lang['login.title'];

                // zpravy
                if (isset($_GET['_mlr'])) {
                    switch ($_GET['_mlr']) {
                        case 0:
                            $content .= _formMessage(2, $_lang['login.failure']);
                            break;
                        case 1:
                            if (_loginindicator and !_administration) {
                                $content .= _formMessage(1, $_lang['login.success']);
                            }
                            break;
                        case 2:
                            if (!_loginindicator) {
                                $content .= _formMessage(2, $_lang['login.blocked.message']);
                            }
                            break;
                        case 3:
                            if (!_loginindicator) {
                                $content .= _formMessage(3, $_lang['login.securitylogout']);
                            }
                            break;
                        case 4:
                            if (!_loginindicator) {
                                $content .= _formMessage(1, $_lang['login.selfremove']);
                            }
                            break;
                        case 5:
                            if (!_loginindicator) {
                                $content .= _formMessage(2, str_replace(array("*1*", "*2*"), array(_maxloginattempts, _maxloginexpire / 60), $_lang['login.attemptlimit']));
                            }
                            break;
                        case 6:
                            $content .= _formMessage(3, $_lang['xsrf.msg']);
                            break;
                    }
                }

                // obsah
                if (!_loginindicator) {

                    // adresa pro navrat
                    if (isset($_GET['login_form_return'])) $return = $_GET['login_form_return'];
                    else $return = $_SERVER['REQUEST_URI'];

                    // adresa formulare
                    $form_url = parse_url($_SERVER['REQUEST_URI']);
                    if (isset($form_url['query'])) {
                        parse_str($form_url['query'], $form_url['query']);
                        unset($form_url['query']['_formData'], $form_url['query']['_mlr']);
                        $form_url = _buildURL($form_url);
                    } else {
                        $form_url = $_SERVER['REQUEST_URI'];
                    }

                    // kod formulare
                    $callArgs = array(
                        "login_form",
                        _indexroot . "remote/login.php?_return=" . urlencode($return),
                        array(
                            array($_lang['login.username'], "<input type='text' name='username' class='inputmedium'" . _restoreGetFdValue("username") . " maxlength='24' />"),
                            array($_lang['login.password'], "<input type='password' name='password' class='inputmedium' />")
                        ),
                        null,
                        $_lang['global.login'],
                        "&nbsp;&nbsp;<label><input type='checkbox' name='persistent' value='1' /> " . $_lang['login.persistent'] . "</label><input type='hidden' name='form_url' value='" . _htmlStr($form_url) . "' />
                        &nbsp;&nbsp;<label><input type='checkbox' name='ipbound' value='1' checked='checked' /> " . (isset($_lang['login.ipbound']) ? $_lang['login.ipbound'] : 'zabezpečené') . "</label>"
                    );

                    if ($extend) {
                        _extend('call', 'sys.form.login', array('call' => &$callArgs));
                    }

                    $content .= call_user_func_array('_formOutput', $callArgs);

                    // odkazy
                    if (_registration or _lostpass) {
                        $content .= "\n\n<p>\n" . ((_registration and !_administration) ? "<a href='" . _indexroot . "index.php?m=reg'>" . $_lang['mod.reg'] . " &gt;</a>\n" : '') . (_lostpass ? ((_registration and !_administration) ? "<br />" : '') . "<a href='" . _indexroot . "index.php?m=lostpass'>" . $_lang['mod.lostpass'] . " &gt;</a>\n" : '') . "</p>";
                    }

                } else {
                    $content .= "<p>" . $_lang['login.ininfo'] . " <em>" . _loginname . "</em> - <a href='" . _xsrfLink(_indexroot . "remote/logout.php") . "'>" . $_lang['usermenu.logout'] . "</a>.</p>";
                }

                break;

                /* ---  zprava o neverejnosti obsahu (0-notpublicsite)  --- */
            case "notpublic":
                $form = _uniForm("login", array(), true);
                if (!isset($vars[0])) {
                    $vars[0] = false;
                }
                $content = "<p>" . $_lang['notpublic.p' . (($vars[0] == true) ? '2' : '')] . "</p>" . $form[0];
                $title = $_lang['notpublic.title'];
                break;

                /* ---  formular pro zaslani prispevku / komentare (posttype,posttarget,xhome,url)  --- */
            case "postform":
                $title = "";
                $notitle = true;

                // pole
                $inputs = array();
                $captcha = _captchaInit();
                $content = _jsLimitLength(16384, "postform", "text");
                if (_loginindicator == 0) $inputs[] = array($_lang['posts.guestname'], "<input type='text' name='guest' maxlength='24' class='inputsmall'" . _restoreGetFdValue("guest") . " />");
                if ($vars['xhome'] == -1) $inputs[] = array($_lang[(($vars['posttype'] != 5) ? 'posts.subject' : 'posts.topic')], "<input type='text' name='subject' class='input" . (($vars['posttype'] != 5) ? 'small' : 'medium') . "' maxlength='" . (($vars['posttype'] != 5) ? 22 : 48) . "'" . _restoreGetFdValue("subject") . " />");
                $inputs[] = $captcha;
                $inputs[] = array($_lang['posts.text'], "<textarea name='text' class='areamedium' rows='5' cols='33'>" . _restoreGetFdValue("text", null, true) . "</textarea><input type='hidden' name='_posttype' value='" . $vars['posttype'] . "' /><input type='hidden' name='_posttarget' value='" . $vars['posttarget'] . "' /><input type='hidden' name='_xhome' value='" . $vars['xhome'] . "' />" . (isset($vars['pluginflag']) ? "<input type='hidden' name='_pluginflag' value='" . $vars['pluginflag'] . "' />" : ''), true);

                // formular
                $callArgs = array(
                    'postform',
                    _addGetToLink(_indexroot . "remote/post.php", "_return=" . urlencode($vars['url']), false),
                    $inputs,
                    array("text"),
                    null,
                    _getPostformControls("postform", "text")
                );

                if ($extend) {
                    _extend('call', 'sys.form.postform', array(
                        'call' => &$callArgs,
                        'vars' => $vars,
                    ));
                }

                $content .= call_user_func_array('_formOutput', $callArgs);

                break;

        }
    }

    // return
    if ((_template_autoheadings == 1 or _administration == 1) and $notitle == false) {
        $content = "<h1>$title</h1>\n" . $content;
    }

    return array($content, $title);
}


/**
 * Odhlaseni aktualniho uzivatele
 * @param bool $destroy uplne znicit session
 * @return bool
 */
function _userLogout($destroy = true)
{
    if (!defined('_loginindicator') or _loginindicator == 1) {
        $_SESSION = array();
        if ($destroy) {
            session_destroy();
            setcookie(session_name(), '', time() - 3600, '/');
        }
        if (isset($_COOKIE[_sessionprefix . "persistent_key"])) @setcookie(_sessionprefix . "persistent_key", "", (time() - 3600), "/");
        return true;
    }

    return false;
}

/**
 * Kontrola podpory formatu GD knihovnou
 * @param string|null $check_format nazev formatu (jpg,jpeg,png,gif) jehoz podpora se ma zkontrolovat nebo null
 * @param bool $print_image poslat do vystupu obrazek se zpravou o nepodporovanem formatu a ukoncit skript 1/0
 * @return bool
 */
function _checkGD($check_format = null, $print_image = false)
{
    if (function_exists('gd_info')) {
        if (isset($check_format)) {

            $info = gd_info();
            $support = false;
            switch (strtolower($check_format)) {
                case 'png':
                    if (isset($info['PNG Support']) and $info['PNG Support'] == true) $support = true;
                    break;
                case 'jpg':
                case 'jpeg':
                    if ((isset($info['JPG Support']) and $info['JPG Support'] == true) or (isset($info['JPEG Support']) and $info['JPEG Support'] == true)) $support = true;
                    break;
                case 'gif':
                    if (isset($info['GIF Read Support']) and $info['GIF Read Support'] == true) $support = true;
                    break;
            }

            if ($support) return true;
            elseif ($print_image) $img = '2klEQVR4nGP538jyh4HlOwPLf5Y/LEdY/mtEsjDcaGRhYACKMDSy/NMC8h0YWJgmdLJsWyPP4nUKKH7BgOEgC4PeVm8GFgYmb6DKt+sYhIVZOCVe/ADpYgDJLkhocGRhkO4DyjL8CVgEJIH2AElXhoaDINP/MbB8285VPJ2FkVF1yWeWD+3Nn8RZJE8yAfWCTACBj0B1HwQYGA4A7Wdg+HJcdZETyAQGHqAwA5gl4MkwCcKCAijrgQwDy7+dHLINQnkJU4FiFk+Bgg1AX3gyKLQoQExmYFEAKQUAOQ48juT2O7oAAAAASUVORK5CYII=';
            else return false;

        } else {
            return true;
        }
    } elseif (!$print_image) {
        return false;
    } else {
        $img = 'zElEQVR4nGP538jyiYGFj4HlP8sClgSW/4f2szDYATEDUIShkYXBwpPl9R8GFtEzjWAxEP5z+YPDQRbmDQ4OQLEGhgUPQaIKYBKsi4HhIMvfhU4KQLbAywWOQHHuCY4sjO9/GDCwMCcjqX8n9IGB5XfCn2cToXpZN4H0/meAgI8sDB/qeyF2MvxlYHhwxVoUyGIG8v+DxQQmMqio/IO4CgiYwKz/v9kY/jMCWY8erw1V6ugHsni/de1ntgPL8jT4KCwFsQQtLRm+ADUBAA7YQI0qsisiAAAAAElFTkSuQmCC';
    }

    // odeslani chyboveho obrazku
    header('Content-Type: image/PNG');
    echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAEQAAAAWAQAAAAEmKTd1AAAACXBIWXMAAAsSAAALEgHS3X78AAAA' . $img);
    exit;
}

/**
 * Vynutit existenci klicu v danem globalnim poli (pokud nejsou vsechny, ukonci se skript)
 * @param string $array_name nazev globalniho pole (_POST, _GET, ...)
 * @param array $keys pole s klici, ktere musi existovat
 */
function _checkKeys($array_name, $keys)
{
    global $$array_name;
    if (!is_array($$array_name)) return;
    foreach ($keys as $key) {
        if (!isset(${$array_name}[$key])) {
            global $_lang;
            if (!headers_sent()) {
                header('HTTP/1.1 503 Service Temporarily Unavailable');
                header('Retry-After: 600');
            }
            die($_lang['global.badinput']);
        }
    }
}

/**
 * Ziskat kod avataru daneho uzivatele
 * @param int $id id uzivatele
 * @param bool $path_only ziskat pouze cestu namisto html kodu obrazku 1/0
 * @param bool $return_null vratit null pokud uzivatel nema avatar namisto vychoziho avataru 1/0
 * @param bool $full_size vynutit plnou velikost (pro pluginy) 1/0
 * @param bool $no_plugins ignorovat pluginy 1/0
 * @param bool $no_link neodkazovat na profil 1/0
 * @return string|null
 */
function _getAvatar($id, $path_only = false, $return_null = false, $full_size = false, $no_plugins = false, $no_link = false)
{
    // adresar
    $path = _indexroot . 'pictures/avatars/';

    // nacteni dat
    $exists = false;
    if ($id != -1) {
        $udata = _userDataCache($id);
        if (isset($udata['avatar'])) $exists = true;
    }

    // zpracovani rozsirenim
    if ($id != -1 && !$no_plugins) {
        $extend_return = false;
        _extend('call', 'user.avatar', array('return' => &$extend_return, 'udata' => $udata, 'path_only' => $path_only, 'return_null' => $return_null, 'full_size' => $full_size));
        if ($extend_return !== false) return $extend_return;
    }

    // neexistuje?
    if (!$exists && $return_null) return;

    // doplneni cesty
    $path .= ($exists ? $udata['avatar'] : 'no-avatar' . (_template_dark ? '-dark' : '')) . '.jpg';
    if ($path_only) return $path;

    // navrat kodu
    $output = '<img src="' . $path . '" alt="' . ($exists ? (($udata['publicname'] === '') ? $udata['username'] : $udata['publicname']) : 'avatar') . '" class="avatar2" />';
    if ($id != -1 && !$no_link) $output = '<a href="' . _indexroot . 'index.php?m=profile&amp;id=' . $udata['username'] . '">' . $output . '</a>';
    return $output;
}

/**
 * Ziskat nazev domeny z dane URL
 * @param string $url adresa
 * @return string|null nazev domeny (host) nebo null
 */
function _getDomain($url = _url)
{
    // check cache
    static $cache = array();
    if (isset($cache[$url])) return $cache[$url];

    // extract, cache and return
    $purl = @parse_url($url);

    return ($cache[$url] = (isset($purl['host']) ? $purl['host'] : null));
}

/**
 * Sestavit hlavicku se systemovym odesilatelem pro e-maily
 * @return string
 */
function _sysMailHeader()
{
    if (_sysmail === '') return;
    return (_mailerusefrom ? 'From' : 'Reply-To') . ": " . _sysmail . "\n";
}

/**
 * Funkce pro nacteni rozsireni
 * @param string|null $dir vlastni cesta k adresari s rozsirenimi nebo null (= vychozi)
 * @return bool
 */
function _extendLoad($dir = null)
{
    if (_extend_enabled) {
        $extend_dir = (isset($dir) ? $dir : _indexroot . 'plugins/extend/');
        $extend = @opendir($extend_dir);
        if ($extend !== false)
            while (false !== ($item = readdir($extend))) {

                // preskocit soubory a blbosti
                if ($item === '.' || $item === '..' || $item[0] === '.' || !is_dir($extend_dir . $item)) continue;

                // zjistit nazev souboru, detekovat omezeni na web ci administraci
                $fname = $item;
                if (substr($fname, 0, 6) === 'admin.') {
                    if (!_administration) continue;
                    $fname = substr($fname, 6);
                } elseif (substr($item, 0, 4) === 'web.') {
                    if (_administration) continue;
                    $fname = substr($fname, 4);
                }

                // nacist
                if (is_file($extend_script = $extend_dir . $item . '/' . $fname . '.php')) {
                    include $extend_script;
                }

            }

        return true;
    }

    return false;
}

/**
 * Funkce pro praci s extend rozsirenimi
 *
 * Akce     Popis                   $idt                    $arg                        $arg2       Navratova hodnota
 * ===========================================================================================================
 * reg      registrace              udalost/pole udalosti   callback(argumenty)         poradi      -
 * regm     reg. vice callbacku     pole event=>callback    poradi                      -           -
 * call     volani                  udalost                 argumenty                   -           -
 * buffer   ziskani obsahu          udalost                 argumenty + auto output     -           string
 * fetch    ziskani hodnoty         udalost                 argumenty + auto output     vych. hodn. mixed
 *
 * @param string $act identifikator akce
 * @param string|array $idt identifikator udalosti (akce 'reg' podporuje i serii jako pole)
 * @param mixed $arg dle akce, viz vyse
 * @param mixed $arg2 dle akce, viz vyse
 */
function _extend($act, $idt, $arg = null, $arg2 = null)
{
    // registry
    static
        $data = array(), // zaznam: event idt => array(array(0 - callback, 1 - priorita), ...)
        $sort_states = array(), // event_idt => sorted 1/0
        $multi_events = array(), // event idt => array(array(multi_id1, priorita1), ...)
        $multi_data = array(), // multi_id => array(0 - callback, 1 - current_state, 2 - state_count, 3 - state_map(event_idt => state_index)), 4 - args)
        $multi_data_i = 0, // int
        $multi_sort_states = array() // event_idt => sorted 1/0
    ;

    // akce
    if ('reg' === $act) {

        // registrace
        $pri = (isset($arg2) ? $arg2 : 0);
        if (is_array($idt)) {

            // serie eventu
            $multi_data[$multi_data_i] = array($arg, 0, 0, array(), array());
            for ($i = 0; isset($idt[$i]); ++$i) {
                $multi_events[$idt[$i]][] = array($multi_data_i, $pri);
                $multi_data[$multi_data_i][3][$idt[$i]] = $i;
                $multi_sort_states[$idt[$i]] = false;
            }
            $multi_data[$multi_data_i][2] = $i;
            ++$multi_data_i;

        } else {

            // jediny event
            $data[$idt][] = array($arg, $pri);
            $sort_states[$idt] = false;

        }
    } elseif ('regm' === $act) {

        // registrace vice callbacku
        $pri = (isset($arg) ? $arg : 0);
        foreach ($idt as $event => $callback) {
            $data[$event][] = array($callback, $pri);
            $sort_states[$event] = false;
        }

    } else {

        $has_single_handler = isset($data[$idt]);

        // priprava vystupu
        $output = null;
        switch ($act) {

            case 'buffer':
                $output = '';
                if ($has_single_handler) {
                    if (null === $arg) {
                        $arg = array();
                    }
                    $arg['output'] = &$output;
                }
                break;

            case 'fetch':
                if ($has_single_handler) {
                    $output = $arg2;
                    if (null === $arg) {
                        $arg = array();
                    }
                    $arg['output'] = &$output;
                }
                break;

        }

        // jediny event
        if ($has_single_handler) {

            // seradit?
            if (!$sort_states[$idt]) {
                usort($data[$idt], '_extend_reg_sort');
               $sort_states[$idt] = true;
            }

            // zpracovat
            for ($i = 0; isset($data[$idt][$i]); ++$i) {
                $call = call_user_func($data[$idt][$i][0], $arg);
                if (false === $call) {
                    break;
                }
            }

        }

        // serie eventu
        if (isset($multi_events[$idt]) && 'call' === $act) {

            // seradit?
            if (!$multi_sort_states[$idt]) {
                usort($multi_events[$idt], '_extend_reg_sort');
                $multi_sort_states[$idt] = true;
            }

            // zpracovat
            for ($i = 0; isset($multi_events[$idt][$i]); ++$i) {
                $ii = $multi_events[$idt][$i][0];
                if ($multi_data[$ii][1] === $multi_data[$ii][3][$idt]) {
                    ++$multi_data[$ii][1];
                    $multi_data[$ii][4][] = $arg;
                    if ($multi_data[$ii][2] === $multi_data[$ii][1]) {
                        $call = call_user_func_array($multi_data[$ii][0], $multi_data[$ii][4]);
                        $multi_data[$ii][1] = 0;
                        $multi_data[$ii][4] = array();
                        if (false === $call) break;
                    }
                }
            }
        }

        // vystup
        return $output;

    }
}

/**
 * @internal
 */
function _extend_reg_sort($a, $b)
{
    if ($a[1] === $b[1]) return 0;
    if ($a[1] < $b[1]) return 1;
    return -1;
}

/**
 * Sestavit argumenty pro volani _extend (call)
 * @param string &$output reference na promennou vystupu
 * @param array $extra pole s extra daty ci referencemi
 * @return array
 */
function _extendArgs(&$output, $extra = array())
{
    return array('output' => &$output, 'extra' => $extra);
}

/**
 * Nacteni obrazku ze souboru
 *
 * Mozne klice v $limit:
 *
 * filesize     maximalni velikost souboru v bajtech
 * dimensions   max. rozmery ve formatu array(x => max_sirka, y => max_vyska)
 * memory       maximalni procento zbyvajici dostupne pameti, ktere muze byt vyuzito (vychozi je 0.75) a je treba pocitat s +- odchylkou
 *
 * @param string $filepath realna cesta k souboru
 * @param array $limit volby omezeni
 * @param string|null $filename pouzity nazev souboru (pokud se lisi od $filepath)
 * @return array v pripade uspechu array(true, kod, resource, pripona), jinak array(false, kod, zprava, pripona)
 */
function _pictureLoad($filepath, $limit = array(), $filename = null)
{
    // vychozi nastaveni
    static $limit_default = array(
        'filesize' => null,
        'dimensions' => null,
        'memory' => 0.75,
    );

    // vlozeni vychoziho nastaveni
    $limit += $limit_default;

    // proces
    $code = 0;
    do {

        /* --------  kontroly a nacteni  -------- */

        // zjisteni nazvu souboru
        if (null === $filename) {
            $filename = basename($filepath);
        }

        // zjisteni pripony
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // kontrola pripony
        if (!in_array($ext, SL::$imageExt) || !_isSafeFile($filepath) || !_isSafeFile($filename)) {
            // nepovolena pripona
            $code = 1;
            break;
        }

        // kontrola velikosti souboru
        $size = @filesize($filepath);
        if ($size === false) {
            // soubor nenalezen
            $code = 2;
            break;
        }
        if (isset($limit['filesize']) && $size > $limit['filesize']) {
            // prekrocena datova velikost
            $code = 3;
            break;
        }

        // kontrola podpory formatu
        if (!_checkGD($ext)) {
            // nepodporovany format
            $code = 4;
            break;
        }

        // zjisteni informaci o obrazku
        $imageInfo = getimagesize($filepath);
        if (isset($imageInfo['channels'])) {
            $channels = $imageInfo['channels'];
        } else {
            switch ($ext) {
                case 'png': $channels = 4; break;
                default: $channels = 3; break;
            }
        }
        if (false === $imageInfo || 0 == $imageInfo[0] || 0 == $imageInfo[1]) {
            $code = 5;
            break;
        }

        // kontrola dostupne pameti
        if ($memlimit = _phpIniLimit('memory_limit')) {
            $availMem = floor($limit['memory'] * ($memlimit - memory_get_usage()));
            $requiredMem = ceil(($imageInfo[0] * $imageInfo[1] * $imageInfo['bits'] * $channels / 8 + 65536) * 1.65);

            if ($requiredMem > $availMem) {
                // nedostatek pameti
                $code = 5;
                break;
            }
        }

        // nacteni rozmeru
        $x = $imageInfo[0];
        $y = $imageInfo[1];

        // kontrola rozmeru
        if (isset($limit['dimensions']) && ($x > $limit['dimensions']['x'] || $y > $limit['dimensions']['y'])) {
            $code = 6;
            break;
        }

        // pokus o nacteni obrazku
        switch ($ext) {

            case 'jpg':
            case 'jpeg':
                $res = @imagecreatefromjpeg($filepath);
                break;

            case 'png':
                $res = @imagecreatefrompng($filepath);
                break;

            case 'gif':
                $res = @imagecreatefromgif ($filepath);
                break;

        }

        // kontrola nacteni
        if (!is_resource($res)) {
            $code = 5;
            break;
        }

        // vsechno je ok, vratit vysledek
        return array(true, $code, $res, $ext);

    } while (false);

    // chyba
    global $_lang;
    $output = array(false, $code, $_lang['pic.load.' . $code], $ext);

    // uprava vystupu
    switch ($code) {

        case 3:
            $output[2] = str_replace('*maxkb*', round($limit['filesize'] / 1024), $output[2]);
            break;

        case 5:
            $lastError = error_get_last();
            if (null !== $lastError && !empty($lastError['message'])) {
                $output[2] .= " {$_lang['global.error']}: " . _htmlStr($lastError['message']);
            }
            break;

        case 6:
            $output[2] = str_replace(array('*maxw*', '*maxh*'), array($limit['dimensions']['x'], $limit['dimensions']['y']), $output[2]);
            break;

    }

    // navrat
    return $output;
}

/**
 * Zmena velikosti obrazku
 *
 * Mozne klice v $opt:
 * -----------------------------------------------------
 *
 * x                pozadovana sirka obrazku (nepovinne pokud je uvedeno y)
 * y                pozadovana vyska obrazku (nepovinne pokud je uvedeno x)
 * mode             mod zpracovani - 'zoom', 'fit' nebo 'none' (zadna operace)
 * keep_smaller     zachovat mensi obrazky 1/0
 * bgcolor          barva pozadi ve formatu array(r, g, b) [pouze mod 'fit']
 * pad              doplnit rozmer obrazku prazdnym mistem 1/0, vychozi = 1 [pouze mod 'fit']
 *
 * trans            zachovat pruhlednost obrazku (ignoruje klic bgcolor) 1/0
 * trans_format     format obrazku (png/gif), vyzadovano pro trans = 1
 *
 * @param resource $res resource obrazku
 * @param array $opt pole s volbami procesu
 * @param array|null $size pole ve formatu array(sirka, vyska) nebo null (= bude nacteno)
 * @return array v pripade uspechu array(true, kod, resource, no_change 1/0), jinak array(false, kod, zprava)
 */
function _pictureResize($res, $opt, $size = null)
{
    global $_lang;

    // zadna operace?
    if ('none' === $opt['mode']) {
        return array(true, 0, $res, true);
    }

    // zjisteni rozmeru
    if (!isset($size)) {
        $x = imagesx($res);
        $y = imagesy($res);
    } else {
        list($x, $y) = $size;
    }

    // rozmery kompatibilita 0 => null
    if (isset($opt['x']) && 0 == $opt['x']) {
        $opt['x'] = null;
    }
    if (isset($opt['y']) && 0 == $opt['y']) {
        $opt['y'] = null;
    }

    // kontrola parametru
    if (!isset($opt['x']) && !isset($opt['y']) || isset($opt['y']) && $opt['y'] < 1 || isset($opt['x']) && $opt['x'] < 1) {
        return array(false, 2, $_lang['pic.resize.2']);
    }

    // proporcionalni dopocet chybejiciho rozmeru
    if (!isset($opt['x'])) {
        $opt['x'] = max(round($x / $y * $opt['y']), 1);
    } elseif (!isset($opt['y'])) {
        $opt['y'] = max(round($opt['x'] / ($x / $y)), 1);
    }

    // povolit mensi rozmer?
    if (isset($opt['keep_smaller']) && $opt['keep_smaller'] === true && $x < $opt['x'] && $y < $opt['y']) {
        return array(true, 0, $res, true);
    }

    // nezpracovavat stejny rozmer
    if ($x == $opt['x'] && $y == $opt['y']) {
        return array(true, 0, $res, true);
    }

    // vypocet novych rozmeru
    $newx = $opt['x'];
    $newy = max(round($opt['x'] / ($x / $y)), 1);

    // volba finalnich rozmeru
    $xoff = $yoff = 0;
    if ($opt['mode'] === 'zoom') {
        if ($newy < $opt['y']) {
            $newx = max(round($x / $y * $opt['y']), 1);
            $newy = $opt['y'];
            $xoff = round(($opt['x'] - $newx) / 2);
        } elseif ($newy > $opt['y']) {
            $yoff = round(($opt['y'] - $newy) / 2);
        }
    } elseif ($opt['mode'] === 'fit') {
        if ($newy < $opt['y']) {
            if (!isset($opt['pad']) || $opt['pad'] === true) $yoff = round(($opt['y'] - $newy) / 2);
            else $opt['y'] = $newy;
        } elseif ($newy > $opt['y']) {
            $newy = $opt['y'];
            $newx = max(round($x / $y * $opt['y']), 1);
            if (!isset($opt['pad']) || $opt['pad'] === true) $xoff = round(($opt['x'] - $newx) / 2);
            else $opt['x'] = $newx;
        }
    } else {
        return array(false, 1, $_lang['pic.resize.0']);
    }

    // priprava obrazku
    $output = imagecreatetruecolor($opt['x'], $opt['y']);

    // prekresleni pozadi
    if (isset($opt['trans'], $opt['trans_format']) && $opt['trans']) {

        // pruhledne
        $trans = imagecolortransparent($res);
        if ($trans >= 0) {
            $transColor = imagecolorsforindex($output, $trans);
            $transColorAl = imagecolorallocate($output, $transColor['red'], $transColor['green'], $transColor['blue']);
            imagefill($output, 0, 0, $transColorAl);
            imagecolortransparent($output, $transColorAl);
        } elseif ('png' === $opt['trans_format']) {
            imagealphablending($output, false);
            $transColorAl = imagecolorallocatealpha($output, 0, 0, 0, 127);
            imagefill($output, 0, 0, $transColorAl);
            imagesavealpha($output, true);
        }

    } else {

        // nepruhledne
        if ($opt['mode'] === 'fit' && isset($opt['bgcolor'])) {
            $bgc = imagecolorallocate($output, $opt['bgcolor'][0], $opt['bgcolor'][1], $opt['bgcolor'][2]);
            imagefilledrectangle($output, 0, 0, $opt['x'], $opt['y'], $bgc);
        }

    }

    // zmena rozmeru a navrat
    if (imagecopyresampled($output, $res, $xoff, $yoff, 0, 0, $newx, $newy, $x, $y)) {
        return array(true, 0, $output, false);
    }
    imagedestroy($output);

    return array(false, 2, $_lang['pic.resize.2']);
}

/**
 * Ulozit obrazek do uloziste
 * @param resource $res resource obrazku
 * @param string $path cesta k adresari uloziste vcetne lomitka
 * @param string|null subcesta v adresari uloziste vcetne lomitka nebo null
 * @param string $format pozadovany format obrazku
 * @param int $jpg_quality kvalita JPG obrazku
 * @param string|null $uid UID obrazku nebo null (= vygeneruje se automaticky)
 * @return array v pripade uspechu array(true, kod, filepath, uid), jinak array(false, kod, zprava)
 */
function _pictureStoragePut($res, $path, $home_path, $format, $jpg_quality = 80, $uid = null)
{
    // vygenerovani uid
    if (!isset($uid)) $uid = uniqid('');

    // sestaveni cesty
    if (isset($home_path)) $path .= $home_path;

    // proces
    $code = 0;
    do {

        // kontrola adresare
        if (!is_dir($path) && !@mkdir($path, 0777, true)) {
            $code = 1;
            break;
        }

        // kontrola formatu
        if (!_checkGD($format)) {
            $code = 2;
            break;
        }

        // sestaveni nazvu
        $fname = $path . $uid . '.' . $format;

        // zapsani souboru
        switch ($format) {

            case 'jpg':
            case 'jpeg':
                $write = @imagejpeg($res, $fname, $jpg_quality);
                break;

            case 'png':
                $write = @imagepng($res, $fname);
                break;

            case 'gif':
                $write = @imagegif ($res, $fname);
                break;

        }

        // uspech?
        if ($write) return array(true, $code, $fname, $uid); // jo
        $code = 3; // ne

    } while (false);

    // chyba
    global $_lang;

    return array(false, $code, $_lang['pic.put.' . $code]);
}

/**
 * Ziskat adresu obrazku v ulozisti
 * @param string $path cesta k adresari uloziste vcetne lomitka
 * @param string|null subcesta v adresari uloziste vcetne lomitka nebo null
 * @param string $uid UID obrazku
 * @param string $format format ulozeneho obrazku
 * @return string
 */
function _pictureStorageGet($path, $home_path, $uid, $format)
{
    return $path . (isset($home_path) ? $home_path : '') . $uid . '.' . $format;
}

/**
 * Zpracovat obrazek
 *
 * Dostupne klice v $args :
 * -----------------------------------------------------
 *
 * Nacteni a zpracovani
 *
 *  file_path       realna cesta k souboru s obrazkem
 *  [file_name]     vlastni nazev souboru pro detekci formatu (jinak se pouzije file_path)
 *  [limit]         omezeni pri nacitani obrazku, viz _pictureLoad() - $limit
 *  [resize]        pole s argumenty pro zmenu velikosti obrazku, viz _pictureResize()
 *
 * Ukladani
 *
 *  target_path         cesta do adresare, kam ma byt obrazek ulozen, s lomitkem na konci!!
 *  [target_format]     cilovy format (JPG/JPEG, PNG, GIF), pokud neni uveden, je zachovan stavajici format
 *  [target_uid]        vlastni unikatni identifikator, jinak bude vygenerovan automaticky
 *  [jpg_quality]       kvalita pro ukladani JPG/JPEG formatu
 *  [target_callback]   callback(resource, format, args) pro zpracovani vysledne resource
 *                       - pokud vrati jinou hodnotu nez null, obrazek nebude ulozen
 *                         a funkce vrati tuto hodnotu
 *
 * Ostatni
 *
 *  [destroy]   pokud je nastaveno na false, resource obrazku neni po ukonceni volani znicena
 *
 * @param array $args argumenty zpracovani
 * @param string &$error promenna pro ulozeni chybove hlasky v pripade neuspechu
 * @param string &$format promenna pro ulozeni formatu nacteneho obrazku
 * @param resource|null &$resource promenna pro ulozeni resource vysledneho obrazku (pouze pokud 'destroy' = false)
 * @return string|bool|mixed vraci UID ulozeneho obrazku, false pri neuspechu nebo vystup target_callback, neni-li null
 */
function _pictureProcess(array $args, &$error = null, &$format = null, &$resource = null)
{
    try {

        // nacteni
        $load = _pictureLoad(
            $args['file_path'],
            isset($args['limit']) ? $args['limit'] : array(),
            isset($args['file_name']) ? $args['file_name'] : null
        );
        if (!$load[0]) {
            throw new RuntimeException($load[2]);
        }
        $format = $load[3];

        // zmena velikosti
        if (isset($args['resize'])) {

            $resize = _pictureResize($load[2], $args['resize']);
            if (!$resize[0]) {
                throw new RuntimeException($resize[2]);
            }

            // nahrada puvodni resource
            if (!$resize[3]) {
                // resource se zmenila
                imagedestroy($load[2]);
                $load[2] = $resize[2];
            }

            $resize = null;

        }

        // callback pred ulozenim
        if (isset($args['target_callback'])) {
            $targetCallbackResult = call_user_func($args['target_callback'], $load[2], $load[3], $args);
            if (null !== $targetCallbackResult) {

                // smazani obrazku z pameti
                if (!isset($args['destroy']) || $args['destroy']) {
                    imagedestroy($load[2]);
                    $resource = null;
                } else {
                    $resource = $load[2];
                }

                // navrat vystupu callbacku
                return $targetCallbackResult;

            }
        }

        // ulozeni
        $put = _pictureStoragePut(
            $load[2],
            $args['target_path'],
            null,
            isset($args['target_format']) ? $args['target_format'] : $load[3],
            isset($args['jpg_quality']) ? $args['jpg_quality'] : 80,
            isset($args['target_uid']) ? $args['target_uid'] : null
        );
        if (!$put[0]) {
            throw new RuntimeException($put[2]);
        }

        // smazani obrazku z pameti
        if (!isset($args['destroy']) || $args['destroy']) {
            imagedestroy($load[2]);
            $resource = null;
        } else {
            $resource = $load[2];
        }

        // vratit UID
        return $put[3];

    } catch (RuntimeException $e) {
        $error = $e->getMessage();

        return false;
    }
}

/**
 * Vygenerovat cachovanou miniaturu obrazku
 * @param string $source cesta ke zdrojovemu obrazku
 * @param array $resize_opts volby pro zmenu velikosti, viz _pictureResize() (mode je prednastaven na zoom)
 * @param $use_error_image vratit chybovy obrazek pri neuspechu namisto false
 * @param string &$error promenna, kam bude ulozena pripadna chybova hlaska
 * @return string|bool cesta k miniature nebo chybovemu obrazku nebo false pri neuspechu
 */
function _pictureThumb($source, array $resize_opts, $use_error_image = true, &$error = null)
{
    // zjistit priponu
    $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION));
    if (!in_array($ext, SL::$imageExt)) {
        return $use_error_image ? SL::$imageError : false;
    }

    // sestavit cestu do adresare
    $path = _indexroot . 'pictures/thumb/';

    // extend pro nastaveni velikosti
    _extend('call', 'sys.thumb.resize', array('options' => &$resize_opts));

    // vychozi nastaveni zmenseni
    $resize_opts += array(
        'mode' => 'zoom',
        'trans' => 'png' === $ext || 'gif' === $ext,
        'trans_format' => $ext,
    );

    // normalizovani nastaveni zmenseni
    if (!isset($resize_opts['x']) || 0 == $resize_opts['x']) {
        $resize_opts['x'] = null;
    } else {
         $resize_opts['x'] = intval($resize_opts['x']);
    }
    if (!isset($resize_opts['y']) || 0 == $resize_opts['y']) {
        $resize_opts['y'] = null;
    } else {
        $resize_opts['y'] = intval($resize_opts['y']);
    }

    // vygenerovat hash
    ksort($resize_opts);
    if (isset($resize_opts['bgcolor'])) {
        ksort($resize_opts['bgcolor']);
    }
    $hash = md5(realpath($source) . '$' . serialize($resize_opts));

    // sestavit cestu k obrazku
    $image_path = $path . $hash . '.' . $ext;

    // zkontrolovat cache
    if (file_exists($image_path)) {

        // obrazek jiz existuje
        if (time() - filemtime($image_path) >= _thumb_touch_threshold) {
            touch($image_path);
        }
        return $image_path;

    } else {

        // obrazek neexistuje
        $options = array(
            'file_path' => $source,
            'resize' => $resize_opts,
            'target_path' => $path,
            'target_uid' => $hash,
        );

        // extend
        _extend('call', 'sys.thumb.process', array('options' => &$options));

        // vygenerovat
        if (false !== _pictureProcess($options, $error)) {
            // uspech
            return $image_path;
        } else {
            // chyba
            return $use_error_image ? SL::$imageError : false;
        }

    }
}

/**
 * Smazat nepouzivane miniatury
 * @param int $threshold minimalni doba v sekundach od posledniho vyzadani miniatury
 */
function _pictureThumbClean($threshold)
{
    $dir = _indexroot . 'pictures/thumb/';
    $handle = opendir($dir);
    while ($item = readdir($handle)) {
        if (
            '.' !== $item
            && '..' !== $item
            && is_file($dir . $item)
            && in_array(strtolower(pathinfo($item, PATHINFO_EXTENSION)), SL::$imageExt)
            && time() - filemtime($dir . $item) > $threshold
        ) {
            unlink($dir . $item);
        }
    }
    closedir($handle);
}

/**
 * Sestavit kod skryteho inputu pro XSRF ochranu
 * @return string
 */
function _xsrfProtect()
{
    return '<input type="hidden" name="_security_token" value="' . _xsrfToken() . '" />';
}

/**
 * Pridat XSRF ochranu do URL
 * @param string $url adresa
 * @param bool $entity oddelit argument pomoci &amp; namisto & 1/0
 * @return string
 */
function _xsrfLink($url, $entity = true)
{
    return _addGetToLink($url, '_security_token=' . urlencode(_xsrfToken()), $entity);
}

/**
 * Vygenerovat XSRF token
 * @param bool $forCheck token je ziskavan pro kontrolu (je bran ohled na situaci, ze mohlo zrovna dojit ke zmene ID session) 1/0
 * @return string
 */
function _xsrfToken($forCheck = false)
{
    // cache tokenu
    static $tokens = array(null, null);

    // typ tokenu (aktualni ci pro kontrolu)
    $type = ($forCheck ? 1 : 0);

    // vygenerovat token
    if (null === $tokens[$type]) {

        // zjistit ID session
        if (defined('_no_session')) {
            // session je deaktivovana
            $sessionId = 'none';
        } elseif ($forCheck && defined('_session_regenerate')) {
            // ID session bylo prave pregenerovane
            $sessionId = _session_old_id;
        } else {
            // ID aktualni session
            $sessionId = session_id();
            if ('' === $sessionId) {
                $sessionId = 'none';
            }
        }

        // vygenerovat token
        $tokens[$type] = _md5HMAC($sessionId, _sessionprefix);

    }

    // vystup
    return $tokens[$type];
}

/**
 * Zkontrolovat XSRF token
 * @param bool $get zkontrolovat token v $_GET namisto $_POST 1/0
 * @return bool
 */
function _xsrfCheck($get = false)
{
    // determine data source variable
    if ($get) $tvar = '_GET';
    else $tvar = '_POST';

    // load used token
    if (isset($GLOBALS[$tvar]['_security_token'])) {
        $test = @strval($GLOBALS[$tvar]['_security_token']);
        unset($GLOBALS[$tvar]['_security_token']);
    } else {
        $test = null;
    }

    // check
    if (null !== $test && _xsrfToken(true) === $test) {
        return true;
    }

    return false;
}

/**
 * Zobrazit IP adresu bez posledni sekvence
 * @param string $ip ip adresa
 * @param string $repl retezec, kterym se ma nahradit posledni sekvence
 * @return string
 */
function _showIP($ip, $repl = 'x')
{
    if (_loginright_group == 1) return $ip; // hlavni administratori vidi vzdy puvodni IP
    return substr($ip, 0, strrpos($ip, '.') + 1) . $repl;
}

/**
 * Ziskat kod jazyka dle seznamu podporovanych
 * @param array $langs pole s jazyky kde klicem je identifikator jazyka ($GLOBALS['_lang']['main.languagespecification'])
 * @return string pokud neni aktualni jazyk podporovan pouzije se aktualni polozka z pole
 */
function _getLang($langs)
{
    if (!isset($langs[$current = $GLOBALS['_lang']['main.languagespecification']])) return key($langs);
    return $current;
}

/**
 * Nacist jazykovy soubor
 * @param string $dir cesta k adresari s jazykovymi soubory (bez lomitka)
 * @param array $langs pole s moznymi jazykovymi mutacemi
 * @return mixed vystup includovaneho souboru
 */
function _loadLang($dir, $langs)
{
    if (!isset($langs[$lang = $GLOBALS['_lang']['main.languagespecification']])) $lang = current($langs);
    return include $dir . DIRECTORY_SEPARATOR . "lang.{$lang}.php";
}

/**
 * Wrapper funkce mail umoznujici odchyceni rozsirenim
 * @param string $to prijemce
 * @param string $subject predmet (automaticky formatovan jako UTF-8)
 * @param string $message zprava
 * @param string $additional_headers extra hlavicky
 * @return bool
 */
function _mail($to, $subject, $message, $additional_headers = '')
{
    // plugin
    $handled = false;
    _extend('call', 'sys.mail', array('handled' => &$handled, 'to' => $to, 'subject' => $subject, 'message' => $message, 'headers' => $additional_headers));
    if ($handled) return true; // odchyceno rozsirenim

    // predmet
    $subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    // odeslani
    return @mail($to, $subject, $message, $additional_headers);
}

/**
 * Zaregistrovat kolekci jazykovych souboru pro dynamicke nacteni
 * @param string $key pozadovany nazev klice v $_lang promenne
 * @param string $dir cesta k adresari s preklady vcetne lomitka na konci
 * @param array|null $list seznam dostupnych lokalizaci (zamezi nutne kontrole pres file_exists)
 * @return LangPack
 */
function _registerLangPack($key, $dir, array $list = null)
{
    return $GLOBALS['_lang'][$key] = new LangPack($key, $dir, $list);
}
