<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  priprava promennych  --- */
$message = "";
$continue = false;
if (isset($_GET['c'])) {
    $c = _get('c');
    $continue = true;
}

/* ---  ulozeni  --- */
if (isset($_POST['do'])) {

    // nacteni dat pro aktualizaci
    $update = array();
    foreach ($_POST as $var => $val) {
        if ($var != "do") {
            $var = @explode("-", $var);
            if (count($var) == 2) {

                // zpracovat
                $id = intval($var[0]);
                $var = $var[1];
                switch ($var) {
                    case "title":
                    case "class":
                        $val = _htmlStr(trim($val));
                        if ($var === 'class' && $val === '') $val = null;
                        break;
                    case "column":
                        $val = strval($val);
                        break;
                    case "ord":
                        $val = floatval($val);
                        break;
                    case "content":
                        $val = _filtrateHCM($val);
                        break;
                    case "visible":
                    case "public":
                        $val = _checkboxLoad($id . '-' . $var . 'new');
                        break;
                    default:
                        continue  2;
                }

                // pridat do pole
                if (!isset($update[$id])) $update[$id] = array();
                $update[$id][$var] = $val;

            }
        }
    }

    // aktualizace v db
    if (!empty($update)) {

        foreach ($update as $id => $changes) {
            $sql = 'UPDATE `' . _mysql_prefix . '-boxes` SET ';
            $counter = 0;
            foreach ($changes as $column => $value) {
                if ($counter !== 0) $sql .= ',';
                $sql .= '`' . $column . '`=' . (isset($value) ? (is_numeric($value) ? (0 + $value) : '\'' . DB::esc($value) . '\'') : 'NULL');
                ++$counter;
            }
            $sql .= ' WHERE `id`=' . $id;
            DB::query($sql);
        }

        $message = _formMessage(1, $_lang['global.saved']);

    }

}

/* ---  odstraneni  --- */
if (isset($_GET['del']) && _xsrfCheck(true)) {
    $del = intval($_GET['del']);
    if (DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-boxes` WHERE id=" . $del), 0) != 0) {
        DB::query("DELETE FROM `" . _mysql_prefix . "-boxes` WHERE id=" . $del);
        $message = _formMessage(1, $_lang['global.done']);
    } else {
        $message = _formMessage(2, $_lang['global.badinput']);
    }
}

/* ---  vystup  --- */
if ($continue) {

    $nokit = false;

    // zprava
    if (isset($_GET['created'])) {
        $message = _formMessage(1, $_lang['global.created']);
    }

    $output .= "<div class='hr'><hr /></div><br />" . $message . "
<form class='cform' action='index.php?p=content-boxes-edit&amp;c=" . urlencode($c) . "&amp;saved' method='post'>
<input type='hidden' name='do' value='1' />
<p><input type='submit' value='" . $_lang['admin.content.boxes.saveboxeschanges'] . "' />&nbsp;&nbsp;&nbsp;&nbsp;<a href='index.php?p=content-boxes-new&amp;c=" . urlencode($c) . "'><img src='images/icons/new.png' alt='new' class='icon' />" . $_lang['admin.content.boxes.create'] . "</a></p>
<table id='boxesedit'>
";

    $query = DB::query("SELECT * FROM `" . _mysql_prefix . "-boxes` WHERE `column`='" . DB::esc($c) . "' ORDER BY ord");
    if (DB::size($query) != 0) {
        $isfirst = true;
        while ($item = DB::row($query)) {
            if ($isfirst) {
                $output .= "\n\n\n\n<tr>\n\n\n\n";
            }
            $output .= "
    <td class='cell'>
    <div>
    <table class='formtable'>

    <tr>
    <td class='rpad'><strong>" . $_lang['admin.content.form.title'] . "</strong></td>
    <td><input type='text' name='" . $item['id'] . "-title' value='" . $item['title'] . "' class='inputmedium' maxlength='96' /></td>
    </tr>

    <tr>
    <td class='rpad'><strong>" . $_lang['admin.content.boxes.column'] . "</strong></td>
    <td><input type='text' maxlength='64' name='" . $item['id'] . "-column' value='" . _htmlStr($item['column']) . "' class='inputmedium' /></td>
    </tr>

    <tr>
    <td class='rpad'><strong>" . $_lang['admin.content.form.ord'] . "</strong></td>
    <td><input type='text' name='" . $item['id'] . "-ord' value='" . $item['ord'] . "' class='inputmedium' /></td>
    </tr>

    <tr>
    <td class='rpad'><strong>" . $_lang['admin.content.form.class'] . "</strong></td>
    <td><input type='text' name='" . $item['id'] . "-class' value='" . $item['class'] . "' class='inputmedium' maxlength='24' /></td>
    </tr>

    <tr class='valign-top'>
    <td class='rpad'><strong>" . $_lang['admin.content.form.content'] . "</strong></td>
    <td><textarea name='" . $item['id'] . "-content' class='areasmall_100pwidth codemirror' rows='9' cols='33'>" . _htmlStr($item['content']) . "</textarea></td>
    </tr>

    <tr>
    <td class='rpad'><strong>" . $_lang['admin.content.form.settings'] . "</strong></td>
    <td>
    <label><input type='checkbox' name='" . $item['id'] . "-visiblenew' value='1'" . _checkboxActivate($item['visible']) . " /> " . $_lang['admin.content.form.visible'] . "</label>&nbsp;&nbsp;
    <label><input type='checkbox' name='" . $item['id'] . "-publicnew' value='1'" . _checkboxActivate($item['public']) . " /> " . $_lang['admin.content.form.public'] . "</label>
    <input type='hidden' name='" . $item['id'] . "-visible' value='1' />
    <input type='hidden' name='" . $item['id'] . "-public' value='1' />
    &nbsp;&nbsp;&nbsp;&nbsp;<a href='" . _xsrfLink("index.php?p=content-boxes-edit&amp;c=" . urlencode($c) . "&amp;del=" . $item['id']) . "' onclick='return _sysConfirm();'><img src='images/icons/delete.png' alt='del' class='icon' />" . $_lang['admin.content.boxes.delete'] . "</a>
    </td>
    </tr>

    </table>
    </div>
    </td>
    ";
            if (!$isfirst) {
                $output .= "\n\n\n\n</tr>\n\n\n\n";
            }
            $isfirst = !$isfirst;
        }

        // dodatecne uzavreni radku tabulky (pri lichem poctu boxu)
        if (!$isfirst) {
            $output .= "\n\n\n\n</tr>\n\n\n\n";
        }
    } else {
        $nokit = true;
        $output .= '<tr><td>' . $_lang['global.nokit'] . '</td></tr>';
    }

    $output .= "</table>
" . ($nokit ? '' : "<p><input type='submit' value='" . $_lang['admin.content.boxes.saveboxeschanges'] . "' /></p>") . "
" . _xsrfProtect() . "</form>";

} else {
    $output .= _formMessage(3, $_lang['global.badinput']);
}
