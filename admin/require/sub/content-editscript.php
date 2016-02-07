<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  ulozeni  --- */
if (isset($_POST['title']) and $continue) {

    // pole vstupu (nazev, checkbox?, zpracovani: 0-nic 1-htmlstr 2-intval 3-floatval, textova hodnota?)
    $save_array = array(
        array("title", false, 1, true),
        array("title_seo", false, 0, true),
        array("keywords", false, 1, true),
        array("description", false, 1, true),
        array("intersection", false, 2, false),
        array("ord", false, 3, false),
        array("visible", true, 0, false),
        array("public", true, 0, false),
        array("level", false, 2, false),
        array("autotitle", true, 0, false),
        array("intersectionperex", false, 0, true),
        array("content", false, 0, true),
        array("events", false, 0, false),
    );
    if (!$editscript_enable_content) unset($save_array[11]);
    $save_array = array_merge($save_array, $custom_array);

    // ulozeni
    $sql = "";
    $new_column_list = "";
    foreach ($save_array as $item) {

        // nacteni a zpracovani hodnoty
        if ($item[1] == false) {
            if (!isset($_POST[$item[0]])) {
                $_POST[$item[0]] = null;
            }
            switch ($item[2]) {
                case 0:
                    $val = $_POST[$item[0]];
                    break;
                case 1:
                    $val = _htmlStr($_POST[$item[0]]);
                    break;
                case 2:
                    $val = intval($_POST[$item[0]]);
                    break;
                case 3:
                    $val = floatval($_POST[$item[0]]);
                    break;
            }
        } else {
            $val = _checkboxLoad($item[0]);
        }

        // individualni akce
        $skip = false;
        switch ($item[0]) {

                // content
            case "content":
                $val = _filtrateHCM(trim($val));
                break;

                // intersection
            case "intersection":
                if (DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-root` WHERE id=" . $val . " AND type=7"), 0) == 0 or $type == 7) {
                    $val = -1;
                }
                break;

                // title
            case "title":
                $val = trim($val);
                if ($val == "") $val = $_lang['global.novalue'];
                $title = $val;
                break;

                // title_seo
            case "title_seo":
                if ($val === '') $val = $title;
                $val = _anchorStr($val, true, array('/' => 0));
                break;

                // keywords, description, intersectionperex
             case "keywords":
             case "description":
             case "intersectionperex":
                $val = trim($val);
                break;

                // level
             case "level":
                if ($val < 0) $val = 0;
                elseif ($val > _loginright_level) $val = _loginright_level;
                break;

                // var1
            case "var1":
                switch ($type) {
                        // zpusob razeni v kategoriich
                    case 2:
                        if ($val < 1 or $val > 4) $val = 1;
                        break;
                        // obrazku na radek v galerii
                    case 5:
                        if ($val <= 0 and $val != -1) $val = 1;
                        break;
                        // temat na stranu ve forech
                    case 8:
                        if ($val <= 0) $val = 1;
                        break;
                }
                break;

                // var2
            case "var2":
                switch ($type) {
                        // clanku na stranu v kategoriich, prispevku na stranu v knihach, obrazku na stranu v galeriich
                    case 2:
                    case 3:
                    case 5:
                        if ($val <= 0) $val = 1;
                        break;
                }
                break;

                // var3
            case "var3":
                switch ($type) {
                        // vyska nahledu v galeriich
                    case 5:
                        if ($val < 10) $val = 10;
                        elseif ($val > 1024) $val = 1024;
                        break;
                }
                break;

                // var4
            case "var4":
                switch ($type) {
                        // sirka nahledu v galeriich
                    case 5:
                        if ($val <= 10) $val = 10;
                        elseif ($val > 1024) $val = 1024;
                        break;
                }
                break;

                // smazani komentaru v sekcich
            case "delcomments":
                if ($type == 1 and $val == 1) DB::query("DELETE FROM `" . _mysql_prefix . "-posts` WHERE home=" . $id . " AND type=1");
                $skip = true;
                break;

                // smazani prispevku v knihach
            case "delposts":
                if ($val == 1) {
                    $ptype = null;
                    switch ($type) {
                        case 3:
                            $ptype = 3;
                            break;
                        case 8:
                            $ptype = 5;
                            break;
                    }
                    if ($ptype != null) DB::query("DELETE FROM `" . _mysql_prefix . "-posts` WHERE home=" . $id . " AND type=" . $ptype);
                }
                $skip = true;
                break;

                // typ plugin stranky
            case "type_idt":
                if ($type == 9 && $new) $val = $type_idt;
                else $skip = true;
                break;

                // udalosti stranky
            case "events":
                $val = trim($val);
                if ('' === $val) $val = 'NULL';
                else $item[3] = true;
                break;

        }

        if ($skip != true) {

            // uvozovky pro text
            if ($item[3] == true) $quotes = "'";
            else $quotes = "";

            // ulozeni casti sql dotazu
            if (!$new) {
                $sql .= $item[0] . "=" . $quotes . DB::esc($val) . $quotes . ",";
            } else {
                $new_column_list .= $item[0] . ",";
                $sql .= $quotes . DB::esc($val) . $quotes . ",";
            }

        }

    }

    // vlozeni / ulozeni
    $sql = rtrim($sql, ",");
    if (!$new) {
        // ulozeni
        DB::query("UPDATE `" . _mysql_prefix . "-root` SET " . $sql . "  WHERE id=" . $id);
        _extend('call', 'admin.root.edit', array('id' => $id, 'query' => $query));
    } else {
        // vytvoreni
        $new_column_list = rtrim($new_column_list, ",");
        DB::query("INSERT INTO `" . _mysql_prefix . "-root` (type," . $new_column_list . ") VALUES (" . $type . "," . $sql . ")");
        $id = $query['id'] = DB::insertID();
        _extend('call', 'admin.root.new', array('id' => $id, 'query' => $query));
    }

    define('_redirect_to', 'index.php?p=content-edit' . $type_array[$type] . '&id=' . $id . '&saved');

    return;
}

/* ---  vystup  --- */
if ($continue != true) {
    $output .= _formMessage(3, $_lang['global.badinput']);
} else {

    // vyber rozcestniku
    if ($type != 7) {
        $intersection_select = "<select name='intersection' class='selectmedium'><option value='-1' class='special'>" . $_lang['admin.content.form.intersection.none'] . "</option>";
        $isquery = DB::query("SELECT id,title FROM `" . _mysql_prefix . "-root` WHERE type=7 ORDER BY ord");
        while ($item = DB::row($isquery)) {
            if ($item['id'] == $query['intersection']) {
                $selected = " selected='selected'";
            } else {
                $selected = "";
            }
            $intersection_select .= "<option value='" . $item['id'] . "'" . $selected . ">" . _cutStr($item['title'], 22) . "</option>";
        }
        $intersection_select .= "</select>";
        $intersection_row = "<td class='rpad'><strong>" . $_lang['admin.content.form.intersection'] . "</strong></td><td>" . $intersection_select . "</td>";
    } else {
        $intersection_select = "";
        $intersection_row = "";
    }

    // wysiwyg editor
    $output .= _admin_wysiwyg();

    // stylove oddeleni individualniho nastaveni
    if ($custom_settings != "") {
        $custom_settings = "<span class='customsettings'>" . $custom_settings . "</span>";
    }

    // formular
    $output .= "<div class='hr'><hr /></div><br />" . (isset($_GET['saved']) ? _formMessage(1, $_lang['global.saved'] . "&nbsp;&nbsp;<small>(" . _formatTime(time()) . ")</small>") : '') . "

" . ((!$new && $type !=4 && DB::result(DB::query('SELECT COUNT(*) FROM `' . _mysql_prefix . '-root` WHERE `id`!=' . $query['id'] . ' AND `title_seo`=\'' . $query['title_seo'] . '\''), 0) != 0) ? _formMessage(2, $_lang['admin.content.form.title_seo.collision']) : '') . "
" . ((!$new && $id == _index_page_id) ? _admin_smallNote($_lang['admin.content.form.indexnote']) : '') . "
<form" . (($type != 4) ? " class='cform'" : '') . " action='index.php?p=content-edit" . $type_array[$type] . (!$new ? "&amp;id=" . $id : '') . (($type == 9 && $new) ? '&amp;idt=' . $type_idt : '') . "' method='post'>


" . $editscript_extra . "
" . ((!$new && $type == 5) ? "<p><a href='index.php?p=content-manageimgs&amp;g=" . $id . "'><img src='images/icons/edit.png' alt='edit' class='icon' /><big>" . $_lang['admin.content.form.manageimgs'] . " &gt;</big></a></p>" : '') . "

<table class='formtable'>

<tr>
<td class='rpad'><strong>" . $_lang['admin.content.form.title'] . "</strong></td>
<td><input type='text' name='title' value='" . $query['title'] . "' class='inputmedium' maxlength='96' /></td>

" . (($type != 4) ? "<td class='rpad'><strong>" . $_lang['admin.content.form.title_seo'] . "</strong></td>
<td><input type='text' name='title_seo' value='" . $query['title_seo'] . "' maxlength='255' class='inputmedium' /></td>" : $intersection_row) . "
</tr>

<tr>
<td class='rpad'><strong>" . $_lang['admin.content.form.ord'] . "</strong></td>
<td><input type='text' name='ord' value='" . $query['ord'] . "' class='inputmedium' /></td>

" . (($type != 4) ? $intersection_row : '') . "
</tr>

" . (($type != 4) ? "
<tr>
<td class='rpad'><strong>" . $_lang['admin.content.form.description'] . "</strong></td>
<td><input type='text' name='description' value='" . $query['description'] . "' maxlength='128' class='inputmedium' /></td>

<td class='rpad'><strong>" . $_lang['admin.content.form.keywords'] . "</strong></td>
<td><input type='text' name='keywords' value='" . $query['keywords'] . "' maxlength='128' class='inputmedium' /></td>
</tr>

<tr class='valign-top'>
<td class='rpad'><strong>" . $_lang['admin.content.form.intersectionperex'] . "</strong></td>
<td colspan='3'><textarea name='intersectionperex' rows='2' cols='94' class='arealine codemirror'>" . _htmlStr($query['intersectionperex']) . "</textarea></td>
</tr>

" . ($editscript_enable_content ? "
<tr class='valign-top'>
<td class='rpad'><strong>" . $_lang['admin.content.form.' . (($type != 6) ? 'content' : 'url')] . "</strong>" . (!$new ? " <a href='" . _indexroot . _linkRoot($query['id'], $query['title_seo']) . "' target='_blank'><img src='images/icons/loupe.png' alt='prev' /></a>" : '') . "</td>
<td colspan='3'>
" . (($type != 6) ? "<textarea name='content' rows='25' cols='94' class='areabig wysiwyg_editor" . ((!_wysiwyg || !_loginwysiwyg) ? ' codemirror' : '') . "'>" . _htmlStr($query['content']) . "</textarea>" : "<input type='text' name='content' value='" . _htmlStr($query['content']) . "' class='inputbig' />") . "
</td>
</tr>
" : '') . "

" . $editscript_extra_row . "

<tr>
<td class='rpad'><strong>" . $_lang['admin.content.form.settings'] . "</strong></td>
<td colspan='3'>
<label><input type='checkbox' name='visible' value='1'" . _checkboxActivate($query['visible']) . " /> " . $_lang['admin.content.form.visible'] . "</label>&nbsp;&nbsp;
" . (($type != 6) ? "<label><input type='checkbox' name='autotitle' value='1'" . _checkboxActivate($query['autotitle']) . " /> " . $_lang['admin.content.form.autotitle'] . "</label>&nbsp;&nbsp;" : '') . "
" . $custom_settings . "
</td>
</tr>

<tr>
<td class='rpad'><strong>" . $_lang['global.access'] . "</strong></td>
<td>
<label><input type='checkbox' name='public' value='1'" . _checkboxActivate($query['public']) . " /> " . $_lang['admin.content.form.public'] . "</label>&nbsp;&nbsp;
<input type='text' name='level' value='" . $query['level'] . "' class='inputsmaller' maxlength='5' /> " . $_lang['admin.content.form.level'] . "
</td>

" . (($type != 4) ? "<td class='rpad'><strong>" . $_lang['admin.content.form.events'] . "</strong></td>
<td><input type='text' name='events' value='" . (isset($query['events']) ? _htmlStr($query['events']) : '') . "' class='inputmedium' maxlength='255' /></td>" : '') . "
</tr>

" : '') . "


<tr><td></td><td colspan='3'><br />
<input type='submit' value='" . ($new ? $_lang['global.create'] : $_lang['global.savechanges']) . "' />" . (!$new ? "&nbsp;&nbsp;<small>" . $_lang['admin.content.form.thisid'] . " " . $query['id'] . "</small>" : '') . "
</td></tr>

</table>
" . _xsrfProtect() . "</form>
";

}
