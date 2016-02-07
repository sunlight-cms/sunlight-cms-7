<?php

/**
 * Hromadne boxu danych sloupcu pro usetreni SQL dotazu
 * @param array $columns pole sloupcu, ktere se maji nacist
 */
function _templateBoxesPreload(array $columns)
{
    SL::$registry['template_columns'] = array();
    foreach ($columns as $column) {
        SL::$registry['template_columns'][$column] = array();
    }
    $query = DB::query($s = 'SELECT title,content,class,`column` FROM `' . _mysql_prefix . '-boxes` WHERE visible=1' . (_loginindicator ? '' : ' AND public=1') . ' AND `column` IN(' . DB::val($columns, true) . ')' . ' ORDER BY ord');
    while ($item = DB::row($query)) {
        SL::$registry['template_columns'][$item['column']][] = $item;
    }
    DB::free($query);
}

/**
 * Vypis boxu daneho sloupce
 * @param string $column nazev sloupce
 * @param bool $return vratit misto vypisu 1/0
 * @return string|null
 */
function _templateBoxes($column = 1, $return = false)
{
    $output = "\n";

    if (!_notpublicsite or _loginindicator) {

        // nacist boxy
        if (isset(SL::$registry['template_columns'][$column])) {
            $boxes = SL::$registry['template_columns'][$column];
        } else {
            $boxes = array();
            $query = DB::query('SELECT title,content,class FROM `' . _mysql_prefix . '-boxes` WHERE visible=1 AND `column`=' . DB::val($column) . (_loginindicator ? '' : ' AND `public`=1') . ' ORDER BY ord');
            while ($item = DB::row($query)) {
                $boxes[] = $item;
            }
            DB::free($query);
        }

        // extend
        $extendOutput = _extend('buffer', 'tpl.boxes', array('boxes' => $boxes, 'column' => $column));
        if ('' !== $extendOutput) {
            return $extendOutput;
        }

        // obsah
        if (_template_boxes_parent != "") $output .= "<" . _template_boxes_parent . ">\n";
        foreach ($boxes as $item) {

            // kod titulku
            if ($item['title'] != "") $title = "<" . _template_boxes_title . " class='box-title'>" . $item['title'] . "</" . _template_boxes_title . ">\n";
            else $title = "";

            // titulek venku
            if (_template_boxes_title_inside == 0 and $title != "") $output .= $title;

            // starttag polozky
            if (_template_boxes_item != "") $output .= "<" . _template_boxes_item . " class='box-item" . (isset($item['class']) ? ' ' . $item['class'] : '') . "'>\n";

            // titulek vevnitr
            if (_template_boxes_title_inside == 1 and $title != "") $output .= $title;

            // obsah
            $output .= _parseHCM($item['content']);

            // endtag polozky
            if (_template_boxes_item != "") $output .= "\n</" . _template_boxes_item . ">";

            // spodek boxu
            if (_template_boxes_bottom == 1) $output .= "<" . _template_boxes_item . " class='box-bottom'></" . _template_boxes_item . ">\n\n";
            else $output .= "\n\n";

        }
        if (_template_boxes_parent != "") $output .= "</" . _template_boxes_parent . ">\n";

    }

    // vypis vysledku
    if ($return) return $output;
    echo $output;
}

/**
 * Vypis obsahu
 * @param bool $return vratit namisto vypisu 1/0
 * @return string|null
 */
function _templateContent($return = false)
{
    // ziskani obsahu
    $output = '';
    $output .= _extend('buffer', 'tpl.content.before');
    $output .= _indexOutput_content;
    $output .= _extend('buffer', 'tpl.content.after');

    // navraceni nebo vypsani
    if (!$return) echo $output;
    else return $output;
}

/**
 * Vypis HTML hlavicky
 */
