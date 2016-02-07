<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  nastaveni a vlozeni skriptu pro upravu stranky  --- */

// inicializace editscriptu
$type = 9;
require 'require/sub/content-editscript-init.php';

// nacist info o plugin strance
$ppages = _admin_getPluginPageInfos();
if ($new) {
    if (!isset($_GET['idt'])) {
        $output .= _formMessage(3, $_lang['global.badinput']);

        return;
    }
    $type_idt = strval($_GET['idt']);
} else {
    $type_idt = $query['type_idt'];
}

// overit dostupnost pluginu
if (!isset($ppages[$type_idt])) {
    $output .= _formMessage(3, sprintf($_lang['plugin.error'], $type_idt));

    return;
}
$ppage = $ppages[$type_idt];

// promenne editscriptu
$custom_settings = '';
$custom_array = array();
$use_editscript = true;

// udalost pripravy editace
$file = null;
_extend('call', 'ppage.' . $type_idt . '.edit', _extendArgs($output, array('query' => &$query, 'new' => $new, 'es_settings' => &$custom_settings, 'es_savemap' => &$custom_array, 'es_enable' => &$use_editscript, 'es_content' => &$editscript_enable_content, 'es_extrarow' => &$editscript_extra_row, 'es_extra' => &$editscript_extra, 'es_file' => &$file)));

// vlozeni skriptu
if ($use_editscript) {

    // editscript
    $custom_array[] = array('type_idt', false, 0, true);
    require './require/sub/content-editscript.php';

} elseif (null !== $file) {

    // vlastni
    require $file;

} else {

    // nenastaven
    $output .= _formMessage(3, $_lang['admin.content.editpluginpage.fileerr']);

}
