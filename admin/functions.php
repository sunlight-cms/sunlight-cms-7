<?php

/**
 * [ADMIN] Navratit kod modulu pro wysiwyg
 * @return string|null
 */
function _admin_wysiwyg()
{
    if (_wysiwyg and _loginwysiwyg) {
        ob_start();
        @include(_indexroot . "admin/modules/wysiwyg.php");
        $content = ob_get_contents();
        ob_end_clean();

        return $content;
    }
}

/**
 * [ADMIN] Ziskat pole s druhy stranek
 * @return array
 */
function _admin_getTypeArray()
{
    return array(
        1 => "section",
        2 => "category",
        3 => "book",
        4 => "separator",
        5 => "gallery",
        6 => "link",
        7 => "intersection",
        8 => "forum",
        9 => "pluginpage",
        );
}
$type_array = _admin_getTypeArray();

/**
 * [ADMIN] Ziskat pole s daty plugin stranek
 * @return array
 */
function _admin_getPluginPageInfos()
{
    static $cache;
    if (null === $cache) {
        $cache = array();
        _extend('call', 'ppage.reg', array('infos' => &$cache));
    }

    return $cache;
}

/**
 * [ADMIN] Sestavit kod poznamky
 * @param string $str zprava
 * @param bool $no_gray nepridavat tridu "note" 1/0
 * @param string|null $icon nazev ikony nebo null (= 'note')
 * @return string
 */
function _admin_smallNote($str, $no_gray = false, $icon = null)
{
    return "<p" . ($no_gray ? '' : ' class="note"') . "><img src='images/icons/" . (isset($icon) ? $icon : 'note') . ".png' alt='note' class='icon' />" . $str . "</p>";
}

/**
 * [ADMIN] Sestavit cast sql dotazu pro pristup k ankete - 'where'
 * @param bool $csep oddelit SQL dotaz vyrazem ' AND ' zleva 1/0
 * @return string
 */
function _admin_pollAccess($csep = true)
{
    if ($csep) {
        $csep = " AND ";
    } else {
        $csep = "";
    }

    return ((!_loginright_adminallart) ? $csep . "author=" . _loginid : $csep . "(author=" . _loginid . " OR (SELECT level FROM `" . _mysql_prefix . "-groups` WHERE id=(SELECT `group` FROM `" . _mysql_prefix . "-users` WHERE id=`" . _mysql_prefix . "-polls`.author))<" . _loginright_level . ")");
}

/**
 * [ADMIN] Sestavit cast sql dotazu pro pristup k clanku - 'where'
 * @param string|null $alias alias tabulky clanku nebo null
 * @return string
 */
function _admin_artAccess($alias = '')
{
    if ('' !== $alias) $alias .= '.';
    if (_loginright_adminallart) {
        return " AND (" . $alias . "author=" . _loginid . " OR (SELECT level FROM `" . _mysql_prefix . "-groups` WHERE id=(SELECT `group` FROM `" . _mysql_prefix . "-users` WHERE id=" . (('' === $alias) ? "`" . _mysql_prefix . "-articles`." : $alias) . "author))<" . _loginright_level . ")";
    } else {
        return " AND " . $alias . "author=" . _loginid;
    }
}

/**
 * [ADMIN] Sestavit odkaz na clanek ve vypisu
 * @param array $art data clanku vcetne cat_title_seo
 * @param bool $ucnote zobrazovat poznamku o neschvaleni 1/0
 * @return string
 */
function _admin_articleEditLink($art, $ucnote = true)
{
    global $_lang;
    $output = "";

    // trida
    $class = "";
    if ($art['visible'] == 0 and $art['public'] == 1) {
        $class = " class='invisible'";
    }
    if ($art['visible'] == 1 and $art['public'] == 0) {
        $class = " class='notpublic'";
    }
    if ($art['visible'] == 0 and $art['public'] == 0) {
        $class = " class='invisible-notpublic'";
    }

    // odkaz
    $output .= "<a href='" . _indexroot . _linkArticle($art['id'], $art['title_seo'], $art['cat_title_seo']) . "' target='_blank'" . $class . ">";
    if ($art['time'] <= time()) {
        $output .= "<strong>";
    }
    $output .= $art['title'];
    if ($art['time'] <= time()) {
        $output .= "</strong>";
    }
    $output .= "</a>";

    // poznamka o neschvaleni
    if ($art['confirmed'] != 1 and $ucnote) {
        $output .= "&nbsp;&nbsp;<small>(" . $_lang['global.unconfirmed'] . ")</small>";
    }

    return $output;
}

/**
 * [ADMIN] Sestavit <select> pro vyber stranky
 * @param string $name nazev selectu
 * @param int|null $typ stranky nebo null (= vsechny)
 * @param int $selected id zvolene stranky
 * @param bool $allowempty povolit vyber zadne polozky (-1) 1/0
 * @param string|null $emptycustomcaption vlastni popisek zadne polozky nebo null (= vychozi)
 * @param int|null $maxlength maximalni delka zobrazeneho titulku stranky (null = deaktivovat)
 * @return string
 */
