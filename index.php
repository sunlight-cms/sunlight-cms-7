<?php
/* ----  inicializace jadra  ---- */

if (!defined('_index_noinit')) {
    require './require/load.php';
    SL::init('./');
}

// funkce motivu
require _indexroot . 'require/functions-template.php';

/* ----  vystup  ---- */

$notpublic_form = false;
$notpublic_form_wholesite = false;
$found = true;

$url = parse_url(_url . '/');
$raw_get_data = array();
parse_str(parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY), $raw_get_data);
if (false === $url || !isset($url['path'])) $base_path = '/';
else $base_path = $url['path'];

/* --  xsrf ochrana  -- */
if (!empty($_POST) && !_xsrfCheck()) {

    // neplatny token
    $output = '';
    require _indexroot . 'require/xsrfscreen.php';
    define('_indexOutput_title', $_lang['xsrf.title']);
    define('_indexOutput_content', $output);

} elseif (_publicAccess(!_notpublicsite) or (isset($_GET['m']) and in_array($_GET['m'], array("login", "reg", "lostpass")))) {

    /* --  modul  -- */
    if (isset($_GET['m'])) {
        $mid = strval($_GET['m']);
        if (_loginindicator or !in_array($mid, array("settings", "editpost", "messages"))) {

            // rozpoznani modulu
            $ok = true;
            $nologin = false;
            $custom = false;
            $file = null;
            switch ($mid) {

                    // prihlaseni
                case "login":
                    define('_indexOutput_title', $_lang['login.title']);
                    break;

                    // seznam uzivatelu
                case "ulist":
                    if (_ulist) define('_indexOutput_title', $_lang['admin.users.list']);
                    else $ok = false;
                    break;

                    // registrace
                case "reg":
                    if (_registration and !_loginindicator) {
                        define('_indexOutput_title', $_lang['mod.reg']);
                    } else {
                        $ok = false;
                        $nologin = true;
                    }
                    break;

                    // ztracene heslo
                case "lostpass":
                    if (_lostpass and !_loginindicator) define('_indexOutput_title', $_lang['mod.lostpass']);
                    else {
                        $ok = false;
                        $nologin = true;
                    }
                    break;

                    // profil
                case "profile":
                    define('_indexOutput_title', $_lang['mod.profile']);
                    break;

                    // clanky autora
                case "profile-arts":
                    define('_indexOutput_title', $_lang['mod.profile.arts']);
                    break;

                    // prispevky uzivatele
                case "profile-posts":
                    define('_indexOutput_title', $_lang['mod.profile.posts']);
                    break;

                    // nastaveni
                case "settings":
                    define('_indexOutput_title', $_lang['mod.settings']);
                    break;

                    // uprava prispevku
                case "editpost":
                    define('_indexOutput_title', $_lang['mod.editpost']);
                    break;

                    // uzamknuti prispevku (forum topic)
                case "locktopic":
                    define('_indexOutput_title', $_lang['mod.locktopic']);
                    break;

                    // pripnuti prispevku (forum topic)
                case "stickytopic":
                    define('_indexOutput_title', $_lang['mod.stickytopic']);
                    break;

                    // presunuti prispevku (forum topic)
                case "movetopic":
                    define('_indexOutput_title', $_lang['mod.movetopic']);
                    break;

                    // vzkazy
                case "messages":
                    if (_messages) define('_indexOutput_title', $_lang['mod.messages']);
                    else $ok = false;
                    break;

                    // vyhledavani
                case "search":
                    if (_search) define('_indexOutput_title', $_lang['mod.search']);
                    else $ok = false;
                    break;

                    // tema
                case "topic":
                    // titulek je definovan ve skriptu
                    break;

                    // nenalezeno
                default:
                    // rozsireni?
                    $title = null;
                    _extend('call', 'mod.custom.' . $mid, array('file' => &$file, 'title' => &$title));
                    if (isset($file, $title)) {
                        // found
                        $custom = true;
                        define('_indexOutput_title', $title);
                    } else {
                        $ok = false; // invalid
                    }
                    break;

            }

            // vykonani
            if ($ok) {

                // priprava
                $module = '';
                define('_indexOutput_ptype', 'module');
                define('_indexOutput_pid', $mid);
                if ($mid !== 'topic') define('_indexOutput_url', "index.php?m=" . $mid);

                // rozsireni
                $extend_args = _extendArgs($module, array('mid' => $mid, 'custom' => &$custom, 'file' => &$file));
                _extend('call', 'mod.' . $mid . '.pre', $extend_args);

                // skript
                require $custom ? $file : _indexroot . 'require/mod/' . $mid . '.php';
                _extend('call', 'mod.' . $mid . '.post', $extend_args); // rozsireni - po

                // konstanta
                define('_indexOutput_content', $module);

            } elseif ($nologin) {

                define('_indexOutput_title', $_lang['nologin.title']);
                define('_indexOutput_content', (_template_autoheadings ? "<h1>" . _indexOutput_title . "</h1>" : '') . _formMessage(1, $_lang['nologin.msg']));

            }

        } else {
            $notpublic_form = true;
        }
    }

    /* --  stranka nebo clanek  -- */  else {

        // zjistit typ obsahu, nacist data
        $continue = false;
        $nokit = false;
        $plugin_handler = null;
        if (_modrewrite && isset($_GET['_rwp']) && !array_key_exists('_rwp', $raw_get_data)) {

            // stranka nebo clanek s mod_rewrite
            $type = null;
            list($ids, $ids_first, $ids_full) = _parseIndexPath($_GET['_rwp']);
            $rewritten = true;

            _extend('call', 'index.route', array(
                'ids' => $ids,
                'ids_first' => $ids_first,
                'ids_full' => $ids_full,
                'handler' => &$plugin_handler,
            ));

            if (null !== $plugin_handler) {
                // zpracovani pluginem
                $type = 2;
                $continue = true;
                $query = true;
            } elseif (isset($ids[1])) {

                // clanek nebo segment plugin stranky
                $query = _fetchPageData(
                    array($ids_full, $ids[0]),
                    null,
                    'art.id AS art_id',
                    'LEFT JOIN `' . _mysql_prefix . '-articles` AS art ON(page.type=2 AND art.home1=page.id AND art.`title_seo`=' . DB::val($ids[1]) . ')',
                    'page.type!=4'
                );

                if (false !== $query) {
                    if (isset($query['art_id'])) {
                        // clanek
                        $type = 1;
                        $query = DB::query_row('SELECT * FROM `' . _mysql_prefix . '-articles` WHERE id=' . $query['art_id']);
                        $continue = true;
                    } elseif (2 == $query['type'] && $ids_full !== $query['title_seo']) {
                        // clanek nenalezen
                        $type = 1;
                        $continue = true;
                        $query = false;
                    } elseif ($ids_full === $query['title_seo']) {
                        // stranka
                        $ids = array($ids_full, null);
                        $type = 0;
                        $continue = true;
                    } elseif (9 == $query['type']) {
                        // segment plugin stranky
                        $type = 0;
                        $continue = true;
                    }
                } else {
                    $continue = true;
                }

            } else {

                // stranka
                $type = 0;
                $continue = true;
                $query = _fetchPageData($ids[0], null, null, null, 'page.type!=4');

                if (false === $query) {
                    // stranka nenalezena, mozna kompatibilita se starou verzi adres clanku s mod_rewrite
                    $query = DB::query_row('SELECT art.id,art.title,art.title_seo,cat.title_seo AS cat_title_seo FROM `' . _mysql_prefix . '-articles` AS art JOIN `' . _mysql_prefix . '-root` AS cat ON(cat.id=art.home1) WHERE art.title_seo=' . DB::val($ids[0]));
                    if (false !== $query) {
                        define('_redirect_to', _url . '/' . _linkArticle($query['id'], $query['title_seo'], $query['cat_title_seo']));
                        $query = false;
                    }
                }

            }

        } elseif (isset($_GET['a'])) {

            // clanek bez mod_rewrite
            $type = 1;
            list($ids, $ids_first, $ids_full) = _parseIndexPath($_GET['a']);
            $rewritten = false;

            if (isset($ids[1])) {
                $query = DB::query_row("SELECT art.* FROM `" . _mysql_prefix . "-articles` AS art JOIN `" . _mysql_prefix . "-root` AS cat ON(cat.`title_seo`=" . DB::val($ids[0]) . " AND art.home1=cat.id) WHERE art.`title_seo`='" . DB::esc($ids[1]) . "' LIMIT 1");
                $continue = true;
            } else {
                // spatny format adresy
                if (is_numeric($ids[0])) {
                    // pouzit kompatibilni presmerovani podle ID nize
                    $query = false;
                    $continue = true;
                } else {
                    // presmerovat na spravnou adresu s kategorii
                    $query = DB::query_row('SELECT art.id,art.title,art.title_seo,cat.title_seo AS cat_title_seo FROM `' . _mysql_prefix . '-articles` AS art JOIN `' . _mysql_prefix . '-root` AS cat ON(cat.id=art.home1) WHERE art.title_seo=' . DB::val($ids[0]));
                    if (false !== $query) {
                        define('_redirect_to', _url . '/' . _linkArticle($query['id'], $query['title_seo'], $query['cat_title_seo']));
                    }
                }
            }

        } elseif (isset($_GET['p'])) {

            // stranka bez mod_rewrite
            $type = 0;
            list($ids, $ids_first, $ids_full) = _parseIndexPath($_GET['p']);
            $rewritten = false;
            $continue = true;

            _extend('call', 'index.route', array(
                'ids' => $ids,
                'ids_first' => $ids_first,
                'ids_full' => $ids_full,
                'handler' => &$plugin_handler,
            ));

            if (null !== $plugin_handler) {
                // zpracovani pluginem
                $type = 2;
                $continue = true;
                $query = true;
            } else {
                // nalezeni stranky
                $query = _fetchPageData(array($ids_full, $ids[0]), null, null, null, 'page.type!=4');
                if (false !== $query) {
                    if (isset($ids[1]) && 9 != $query['type'] && $ids_full !== $query['title_seo']) {
                        // nalezena stranka se segmentem, ktera neni plugin stranka
                        $query = false;
                    } elseif ($ids_full === $query['title_seo']) {
                        // kompletni shoda title_seo (tj. ne segment)
                        $ids = array($ids_full, null);
                    }
                }
            }

        } else {

            // hlavni strana
            $type = 0;
            $ids = null;
            $rewritten = null;

            $query = _fetchPageData(null, null, null, null, 'page.id=' . _index_page_id);
            if (false !== $query) {
                $continue = true;
            } else {

                // strana nenalezena, pokus o opravu
                if (_index_page_id != 0) {
                    $query = DB::query_row('SELECT * FROM `' . _mysql_prefix . '-root` WHERE `type`!=4 AND `intersection`=-1 AND `visible`=1 ORDER BY `ord` LIMIT 1');
                    if ($query === false) {
                        // nelze opravit
                        $fix_id = 0;
                        $nokit = true;
                    } else {
                        // opraveno
                        $fix_id = $query['id'];
                        $continue = true;
                    }
                    DB::query('UPDATE `' . _mysql_prefix . '-settings` SET `val`=' . $fix_id . ' WHERE `var`=\'index_page_id\'');
                }

            }

        }

        // path
        if (_modrewrite && $rewritten && isset($ids)) {
            define('_path', $base_path . $ids_full);
        } else {
            define('_path', $base_path);
        }

        // generovani obsahu
        if ($continue) {
            if (false !== $query) {

                if ($type === 0) {

                    /* --  stranka  -- */

                    // rozebrani dat, test pristupu
                    $id = $query['id'];
                    define('_indexOutput_url', _linkRoot($id, $query['title_seo']) . (isset($ids[1]) ? '/' . $ids[1] : ''));
                    define('_indexOutput_pid', $id);

                    // presmerovani na mod_rewrite adresu
                    if (_modrewrite && isset($ids) && !$rewritten) {
                        $redir_query = $raw_get_data;
                        unset($redir_query['p']);
                        define('_redirect_to', _url . '/' . _addGetToLink(_indexOutput_url, _buildQuery($redir_query), false));
                    } elseif (isset($ids) && $id == _index_page_id) {
                        // presmerovani hlavni strany (kvuli duplicite)
                        define('_redirect_to', _url . '/');
                    } else {

                        // priprava pro vystup
                        if (_publicAccess($query['public'], $query['level'])) {

                            // udalosti stranky
                            if (null !== $query['events']) {
                                $query['events'] = _parseStr($query['events']);
                                for ($i = 0; isset($query['events'][$i]); ++$i) {
                                    $event = explode(':', $query['events'][$i], 2);
                                    _extend('call', 'page.event.' . $event[0], array('arg' => isset($event[1]) ? $event[1] : null, 'query' => &$query));
                                }
                            }

                           // zpetny odkaz
                            $backlink = null;
                            _extend('call', 'page.backlink', array('backlink' => &$backlink, 'query' => $query));

                            if (null === $backlink && isset($query['inter_id']) && $query['visible'] == 1 && _template_intersec_backlink) {
                                // odkaz na rozcestnik
                                $backlink = _linkRoot($query['inter_id'], $query['inter_title_seo']);
                            }

                            if (null !== $backlink) $backlink = "<a href='" . $backlink . "' class='backlink'>&lt; " . $_lang['global.return'] . "</a>";
                            else $backlink = "";

                            // vlozeni modulu
                            $plugin = false;
                            $state = 1; // 0 = 404, 1 = ok, 2 = chyba pluginu
                            switch ($query['type']) {
                                case 1:
                                    define('_indexOutput_ptype', 'section');
                                    break;
                                case 2:
                                    define('_indexOutput_ptype', 'category');
                                    break;
                                case 3:
                                    define('_indexOutput_ptype', 'book');
                                    break;
                                case 5:
                                    define('_indexOutput_ptype', 'gallery');
                                    break;
                                case 6:
                                    define('_indexOutput_ptype', 'link');
                                    break;
                                case 7:
                                    define('_indexOutput_ptype', 'intersection');
                                    break;
                                case 8:
                                    define('_indexOutput_ptype', 'forum');
                                    break;

                                case 9:

                                    // vychozi stav
                                    $plugin = true;
                                    $state = 2;

                                    // typ pluginu
                                    if (null === $query['type_idt']) break;

                                    // volani
                                    $pluginfile = null;
                                    $plugin_segment_handled = false;
                                    _extend('call', 'ppage.' . $query['type_idt'] . '.show', array('file' => &$pluginfile, 'query' => &$query, 'segment' => $ids[1], 'segment_handled' => &$plugin_segment_handled));
                                    if (null === $pluginfile) break;

                                    // kontrola segmentu
                                    if (isset($ids[1]) && !$plugin_segment_handled) {
                                        // 404
                                        $state = 0;
                                        break;
                                    }

                                    // vse ok
                                    $state = 1;
                                    define('_indexOutput_ptype', 'plugin_page');
                                    break;

                            }

                            if (1 === $state) {

                                // priprava
                                $title = '';
                                $content = '';

                                // rozsireni
                                $file = null;
                                $extend_args = _extendArgs($content, array('query' => &$query));
                                _extend('call', 'page.all.pre', $extend_args);
                                _extend('call', 'page.' . _indexOutput_ptype . '.pre', _extendArgs($content, array('query' => &$query, 'file' => &$file)));

                                // definovani klicovych slov a popisu
                                if ($query['keywords'] !== '') define('_indexOutput_keywords', $query['keywords']);
                                if ($query['description'] !== '') define('_indexOutput_description', $query['description']);

                                // vlozeni skriptu
                                require (isset($file) ? $file : ($plugin ? $pluginfile : _indexroot . 'require/page/' . _indexOutput_ptype . '.php'));
                                _extend('call', 'page.' . _indexOutput_ptype . '.post', $extend_args); // rozsireni - po

                                // definovani konstant
                                define('_indexOutput_title', $title);
                                define('_indexOutput_content', $backlink . $content);

                            } elseif (2 === $state) {

                                // chybi rozsireni
                                define('_indexOutput_title', $_lang['index.pagerr.title']);
                                define('_indexOutput_content', $backlink . _formMessage(3, sprintf($_lang['index.pagerr.p'], $query['type_idt'])));

                            }

                        } else {
                            $notpublic_form = true;
                        }
                    }

                } elseif ($type === 1) {

                    /* --  clanek  -- */

                    // rozebrani dat, test pristupu
                    $access = _articleAccess($query);
                    $id = $query['id'];
                    $query['cat_title_seo'] = $ids[0];
                    define('_indexOutput_url', _linkArticle($id, $query['title_seo'], $query['cat_title_seo']));
                    define('_indexOutput_ptype', 'article');

                    // presmerovani na mod_rewrite adresu
                    if (_modrewrite && !$rewritten) {
                        $redir_query = $raw_get_data;
                        unset($redir_query['a']);
                        define('_redirect_to', _url . '/' . _addGetToLink(_indexOutput_url, _buildQuery($redir_query), false));
                    } else {

                        // vlozeni modulu
                        if ($access == 1) {

                            // priprava
                            $content = '';

                            // rozsireni
                            $file = null;
                            $extend_args = _extendArgs($content, array('query' => &$query));
                            _extend('call', 'article.pre', _extendArgs($content, array('query' => &$query, 'file' => &$file)));

                            // definovani klicovych slov a popisu
                            if ($query['keywords'] !== '') define('_indexOutput_keywords', $query['keywords']);
                            if ($query['description'] !== '') define('_indexOutput_description', $query['description']);

                            // skript
                            require (isset($file) ? $file : _indexroot . "require/page/article.php");
                            _extend('call', 'article.post', $extend_args); // rozsireni - po

                            // konstanty
                            define('_indexOutput_content', $content);
                            define('_indexOutput_title', $title);

                        } elseif ($access == 2) {
                            $notpublic_form = true;
                        }

                    }

                } else {

                    /* --  plugin  -- */

                    // konstanty
                    define('_indexOutput_url', _linkCustom($ids_full));
                    define('_indexOutput_ptype', 'plugin_handler');

                    // presmerovani na mod_rewrite adresu
                    if (_modrewrite && !$rewritten) {

                        parse_str($_SERVER['QUERY_STRING'], $redir_query);
                        unset($redir_query['p']);
                        define('_redirect_to', _url . '/' . _addGetToLink(_indexOutput_url, _buildQuery($redir_query), false));

                    } else {

                        // spusteni handleru
                        $title = $content = '';
                        call_user_func($plugin_handler, array(
                            'title' => &$title,
                            'content' => &$content,
                            'ids' => $ids,
                            'ids_first' => $ids_first,
                            'ids_full' => $ids_full,
                        ));

                        // konstanty obsahu
                        define('_indexOutput_content', $content);
                        define('_indexOutput_title', $title);

                    }

                }

            } else {

                /* ---  neexistujici stranka  --- */

                do {

                    // pouzit presmerovani, pokud existuje
                    $q = DB::query_row('SELECT new FROM `' . _mysql_prefix . '-redir` WHERE old=\'' . DB::esc($ids_full) . '\' AND active=1');
                    if (false !== $q) {
                        define('_redirect_to', _url . '/' . (_modrewrite ? '' : 'index.php?p=') . $q['new']);
                        break;
                    }

                    // presmerovani starych ciselnych adres bez mod_rewrite
                    if (!$rewritten && is_numeric($ids[0])) {
                        $ids = intval($ids[0]);
                        if (0 === $type) $query = DB::query('SELECT `id`,`title_seo` FROM `' . _mysql_prefix . '-root` WHERE `id`=' . $ids);
                        else $query = DB::query('SELECT art.`id`,art.`title_seo`,cat.`title_seo` AS cat_title_seo FROM `' . _mysql_prefix . '-articles` AS art JOIN `' . _mysql_prefix . '-root` AS cat ON(cat.id=art.home1) WHERE art.`id`=' . $ids);
                        $query = DB::row($query);
                        if ($query !== false) {
                            // stranka nalezena podle ID, presmerovani
                            define('_redirect_to', _url . '/' . (($type === 0) ? _linkRoot($query['id'], $query['title_seo']) : _linkArticle($query['id'], $query['title_seo'], $query['cat_title_seo'])));
                            break;
                        }
                    }

                    // odchyceni rozsirenim
                    if ($rewritten || $type === 0) {
                        $title = $content = null;
                        _extend('call', 'index.notfound.hook', array('output' => &$content, 'title' => &$title, 'ids' => $ids));
                        if (isset($title, $content)) {
                            define('_indexOutput_ptype', 'plugin_hook');
                            define('_indexOutput_title', $title);
                            define('_indexOutput_content', $content);
                            break;
                        }
                    }

                } while (false);

            }

        } elseif ($nokit) {
            // neni co zobrazit
            define('_indexOutput_content', (_template_autoheadings ? "<h1>" . $_lang['global.error404.title'] . "</h1>" : '') . _formMessage(2, $_lang['global.nokit']));
            define('_indexOutput_title', $_lang['global.error404.title']);
        }

    }

} else {
    $notpublic_form = true;
    $notpublic_form_wholesite = true;
}

