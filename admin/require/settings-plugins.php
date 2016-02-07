<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  nacist pluginy  --- */

$plugins = array();
$common_dir = _indexroot . 'plugins/common/';
$handle = opendir($common_dir);
while (false !== ($item = readdir($handle))) {
    if ($item === '.' || $item === '..' || !is_dir($common_dir . $item)) continue;
    $info = (array) @include ($common_dir . $item . '/info.php');
    if (empty($info) || !isset($info['name'], $info['descr'], $info['version'], $info['author'], $info['url'], $info['actions'])) continue;
    $plugins[$item] = $info;
}
unset($info);

/* ---  akce pluginu  --- */

if (isset($_GET['action'])) {

    // validovat akci
    $action = explode('.', strval($_GET['action']), 2);
    if (sizeof($action) !== 2 || !isset($plugins[$action[0]]) || !in_array($action[1], $plugins[$action[0]]['actions'])) {
        $output .= _formMessage(3, $_lang['global.badinput']);

        return;
    }

    // zpetny odkaz, titulek, text
    $output .= '
<a href="index.php?p=settings-plugins" class="backlink">&lt; ' . $_lang['global.return'] . '</a>
<h1>' . $plugins[$action[0]]['name'] . ' - ' . (isset($_lang[$pac_lang = 'admin.settings.plugins.ac.' . $action[1]]) ? $_lang[$pac_lang] : _htmlStr($action[1])) . '</h1>
';

    // provest akci
    $plugin = $action[0];
    $action = $action[1];
    $url = 'index.php?p=settings-plugins&amp;action=' . $plugin . '.' . $action;
    require_once _indexroot . 'admin/functions-pcommon.php';
    include $common_dir . $plugin . '/actions.php';

    return;

}

/* ---  vypis pluginu  --- */

$output .= '
<a href="index.php?p=settings" class="backlink">&lt; ' . $_lang['global.return'] . '</a>
<h1>' . $_lang['admin.settings.plugins.title'] . '</h1>
<p class="bborder">' . $_lang['admin.settings.plugins.p'] . '</p>

<table class="list">
<thead><tr><td>' . $_lang['admin.settings.plugins.name'] . '</td><td>' . $_lang['admin.settings.plugins.version'] . '</td><td>' . $_lang['admin.settings.plugins.author'] . '</td><td>' . $_lang['admin.settings.plugins.descr'] . '</td><td>' . $_lang['admin.settings.plugins.ac'] . '</td></tr></thead>
<tbody>
';
if (empty($plugins)) $output .= '<tr><td colspan="5">' . $_lang['admin.settings.plugins.none'] . '</td></tr>' . _nl;
else {
    foreach ($plugins as $plugin => $info) {
        $ac = '';
        if (!empty($info['actions'])) {
            for ($i = 0; isset($info['actions'][$i]); ++$i) {
                if ($i !== 0) $ac .= ' &nbsp;&bull;&nbsp; ';
                $ac .= '<a href="index.php?p=settings-plugins&amp;action=' . $plugin . '.' . $info['actions'][$i] . '">' . (isset($_lang[$ac_lang = 'admin.settings.plugins.ac.' . $info['actions'][$i]]) ? $_lang[$ac_lang] : _htmlStr($info['actions'][$i])) . '</a>';
            }
        }
        $output .= '<tr><td>' . _htmlStr($info['name']) . '</td><td>' . _htmlStr($info['version']) . '</td><td>' . (isset($info['url']) ? '<a href="' . _htmlStr($info['url']) . '" target="_blank">' . _htmlStr($info['author']) . '</a>' : $info['author']) . '</td><td>' . $info['descr'] . '</td><td>' . $ac . '</td></tr>' . _nl;
    }
}
$output .= '</tbody>
</table>
';