function _admin_rootSelect($name, $type, $selected, $allowempty, $emptycustomcaption = null, $maxlength = 22)
{
    global $_lang;
    $return = "<select name='" . $name . "' class='ae-artselect'>\n";
    $items = DB::query("SELECT id,title,type FROM `" . _mysql_prefix . "-root` WHERE " . (isset($type) ? "(type=" . $type . " OR type=7)" : 'type!=4') . " AND intersection=-1 ORDER BY ord");
    if (DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-root`" . (isset($type) ? " WHERE type=" . $type : '')), 0) != 0) {
        if ($allowempty) {
            if ($emptycustomcaption == null) {
                $emptycustomcaption = $_lang['admin.content.form.category.none'];
            }
            $return .= "<option value='-1' class='special'>" . $emptycustomcaption . "</option>";
        }
        while ($item = DB::row($items)) {
            if ($item['type'] != 7 || $type == 7) {
                if ($item['id'] == $selected) {
                    $sel = " selected='selected'";
                } else {
                    $sel = "";
                }
                $return .= "<option value='" . $item['id'] . "'" . $sel . ">" . _cutStr($item['title'], $maxlength) . "</option>\n";
            }
            if ($item['type'] == 7 && $type != 7) {
                $iitems = DB::query("SELECT id,title,type FROM `" . _mysql_prefix . "-root` WHERE " . (isset($type) ? "type=" . $type . "" : 'type!=4') . " AND intersection=" . $item['id'] . " ORDER BY ord");
                if (!isset($type) || DB::size($iitems) != 0) {
                    $return .= "<optgroup label='" . $item['title'] . "'>\n";
                    if (!isset($type)) $return .= '<option value=\'' . $item['id'] . '\'' . (($item['id'] == $selected) ? ' selected=\'selected\'' : '') . ' class=\'special\'>' . $_lang['admin.content.form.thisintersec'] . "</option>\n";
                    while ($iitem = DB::row($iitems)) {
                        if ($iitem['id'] == $selected) {
                            $sel = " selected='selected'";
                        } else {
                            $sel = "";
                        }
                        $return .= "<option value='" . $iitem['id'] . "'" . $sel . ">" . _cutStr($iitem['title'], 22) . "</option>\n";
                    }
                    $return .= "</optgroup>\n";
                }
            }
        }
    } else {
        $return .= "\n<option value='-1'>" . $_lang['global.nokit'] . "</option>\n";
    }
    $return .= "\n</select>\n";

    return $return;
}

/**
 * [ADMIN] Sestavit <select> pro vyber uzivatele/skupiny
 * @param string $name nazev selectu
 * @param int $selected id zvoleneho uzivatele
 * @param string $gcond SQL podminka pro zarazeni skupiny
 * @param string|null $class trida selectu nebo null
 * @param string|null $extraoption popisek extra volby (-1) nebo null (= deaktivovano)
 * @param bool $groupmode vybirat pouze cele skupiny 1/0
 * @param int|null $multiple povolit vyber vice polozek (size = $multiple) nebo null (= deaktivovano)
 * @return string
 */
function _admin_authorSelect($name, $selected, $gcond, $class = null, $extraoption = null, $groupmode = false, $multiple = null)
{
    if ($class != null) {
        $class = " class='" . $class . "'";
    } else {
        $class = "";
    }
    if ($multiple != null) {
        $multiple = " multiple='multiple' size='" . $multiple . "'";
        $name .= "[]";
    } else {
        $multiple = "";
    }
    $return = "<select name='" . $name . "'" . $class . $multiple . ">";
    $query = DB::query("SELECT id,title,level FROM `" . _mysql_prefix . "-groups` WHERE " . $gcond . " AND id!=2 ORDER BY level DESC");
    if ($extraoption != null) {
        $return .= "<option value='-1' class='special'>" . $extraoption . "</option>";
    }

    if (!$groupmode) {
        while ($item = DB::row($query)) {
            $users = DB::query("SELECT id,username,publicname FROM `" . _mysql_prefix . "-users` WHERE `group`=" . $item['id'] . " AND (" . $item['level'] . "<" . _loginright_level . " OR id=" . _loginid . ") ORDER BY id");
            if (DB::size($users) != 0) {
                $return .= "<optgroup label='" . $item['title'] . "'>";
                while ($user = DB::row($users)) {
                    if ($selected == $user['id']) {
                        $sel = " selected='selected'";
                    } else {
                        $sel = "";
                    }
                    $return .= "<option value='" . $user['id'] . "'" . $sel . ">" . $user[('' !== $user['publicname']) ? 'publicname' : 'username'] . "</option>\n";
                }
                $return .= "</optgroup>";
            }
        }
    } else {
        while ($item = DB::row($query)) {
            if ($selected == $item['id']) {
                $sel = " selected='selected'";
            } else {
                $sel = "";
            }
            $return .= "<option value='" . $item['id'] . "'" . $sel . ">" . $item['title'] . " (" . DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-users` WHERE `group`=" . $item['id']), 0) . ")</option>\n";
        }
    }

    $return .= "</select>";

    return $return;
}

/**
 * [ADMIN] Smazat obrazky z uloziste galerie
 * @param string $sql_cond SQL podminka pro vyber obrazku
 */
function _tmpGalStorageCleanOnDel($sql_cond)
{
    $q = DB::query("SELECT full,(SELECT COUNT(id) FROM `" . _mysql_prefix . "-images` WHERE full=toptable.full) AS counter FROM `" . _mysql_prefix . "-images` AS toptable WHERE in_storage=1 AND (" . $sql_cond . ") HAVING counter=1");
    while($r = DB::row($q)) @unlink(_indexroot . $r['full']);
}

/**
 * [ADMIN] Zjisteni, zda ma byt schema tmave
 * @return bool
 */
function _admin_schemeIsDark()
{
    if (_adminscheme_mode == 0) return false; // vzdy svetle
    elseif (_adminscheme_mode == 1) return true; // vzdy tmave
    else {
        // podle zapadu a vychodu slunce
        $isday = _isDayTime();
        if ($isday === false) return true;
        return false;
    }
}