function _templateHead()
{
    // titulek
    $title = null;
    _extend('call', 'tpl.title', array('title' => &$title, 'head' => true));
    if (!isset($title)) {
        if (_titletype == 1) $title = _title . ' ' . _titleseparator . ' ' . _indexOutput_title;
        else $title = _indexOutput_title . ' ' . _titleseparator . ' ' . _title;
    }

    global $_lang;
    if (_modrewrite) echo "<base href=\"" . _url . "/\" />\n";
    echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="keywords" content="' . (defined('_indexOutput_keywords') ? _indexOutput_keywords : _keywords) . '" />
<meta name="description" content="' . (defined('_indexOutput_description') ? _indexOutput_description : _description) . '" />' . ((_author !== '') ? '
<meta name="author" content="' . _author . '" />' : '') . '
<meta name="generator" content="SunLight CMS ' . _systemversion . ' ' . SL::$states[_systemstate] . _systemstate_revision . '" />
<meta name="robots" content="index, follow" />' . _extend('buffer', 'tpl.head.meta') . '
<link href="' . _indexroot . 'plugins/templates/' . _template . '/style/system.css?' . _cacheid . '" type="text/css" rel="stylesheet" />
<link href="' . _indexroot . 'plugins/templates/' . _template . '/style/layout.css?' . _cacheid . '" type="text/css" rel="stylesheet" />
<script type="text/javascript">/* <![CDATA[ */var sl_indexroot=\'' . _indexroot . '\';/* ]]> */</script>
<script type="text/javascript" src="' . _indexroot . 'remote/jscript.php?' . _cacheid . '&amp;' . _language . '"></script>';

    echo _extend('buffer', 'tpl.head');

    if (_lightbox) {
        $lightbox = _extend('fetch', 'tpl.lightbox');
        if (null === $lightbox) {
            echo '
<link rel="stylesheet" href="' . _indexroot . 'remote/lightbox/style.css?' . _cacheid . '" type="text/css" media="screen" />
<script type="text/javascript" src="' . _indexroot . 'remote/lightbox/script.js?' . _cacheid . '"></script>';
        } else {
            echo $lightbox;
        }
    }

    if (_rss) {
        echo '
<link rel="alternate" type="application/rss+xml" href="' . _indexroot . 'remote/rss.php?tp=4&amp;id=-1" title="' . $_lang['rss.recentarticles'] . '" />';
    }

    if (_favicon) {
        echo '
<link rel="shortcut icon" href="favicon.ico?' . _cacheid . '" />';
    }

    echo '
<title>' . $title . '</title>
';
}

/**
 * Vypis odkazu motivu
 * @param bool $left_separator oddelit odkazy zleva 1/0
 */
function _templateLinks($left_separator = false)
{
    global $_lang;
    if ($left_separator) {
        echo " " . _template_listinfoseparator . " ";
    }
    echo "<a href='http://sunlight.shira.cz/'>SunLight CMS</a>" . ((!_adminlinkprivate or (_loginindicator and _loginright_administration)) ? " " . _template_listinfoseparator . " <a href='" . _indexroot . "admin/index.php'>" . $_lang['admin.link'] . "</a>" : '');
}

/**
 * Sestavit adresu k obrazku motivu
 * @param string $path subcesta k souboru relativne ke slozce images od aktualniho motivu
 * @return string
 */
function _templateImage($path)
{
    return _indexroot . "plugins/templates/" . _template . "/images/" . $path;
}

/**
 * Sestavit kod menu
 * @param int|null $ord_start minimalni poradove cislo
 * @param int|null $ord_end maximalni poradove cislo
 * @param string $parent_class trida hlavniho tagu menu
 * @return string
 */
