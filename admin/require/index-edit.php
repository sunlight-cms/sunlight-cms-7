<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  zpracovani ulozeni  --- */
if (isset($_POST['text'])) {

    DB::query('UPDATE `' . _mysql_prefix . '-settings` SET `val`=\'' . DB::esc(trim($_POST['text'])) . '\' WHERE `var`=\'.admin_index_custom\'');
    DB::query('UPDATE `' . _mysql_prefix . '-settings` SET `val`=\'' . (($_POST['pos'] == 0) ? '0' : '1') . '\' WHERE `var`=\'.admin_index_custom_pos\'');
    define('_redirect_to', 'index.php?p=index-edit&saved');

    return;

}

/* ---  vystup  --- */

$output .= "

<p class='bborder'>" . $_lang['admin.menu.index.edit.p'] . "</p>

" . _admin_wysiwyg() . "
" . (isset($_GET['saved']) ? _formMessage(1, $_lang['global.saved']) : '') . "

<form action='' method='post'>

<table class='formtable'>

<tr>
    <td class='rpad'><strong>" . $_lang['admin.menu.index.edit.pos'] . "</strong></td>
    <td><select name='pos'>
        <option value='0'" . ((SL::$settings['admin_index_custom_pos'] == 0) ? " selected='selected'" : '') . ">" . $_lang['admin.menu.index.edit.pos.0'] . "</option>
        <option value='1'" . ((SL::$settings['admin_index_custom_pos'] == 1) ? " selected='selected'" : '') . ">" . $_lang['admin.menu.index.edit.pos.1'] . "</option>
    </select></td>
</tr>

<tr class='valign-top'>
    <td class='rpad'><strong>" . $_lang['admin.menu.index.edit.text'] . "</strong></td>
    <td class='minwidth'><textarea name='text' rows='25' cols='94' class='areabig wysiwyg_editor" . ((!_wysiwyg || !_loginwysiwyg) ? ' codemirror' : '') . "'>" . _htmlStr(SL::$settings['admin_index_custom']) . "</textarea></td>
</tr>

<tr>
    <td></td>
    <td><input type='submit' value='" . $_lang['global.savechanges'] . "' /></td>
</tr>

</table>

" . _xsrfProtect() . "</form>
";