/* --  definovani nedefinovanych konstant  -- */
if (!defined('_indexOutput_pid')) define('_indexOutput_pid', -1);
if (!defined('_indexOutput_ptype')) define('_indexOutput_ptype', 'none');
if (!defined('_indexOutput_url')) define('_indexOutput_url', _indexroot);
if (!defined('_path')) define('_path', $base_path);

/* --  nenalezeno nebo pozadovani prihlaseni pro neverejny obsah  -- */
if (!defined('_indexOutput_content')) {
    if (!$notpublic_form) {
        $content_404 = (_template_autoheadings ? "<h1>" . $_lang['global.error404.title'] . "</h1>" : '') . _formMessage(2, $_lang['global.error404']);
        _extend('call', 'index.notfound', _extendArgs($content_404));
        define('_indexOutput_content', $content_404);
        define('_indexOutput_title', $_lang['global.error404.title']);
        $found = false;
    } else {
        $form = _uniForm("notpublic", array($notpublic_form_wholesite));
        _extend('call', 'index.notpublic', _extendArgs($form[0]));
        define('_indexOutput_content', $form[0]);
        define('_indexOutput_title', $form[1]);
    }
}

/* --  vlozeni sablony motivu nebo presmerovani  -- */
if (!defined('_redirect_to')) {
    if (!$found) {
        header('HTTP/1.1 404 Not Found');
    }
    $template_path = _extend('fetch', 'index.template');
    if (null === $template_path) {
        $template_path = _indexroot . 'plugins/templates/' . _template . '/template.php';
    }
    require $template_path;
} else {
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . _redirect_to);
    exit;
}