function _templateMenu($ord_start = null, $ord_end = null, $parent_class = 'menu')
{
    $output = "";
    if (defined("_indexOutput_pid")) $pid = _indexOutput_pid;
    else $pid = -1;

    if (!_notpublicsite or _loginindicator) {

        // limit
        if ($ord_start === null or $ord_end === null) $ord_limit = $inter_ord_limit = "";
        else {
            $ord_limit = " AND page.ord>=" . intval($ord_start) . " AND page.ord<=" . intval($ord_end);
            $inter_ord_limit = " AND inter.ord>=" . intval($ord_start) . " AND inter.ord<=" . intval($ord_end);
        }

        // nacteni dat
        $tree = array();
        $query = DB::query("SELECT page.id,page.type,page.title,page.title_seo,page.level,page.var1,page.var2,page.intersection FROM `" . _mysql_prefix . "-root` AS page LEFT JOIN `" . _mysql_prefix . "-root` AS inter ON(page.intersection=inter.id) WHERE page.visible=1 AND page.type!=4 AND (inter.id IS NULL" . $ord_limit . " OR inter.var2=1" . $inter_ord_limit . ") ORDER BY page.intersection,page.ord");
        while ($item = DB::row($query)) {
            if ($item['intersection'] == -1) $tree[$item['id']] = $item;
            else {
                if (!isset($tree[$item['intersection']]['children'])) $tree[$item['intersection']]['children'] = array();
                $tree[$item['intersection']]['children'][] = $item;
            }
        }
        DB::free($query);

        // sestaveni kodu
        $output .= "<" . _template_menu_parent . " class='" . $parent_class . "'>\n";
        $counter = 0;
        $last = sizeof($tree) - 1;
        foreach ($tree as $item) {

            // rozsireni, priprava
            $classes = array();
            _extend('call', 'tpl.menu.item', array('item' => &$item, 'classes' => &$classes, 'sub' => false));

            // zpracovani polozky
            if (empty($item['children'])) {

                // stranka
                if ($item['id'] == $pid) $classes[] = 'act';
                if ($item['type'] == 6 and $item['var1'] == 1) {
                    $target = " target='_blank'";
                } else {
                    $target = "";
                }
                $link = "<a href='" . _linkRoot($item['id'], $item['title_seo']) . "'" . $target . ">" . $item['title'] . "</a>";

            } else {

                // polozky rozcestniku
                $icounter = 0;
                $ilast = sizeof($item['children']) - 1;
                $childactive = false;

                $link_sublistitems = '';
                foreach ($item['children'] as $iitem) {
                    _extend('call', 'tpl.menu.item', array('item' => &$iitem, 'sub' => true));
                    $classes[] = 'menu-item-' . str_replace('/', '_', $iitem['title_seo']);
                    if ($iitem['id'] == $pid) {
                        $classes[] = 'act';
                        $childactive = true;
                    }
                    if ($icounter === 0) $classes[] = 'first';
                    if ($icounter !== 0 && $icounter === $ilast) $classes[] = 'last';
                    if ($iitem['type'] == 6 and $iitem['var1'] == 1) {
                        $target = " target='_blank'";
                    } else {
                        $target = "";
                    }
                    $link_sublistitems .= "    <li" . (!empty($classes) ? ' class="' . implode(' ', $classes) . '"' : '') . "><a href='" . _linkRoot($iitem['id'], $iitem['title_seo']) . "'" . $target . ">" . $iitem['title'] . "</a></li>\n";
                    $classes = array();
                    ++$icounter;
                }

                if (!$childactive && $item['id'] == $pid) $childactive = true;
                $classes[] = 'menu-dropdown';
                if ($childactive || $item['id'] == $pid) $classes[] = 'act';

                $link = "<a href='" . _linkRoot($item['id'], $item['title_seo']) . "' class='menu-dropdown-link'>" . $item['title'] . "</a>";
                if ($link_sublistitems !== '') $link .= "\n<ul class='menu-dropdown-list'>
" . $link_sublistitems . "</ul>\n";

            }

            $classes[] = 'menu-item-' . str_replace('/', '_', $item['title_seo']);
            if ($counter === 0) $classes[] = 'first';
            if ($counter !== 0 && $counter === $last) $classes[] = 'last';
            $output .= "<" . _template_menu_child . (!empty($classes) ? ' class="' . implode(' ', $classes) . '"' : '') . ">" . $link . "</" . _template_menu_child . ">\n";
            ++$counter;

        }

        $output .= "</" . _template_menu_parent . ">";

    }

    return $output;
}

/**
 * Vypis titulku aktualni stranky
 * @param bool $return vratit namisto vypisu 1/0
 */
function _templateTitle($return = false)
{
    // overload pluginem
    $title = null;
    _extend('call', 'tpl.title', array('title' => &$title, 'head' => false));
    if (!isset($title)) $title = _indexOutput_title;

    // vypis ci navraceni
    if ($return) return $title;
    echo $title;
}

