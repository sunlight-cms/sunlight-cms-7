<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ----  nacteni promennych  ---- */
$continue = false;
$custom_array = array();
$custom_settings = "";
$editscript_enable_content = true;
$editscript_extra_row = '';
$editscript_extra = '';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $query = DB::query("SELECT * FROM `" . _mysql_prefix . "-root` WHERE id=" . $id . " AND type=" . $type);
    if (DB::size($query) != 0) {
        $query = DB::row($query);
        $continue = true;
        $new = false;
    }
} else {
    $id = null;
    $new = true;
    $continue = true;

    /* ---  vychozi data pro novou polozku --- */
    switch ($type) {
        case 1:
            $var1 = 0;
            $var2 = 0;
            $var3 = 0;
            $var4 = 0;
            break;
        case 2:
            $var1 = 1;
            $var2 = 15;
            $var3 = 1;
            $var4 = 1;
            break;
        case 3:
            $var1 = 1;
            $var2 = 15;
            $var3 = 0;
            $var4 = 0;
            break;
        case 5:
            $var1 = 3;
            $var2 = 9;
            $var3 = 110;
            $var4 = 147;
            break;
        case 7:
            $var1 = 1;
            $var2 = 0;
            $var3 = 0;
            $var4 = 0;
            break;
        case 8:
            $var1 = 30;
            $var2 = 0;
            $var3 = 1;
            $var4 = 0;
            break;
        default:
            $var1 = 0;
            $var2 = 0;
            $var3 = 0;
            $var4 = 0;
            break;
    }

    $query = array(
        'id' => -1,
        'title' => $_lang['admin.content.' . $type_array[$type]],
        'title_seo' => '',
        'keywords' => '',
        'description' => '',
        'type' => $type,
        'type_idt' => null,
        'intersection' => -1,
        'intersectionperex' => '',
        'ord' => 1,
        'content' => '',
        'visible' => 1,
        'public' => 1,
        'level' => 0,
        'autotitle' => 1,
        'events' => null,
        'var1' => $var1,
        'var2' => $var2,
        'var3' => $var3,
        'var4' => $var4,
    );

    _extend('call', 'admin.root.default', array('data' => &$query));

}
