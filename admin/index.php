<?php
/* ----  inicializace jadra  ---- */
require '../require/load.php';
define('_administration', '1');
SL::init('../');

/* ----  vystup  ---- */

// priprava
$xsrf_protect = true;
$admintitle = $_lang['admin.title'];
if (isset($_GET['p'])) $getp = _anchorStr($_GET['p']);
else $getp = "index";
$output = '';
$admin_base_css_path = 'remote/style.css.php';
$admin_extra_css = array();
$admin_extra_js = array();

/* ---  hlavicka  --- */

/* --  vlozeni funkci administrace  -- */
require _indexroot . "admin/functions.php";

// priprava uzivatelskeho menu
$usermenu = '<span id="usermenu">';
if (_loginindicator and _loginright_administration) {
    $avatar = _getAvatar(_loginid, true, true);
    if (isset($avatar)) $usermenu .= '<a id="header-avatar" href="' . _indexroot . 'index.php?m=profile&amp;id=' . _loginname . '"><img src="' . $avatar . '" alt="' . _loginname . '" /></a>';
    $usermenu .= _loginpublicname . ' [';
    if (_messages) {
        $messages_count = DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-pm` WHERE (receiver=" . _loginid . " AND receiver_deleted=0 AND receiver_readtime<update_time) OR (sender=" . _loginid . " AND sender_deleted=0 AND sender_readtime<update_time)"), 0);
        if ($messages_count != 0) {
            $messages_count = " <span class='highlight'>(" . $messages_count . ")</span>";
        } else {
            $messages_count = "";
        }
        $usermenu .= "<a href='" . _indexroot . "index.php?m=messages'>" . $_lang['usermenu.messages'] . $messages_count . "</a>, ";
    }
    $usermenu .= '<a href="' . _indexroot . 'index.php?m=settings">' . $_lang['usermenu.settings'] . '</a>, <a href="' . _xsrfLink(_indexroot . 'remote/logout.php?_return=admin/') . '">' . $_lang['usermenu.logout'] . '</a>]';
    $usermenu .= '<a href="' . _url . '/" target="_blank" class="usermenu-web-link" title="' . $_lang['admin.link.site'] . '"><img class="icon" src="images/icons/guide.png" alt="' . $_lang['admin.link.site'] . '" /></a>';
} else {
    $usermenu .= '<a href="./">' . $_lang['usermenu.guest'] . '</a>';
}
$usermenu .= '</span>';

// systemove moduly (jmeno, 0-titulek, 1-prava ke vstupu, 2-nadrazeny modul, 3-podrazene moduly, [4-vlastni titulek a zpetny odkaz?], [5-je to plugin?])
$modules=array(
"index"=>           array($_lang['admin.menu.index'],             true,                                              null,                       array('index-edit'), true),
    "index-edit"=>      array($_lang['admin.menu.index.edit.title'],  (_loginright_group == 1),                          'index',                       array(), false),

"content"=>         array($_lang['admin.menu.content'],           _loginright_admincontent,                   null,                       array("content-move","content-titles","content-redir","content-articles","content-confirm","content-movearts","content-polls","content-polls-edit","content-boxes","content-editsection","content-editcategory","content-delete","content-editintersection","content-articles-list","content-articles-edit","content-articles-delete","content-boxes-edit","content-boxes-new","content-editbook","content-editseparator","content-editlink","content-editpluginpage","content-editgallery","content-manageimgs","content-artfilter"), false),
  "content-setindex"=>       array($_lang['admin.content.setindex.title'],        _loginright_admincontent,       "content",                  array()),
  "content-move"=>           array($_lang['admin.content.move.title'],            _loginright_admincontent,       "content",                  array()),
  "content-titles"=>         array($_lang['admin.content.titles.title'],          _loginright_admincontent,       "content",                  array()),
  "content-redir"=>          array($_lang['admin.content.redir.title'],           _loginright_admincontent,       "content",                  array()),
  "content-articles"=>       array($_lang['admin.content.articles.title'],        _loginright_adminart,           "content",                  array()),
  "content-articles-list"=>  array($_lang['admin.content.articles.list.title'],   _loginright_adminart,           "content-articles",         array()),
  "content-articles-edit"=>  array($_lang['admin.content.articles.edit.title'],   _loginright_adminart,           "content-articles",         array(), true),
  "content-articles-delete"=>array($_lang['admin.content.articles.delete.title'], _loginright_adminart,           "content-articles",         array(), true),
  "content-confirm"=>        array($_lang['admin.content.confirm.title'],         _loginright_adminconfirm,       "content",                  array()),
  "content-movearts"=>       array($_lang['admin.content.movearts.title'],        _loginright_admincategory,      "content",                  array()),
  "content-artfilter"=>      array($_lang['admin.content.artfilter.title'],       _loginright_admincategory,      "content",                  array()),
  "content-polls"=>          array($_lang['admin.content.polls.title'],           _loginright_adminpoll,          "content",                  array()),
  "content-polls-edit"=>     array($_lang['admin.content.polls.edit.title'],      _loginright_adminpoll,          "content-polls",            array()),
  "content-sboxes"=>         array($_lang['admin.content.sboxes.title'],          _loginright_adminsbox,          "content",                  array()),
  "content-boxes"=>          array($_lang['admin.content.boxes.title'],           _loginright_adminbox,           "content",                  array()),
  "content-boxes-edit"=>     array($_lang['admin.content.boxes.edit.title'],      _loginright_adminbox,           "content-boxes",            array()),
  "content-boxes-new"=>      array($_lang['admin.content.boxes.new.title'],       _loginright_adminbox,           "content-boxes",            array(), true),

  "content-delete"=>           array($_lang['admin.content.delete.title'],          true,                           "content",                  array()),
  "content-editsection"=>      array($_lang['admin.content.editsection.title'],     _loginright_adminsection,       "content",                  array(), false),
  "content-editcategory"=>     array($_lang['admin.content.editcategory.title'],    _loginright_admincategory,      "content",                  array(), false),
  "content-editintersection"=> array($_lang['admin.content.editintersection.title'],_loginright_adminintersection,  "content",                  array(), false),
  "content-editbook"=>         array($_lang['admin.content.editbook.title'],        _loginright_adminbook,          "content",                  array(), false),
  "content-editseparator"=>    array($_lang['admin.content.editseparator.title'],   _loginright_adminseparator,     "content",                  array(), false),
  "content-editlink"=>         array($_lang['admin.content.editlink.title'],        _loginright_adminlink,          "content",                  array(), false),
  "content-editgallery"=>      array($_lang['admin.content.editgallery.title'],     _loginright_admingallery,       "content",                  array(), false),
  "content-editforum"=>        array($_lang['admin.content.editforum.title'],       _loginright_adminforum,         "content",                  array(), false),
  "content-editpluginpage"=>   array($_lang['admin.content.editpluginpage.title'],  _loginright_adminpluginpage,    "content",                  array(), false),
      "content-manageimgs"=>      array($_lang['admin.content.manageimgs.title'],     _loginright_admingallery,       "content",                  array(), true),

"users"=>           array($_lang['admin.menu.users'],               _loginright_adminusers or _loginright_admingroups,        null,                       array("users-editgroup","users-delgroup","users-edit","users-delete","users-list","users-move")),
  "users-editgroup"=> array($_lang['admin.users.groups.edittitle'], _loginright_admingroups,            "users",                    array(), false),
  "users-delgroup"=>  array($_lang['admin.users.groups.deltitle'],  _loginright_admingroups,            "users",                    array()),
  "users-edit"=>      array($_lang['admin.users.edit.title'],       _loginright_adminusers,             "users",                    array()),
  "users-delete"=>    array($_lang['admin.users.deleteuser'],       _loginright_adminusers,             "users",                    array()),
  "users-list"=>      array($_lang['admin.users.list'],             _loginright_adminusers,             "users",                    array()),
  "users-move"=>      array($_lang['admin.users.move'],             _loginright_adminusers,             "users",                    array()),

"fman"=>            array($_lang['admin.menu.fman'],              _loginright_adminfman,                             null,                       array()),

"settings"=>        array($_lang['admin.menu.settings'],          _loginright_adminsettings,                         null,                       array("settings-plugins")),
    "settings-plugins"=> array($_lang['admin.settings.plugins.title'],    _loginright_adminsettings,                             "settings",            array(),        true),

"other"=>           array($_lang['admin.menu.other'],             _loginright_adminbackup or _loginright_adminrestore or _loginright_adminmassemail or _loginright_adminbans,  null,                       array("other-backup","other-massemail","other-bans","other-cleanup","other-transm")),
  "other-backup"=>       array($_lang['admin.other.backup.title'],    _loginright_adminbackup or _loginright_adminrestore,   "other",                     array()),
  "other-cleanup"=>      array($_lang['admin.other.cleanup.title'],   (_loginright_level==10001),                            "other",                     array()),
  "other-massemail"=>    array($_lang['admin.other.massemail.title'], _loginright_adminmassemail,                          "other",                     array()),
  "other-bans"=>         array($_lang['admin.other.bans.title'],      _loginright_adminbans,                               "other",                     array()),
  "other-transm"=>       array($_lang['admin.other.transm.title'],    (_loginid==0),                                         "other",                     array())
);

// priprava menu, pluginu
$menu = "<div id='menu'>\n";

// extend
_extend('call', 'admin.start');

// vystup dle stavu prihlaseni
if (_loginindicator and _loginright_administration) {

    // titulek adminu
    if (isset($modules[$getp][0])) {
        $admintitle = $modules[$getp][0];
    }

    // seznam prioritnich modulu s odkazem v menu
    $menuitems = array("index", "content");

    // nacteni pluginu
    $plugins_dir = _indexroot . 'plugins/admin/';
    $plugins = opendir($plugins_dir);
    $modules_other_count = 0;
    while (false !== ($plugin = readdir($plugins))) {
        if ($plugin === '.' || $plugin === '..' || !@is_dir($plugins_dir . $plugin)) continue;
        $config = @include($plugins_dir . $plugin . '/config.php');
        $modules[$plugin] = array($config['title'], $config['access'], null, array(), isset($config['autotitle']) && false === $config['autotitle'], true);
        if (isset($config['in-other']) && $config['in-other']) {
            // plugin v ostatnich funkcich
            $modules['other'][3][] = $plugin;
            $modules[$plugin][2] = 'other';
            ++$modules_other_count;
        } elseif (!isset($config['hidden']) || !$config['hidden']) {
           // plugin v hlavnim menu
           $menuitems[] = $plugin;
        }
    }
    closedir($plugins);

    // seznam zbylych modulu s odkazem v menu
    $menuitems_other = array("users", "fman", "settings", "other");
    for($i = 0; isset($menuitems_other[$i]); ++$i) $menuitems[] = $menuitems_other[$i];
    unset($menuitems_other);

    // rozsireni
    _extend('call', 'admin.init', array('menu' => &$menuitems, 'modules' => &$modules));

    // kod menu
    foreach ($menuitems as $item) {
        if ($modules[$item][1] == true) {
            if ($getp == $item or in_array($getp, $modules[$item][3])) $class = " class='act'";
            else $class = "";
            $menu .= "<a href='index.php?p=" . $item . "'$class>" . $modules[$item][0] . "</a> \n";
        }
    }
    $menu = trim($menu);

} else {
    $menu .= "<a href='./' class='act'>" . $_lang['global.login'] . "</a>\n";
}

// dokonceni menu
$menu .= "\n</div>\n<hr class='hidden' />";

/* ---  html hlavicka  --- */

$scheme_dark = _admin_schemeIsDark();

$output .= '<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="generator" content="SunLight CMS ' . _systemversion . ' ' . SL::$states[_systemstate] . _systemstate_revision . '" />
<meta name="robots" content="noindex,follow" />
<link href="' . $admin_base_css_path . '?s=' . _adminscheme . ($scheme_dark ? '&amp;d' : '') . '&amp;' . _cacheid . '" type="text/css" rel="stylesheet" />' .
(empty($admin_extra_css) ? '' : "\n" . implode("\n", $admin_extra_css) . "\n") .
'<script type="text/javascript">/* <![CDATA[ */var sl_scheme_dark = ' . ($scheme_dark ? 'true' : 'false') . ', sl_indexroot = \'' . _indexroot . '\';/* ]]> */</script>
<script type="text/javascript" src="remote/jscript.php?' . _cacheid . '&amp;' . _language . '"></script>
<script type="text/javascript" src="' . _indexroot . 'remote/jscript.php?' . _cacheid . '&amp;' . _language . '"></script>
<script type="text/javascript" src="remote/jquery.scrollwatch.min.js?' . _cacheid . '"></script>
<script type="text/javascript" src="remote/jquery.scrollfix.min.js?' . _cacheid . '"></script>
<script type="text/javascript" src="remote/jscript.php?' . _cacheid . '&amp;' . _language . '"></script>' .
(empty($admin_extra_js) ? '' : "\n" . implode("\n", $admin_extra_js) . "\n") .
(_lightbox ? '
<link rel="stylesheet" href="' . _indexroot . 'remote/lightbox/style.css?' . _cacheid . '" type="text/css" media="screen" />
<script type="text/javascript" src="' . _indexroot . 'remote/lightbox/script.js?' . _cacheid . '"></script>' : '') . (_codemirror ? '
<script type="text/javascript" src="modules/codemirror/codemirror.js?' . _cacheid . '"></script>
<link rel="stylesheet" href="modules/codemirror/codemirror.css?' . _cacheid . '" type="text/css" media="screen" />
<script type="text/javascript" src="modules/codemirror/util/overlay.js?' . _cacheid . '"></script>
<script type="text/javascript" src="modules/codemirror/mode/xml/xml.js?' . _cacheid . '"></script>
<script type="text/javascript" src="modules/codemirror/mode/javascript/javascript.js?' . _cacheid . '"></script>
<script type="text/javascript" src="modules/codemirror/mode/css/css.js?' . _cacheid . '"></script>
<script type="text/javascript" src="modules/codemirror/mode/htmlmixed/htmlmixed.js?' . _cacheid . '"></script>
<script type="text/javascript" src="modules/codemirror/mode/clike/clike.js?' . _cacheid . '"></script>
<script type="text/javascript" src="modules/codemirror/mode/php/php.js?' . _cacheid . '"></script>
<link rel="stylesheet" href="modules/codemirror/theme/' . ($scheme_dark ? 'ambiance' : 'eclipse') . '.css?' . _cacheid . '" type="text/css" media="screen" />' : '') . '
<title>' . _title . ' - ' . $_lang['admin.title'] . ' &gt; ' . $admintitle . '</title>
</head>

<body>

<div id="wrapper">
';

/* ---  hlavicka a menu  --- */
$output .= '
<div id="top">
<div id="header">' . $usermenu . _title . ' - ' . $_lang['admin.title'] . '</div>
<hr class="hidden" />
' . $menu . '
</div>';

$output .= "\n\n<div id='content'>\n";

/* ---  zprava o odeprenem pristupu  --- */
if (_loginindicator and _loginright_administration != 1) {
    $output .= "<h1>" . $_lang['global.error'] . "</h1>" . _formMessage(3, $_lang['admin.denied']);
}

/* ---  prihlaseni nebo obsah  --- */
if (_loginindicator and _loginright_administration) {

    // xsrf ochrana
    $xsrf_protect = true;
    if (!empty($_POST) && !_xsrfCheck()) {

        // neplatny token
        $output .= "<h1>" . $_lang['xsrf.title'] . "</h1><br>\n";
        $output .= _formMessage(3, $_lang['xsrf.msg'] . '<ul><li>' . str_replace('*domain*', _getDomain(), $_lang['xsrf.warning']) . '</li></ul>');
        $output .= "<form action='' method='post'>
" . _getPostdata(false, null, array('_security_token')) . _xsrfProtect() . "
<p><input type='submit' value='" . $_lang['xsrf.button'] . "' /></p>
</form>\n";

    } else {

        // vlozeni modulu
        if (array_key_exists($getp, $modules)) {
            if ($modules[$getp][1] == true and ($modules[$getp][2] == null or $modules[$modules[$getp][2]][1] == true)) {
                /*zpetny odkaz*/
                if ($modules[$getp][2] != null and !(isset($modules[$getp][4]) and $modules[$getp][4] == true)) {
                    $output .= "<a href='index.php?p=" . $modules[$getp][2] . "' class='backlink'>&lt; " . $_lang['global.return'] . "</a>";
                }
                /*titulek*/
                if (!(isset($modules[$getp][4]) and $modules[$getp][4] == true)) {
                    $output .= "<h1>" . $modules[$getp][0] . "</h1>";
                }

                /*soubor*/
                if (!isset($modules[$getp][5])) $file = "require/" . $getp . ".php";
                else $file = _indexroot . 'plugins/admin/' . $getp . '/script.php';

                /*vlozeni*/
                $extend_args = _extendArgs($output, array('name' => $getp, 'file' => &$file));
                _extend('call', 'admin.mod.init', $extend_args);
                _extend('call', 'admin.mod.' . $getp . '.pre', $extend_args);
                if (@file_exists($file)) {
                    require $file;
                    $extend_args = _extendArgs($output);
                    _extend('call', 'admin.mod.' . $getp . '.post', $extend_args);
                    _extend('call', 'admin.mod.post', $extend_args);
                } else $output .= _formMessage(2, $_lang['admin.moduleunavailable']);
            } else {
                $output .= "<h1>" . $_lang['global.error'] . "</h1>" . _formMessage(3, $_lang['global.accessdenied']);
            }
        } else {
            $output .= "<h1>" . $_lang['global.error404.title'] . "</h1>" . _formMessage(2, $_lang['global.error404']);
        }

    }

} else {

    // prihlasovaci formular
    if (empty($_POST)) {
        $login = _uniForm("login");
        $output .= $login[0];
    } else {
        $output .= "<h1>" . $_lang['admin.postrestore.title'] . "</h1>
<p class='bborder'>" . $_lang['admin.postrestore.p'] . "</p>
" . _formMessage(2, $_lang['admin.postrestore.msg']) . "
<form action='' method='post'>
<input type='submit' name='' value='" . $_lang['admin.postrestore.button'] . "' />
" . _getPostdata(false, null, array('_security_token')) . "
" . _xsrfProtect() . "</form>
";
    }
}

/* ---  paticka, vypis vystupu  --- */

// paticka
$output .= '
<div class="cleaner"></div>
</div>

<hr class="hidden" />
<div id="copyright">
<div>' . ((_loginindicator and _loginright_administration) ? '<a href="' . _url . '/" target="_blank">' . $_lang['admin.link.site'] . '</a> &nbsp;&bull;&nbsp; <a href="./" target="_blank">' . $_lang['admin.link.newwin'] . '</a>' : '<a href="../">&lt; ' . $_lang['admin.link.home'] . '</a>') . '</div>
';

// vypis
if (!($redir = defined('_redirect_to'))) {
    echo $output;
}

// presmerovani
if ($redir) {
    header("location: " . _redirect_to);
    exit;
}
?>

<span id="sl-copyright-element">Copyright &copy; 2006-2016 <a href="http://sunlight.shira.cz/" target="_blank">SunLight CMS</a><span> by <a href="http://shira.cz/" target="_blank">ShiraNai7</a></span></span>

</div>
</div>
</body>
</html>