/**
 * Vypis kodu uzivatelskeho menu
 * @param bool $return navratit namisto vypsani 1/0
 */
function _templateUserMenu($return = false)
{
    global $_lang;

    $output = "";

    if (_template_usermenu_parent != "") $output .= "<" . _template_usermenu_parent . ">\n";

    $extend_args = _extendArgs($output);
    _extend('call', 'tpl.usermenu.first', $extend_args);

    if (!_loginindicator) {
        /*prihlaseni*/
        $output .= _template_usermenu_item_start . "<a href='" . _indexroot . "index.php?m=login&amp;login_form_return=" . urlencode($_SERVER['REQUEST_URI']) . "' class='usermenu-item-login'>" . $_lang['usermenu.login'] . "</a>" . _template_usermenu_item_end . "\n";
        if (_registration) {
            /*registrace*/
            $output .= _template_usermenu_item_start . "<a href='" . _indexroot . "index.php?m=reg' class='usermenu-item-reg'>" . $_lang['usermenu.registration'] . "</a>" . _template_usermenu_item_end . "\n";
        }
    } else {
        /*vzkazy*/
        if (_messages) {
            $messages_count = DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-pm` WHERE (receiver=" . _loginid . " AND receiver_deleted=0 AND receiver_readtime<update_time) OR (sender=" . _loginid . " AND sender_deleted=0 AND sender_readtime<update_time)"), 0);
            if ($messages_count != 0) {
                $messages_count = " [" . $messages_count . "]";
            } else {
                $messages_count = "";
            }
            $output .= _template_usermenu_item_start . "<a href='" . _indexroot . "index.php?m=messages' class='usermenu-item-messages'>" . $_lang['usermenu.messages'] . $messages_count . "</a>" . _template_usermenu_item_end . "\n";
        }
        /*nastaveni*/
        $output .= _template_usermenu_item_start . "<a href='" . _indexroot . "index.php?m=settings' class='usermenu-item-settings'>" . $_lang['usermenu.settings'] . "</a>" . _template_usermenu_item_end . "\n";
        _extend('call', 'tpl.usermenu.beforelogout', $extend_args);
        /*odhlaseni*/
        $output .= _template_usermenu_item_start . "<a href='" . _xsrfLink(_indexroot . "remote/logout.php?_return=" . urlencode($_SERVER['REQUEST_URI'])) . "' class='usermenu-item-logout'>" . $_lang['usermenu.logout'] . (_template_usermenu_showusername ? " [" . _loginname . "]" : '') . "</a>" . _template_usermenu_item_end . "\n";
    }

    if (_ulist and (!_notpublicsite or _loginindicator)) {
        /*uziv. menu*/
        $output .= _template_usermenu_item_start . "<a href='" . _indexroot . "index.php?m=ulist' class='usermenu-item-ulist'>" . $_lang['usermenu.ulist'] . "</a>" . _template_usermenu_item_end . "\n";
    }

    _extend('call', 'tpl.usermenu.last', $extend_args);

    if (_template_usermenu_parent != "") $output .= "</" . _template_usermenu_parent . ">\n";

    if (_template_usermenu_trim == 1) {
        $output = trim($output);
        $output = trim($output, _template_usermenu_item_start);
        $output = trim($output, _template_usermenu_item_end);
    }

    // vratit nebo vypsat
    if ($return) return $output;
    echo $output;
}

/**
 * Zjistit ID aktualni stranky
 * @return mixed
 */
function _templatePageID()
{
    return _indexOutput_pid;
}

/**
 * Zjisteni typu aktualni stranky
 *
 * Mozny vystup:
 * ---------------
 * module
 * article
 * custom
 * section
 * category
 * book
 * gallery
 * link
 * intersection
 * forum
 * plugin_page
 * plugin_handler
 * plugin_hook
 * (prazdna hodnota)
 *
 * @return string
 */
function _templatePageType()
{
    return _indexOutput_ptype;
}

/**
 * Zjisteni, zda je aktualni stranka hlavni stranou
 * @return bool
 */
function _templatePageIsIndex()
{
    return _indexOutput_pid == _index_page_id && !isset($GLOBALS['ids']);
}
