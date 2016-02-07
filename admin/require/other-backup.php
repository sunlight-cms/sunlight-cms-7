<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

require_once _indexroot . 'admin/functions-backup.php';
$backup_dir = _indexroot . 'data/backup/';

/* ---  vystup  --- */
$output .= '<br />';

if (isset($_POST['do_backup']) && _loginright_adminbackup) {

    /* ----- tvorba zalohy ----- */

    // typ zalohy (0 = db, 1 = partial, 2 = full)
    if (isset($_POST['type_full'])) {
        $type = _backup_full;
        $type_name = 'full';
    } elseif (isset($_POST['type_partial'])) {
        $type = _backup_partial;
        $type_name = 'partial';
    } else {
        $type = _backup_db;
        $type_name = 'db';
    }
    $type_ext = _backupExt($type);

    // nazev souboru
    $fname = @str_replace('.', '_', _getDomain());
    if ($fname == '') $fname = _mysql_db;
    $fname .= '_' . date("Y_m_d");

    // komprese
    $can_compress = extension_loaded('zlib');
    $memlimit = _phpIniLimit('memory_limit');
    $should_compress = (!isset($memlimit) || $memlimit > 10485760);

    // velikosti
    $sizes = array();

    // velikost databaze
    $sizes['db'] = 10240; // +- 10kB struktura databaze
    $q = DB::query('SHOW TABLE STATUS LIKE \'' . _mysql_prefix . '-%\'');
    while($r = DB::row($q)) $sizes['db'] += $r['Data_length'];

    // velikost slozek
    if ($type !== _backup_db) {
        if ($type === _backup_full) $dirs = array('admin', 'pictures', 'plugins', 'remote', 'require', 'upload');
        else $dirs = array('pictures', 'plugins', 'upload');
        foreach ($dirs as $dir_name) {
            $dir_size = 0;
            $scan = array(_indexroot . $dir_name . '/');
            for ($ii = 0; isset($scan[$ii]); ++$ii) {
                $handle = opendir($scan[$ii]);
                while (false !== ($item = readdir($handle))) {
                    if ($item === '.' || $item === '..') continue;
                    if (is_dir($scan[$ii] . $item)) $scan[] = $scan[$ii] . $item . '/';
                    else $dir_size += filesize($scan[$ii] . $item);
                }
                closedir($handle);
            }
            $sizes[$dir_name] = $dir_size;
        }
    }

    // secist velikosti
    $sizes['sum'] = array_sum($sizes);
    if ($sizes['sum'] <= 10485760) $sizes_sum_class = 'green'; // <= 10MB
    elseif ($sizes['sum'] < 20971520) $sizes_sum_class = 'orange'; // < 20MB
    else $sizes_sum_class = 'red'; // >= 20MB

    // vypnout vychozi kompresi
    if ($should_compress && $sizes['sum'] >= 6291456 && ini_get('max_execution_time') < 60) $should_compress = false; // >= 6MB

    // formatovat velikosti
    $raw_sizes = array('sum' => 0);
    foreach ($sizes as $key => $size) {
        if ($size <= 1048576) $class = 'green'; // <= 1MB
        elseif ($size < 4194304) $class = 'orange'; // < 4MB
        else $class = 'red'; // >= 5MB
        $size_formatted = number_format($size / 1024, 0, '.', ' ') . 'kB';
        if (isset($raw_sizes[$key])) $sizes[$key] = $size_formatted;
        else $sizes[$key] = '<code class="text-' . $class . '">' . $size_formatted . '</code>';
    }

    // vygenerovat vyberu slozek
    $dir_items = '';
    if ($type !== _backup_db) {
        $dirs_optional = array('upload' => 0);
        foreach($dirs as $dir_name) $dir_items .= '<label><input type="checkbox" name="dir_' . $dir_name . '" value="1" checked="checked"' . _inputDisable(isset($dirs_optional[$dir_name])) . ' /> ' . $_lang['admin.other.backup.backup.items.dir'] . ' <code>' . $dir_name . '</code> - ' . $sizes[$dir_name] . '</label><br />' . _nl;
    }

    // formular
    $output .= "
<form method='post' action='remote/backup.php' target='_blank' onsubmit=\"setTimeout(function(){window.location = 'index.php?p=other-backup';}, 1000);\">
<input type='hidden' name='type' value='" . $type . "' />

<p class='bborder'>" . $_lang['admin.other.backup.backup.' . $type_name . '.info'] . "</p>

<table class='formtable'>

<tr>
    <td><strong>" . $_lang['admin.other.backup.backup.type'] . "</strong></td>
    <td>" . $_lang['admin.other.backup.backup.' . $type_name] . "&nbsp; <small class='note'>(" . $_lang['admin.other.backup.backup.' . $type_name . '.hint'] . ")</small></td>
</tr>

<tr>
    <td><strong>" . $_lang['admin.other.backup.backup.fname'] . "</strong></td>
    <td><input type='text' name='fname' class='inputmedium' value='$fname' /><em>.$type_ext</em></td>
</tr>

<tr>
    <td><strong>" . $_lang['global.note'] . "</strong></td>
    <td><input type='text' name='note' class='inputmedium' maxlength='48' /></td>
</tr>

<tr>
    <td><strong>" . $_lang['admin.other.backup.backup.compress'] . "</strong></td>
    <td><select class='inputmedium' name='compress'>
        <option value='0'>" . $_lang['admin.other.backup.backup.compress.0'] . "</option>
        <option value='1'" . (($can_compress && $should_compress) ? " selected='selected'" : '') . _inputDisable($can_compress) . ">" . $_lang['admin.other.backup.backup.compress.1'] . (!$should_compress ? ' (' . $_lang['global.notrecommended'] . ')' : '') . "</option>
        <option value='2'" . _inputDisable($can_compress) . ">" . $_lang['admin.other.backup.backup.compress.2'] . (!$should_compress ? ' (' . $_lang['global.notrecommended'] . ')' : '') . "</option>
    </select></td>
</tr>

<tr class='valign-top'>
    <td><strong>" . $_lang['admin.other.backup.backup.items'] . "</strong></td>
    <td>
        <label><input type='checkbox' name='item_database' disabled='disabled' checked='checked' /> " . $_lang['admin.other.backup.backup.items.db'] . ' - ' . $sizes['db'] . "</label><br />
        " . $dir_items . "
    </td>
</tr>

<tr>
    <td><strong>" . $_lang['admin.other.backup.backup.sizesum'] . "</strong></td>
    <td><code class='text-" . $sizes_sum_class . "'>" . $sizes['sum'] . '</code>' . ($can_compress ? ' <small class="note">(' . $_lang['admin.other.backup.backup.sizesum.note'] . ')</small>' : '') . "</td>
</tr>

<tr>
    <td></td>
    <td>
        <br />
        <input type='submit' name='target_down' value='" . $_lang['admin.other.backup.backup.submit.down'] . "' />
        " . (($type !== _backup_full) ? "<input type='submit' name='target_store' value='" . $_lang['admin.other.backup.backup.submit.store'] . "' />" : '') . "
        &nbsp;&nbsp;<a href='index.php?p=other-backup'><img src='images/icons/delete2.png' alt='cancel' class='icon' />" . $_lang['global.cancel'] . "</a>
    </td>
</tr>

</table>
" . _xsrfProtect() . "</form>
";

} elseif (isset($_POST['do_restore']) && _loginright_adminrestore) {

    /* ----- obnova zalohy ----- */

    $msg = '';

    // akce
    if (isset($_POST['action'])) {

        switch ($_POST['action']) {

                // upload
            case 1:

                // kontrola nahrani
                if (!isset($_FILES['backup']) || !is_uploaded_file($_FILES['backup']['tmp_name'])) {
                    $msg = _formMessage(2, $_lang['global.noupload']);
                    break;
                }

                // nazev souboru
                $fname = _anchorStr($_FILES['backup']['name']);
                if (($dot = strrpos($fname, '.')) !== false) $fname = substr($fname, 0, $dot) . '_' . uniqid('', false) . substr($fname, $dot);
                else $fname .= '_' . uniqid('', false);
                $move_to = _indexroot . 'data/backup/' . $fname;

                // kontrola souboru
                if (($check = _backupCheckFile($_FILES['backup']['tmp_name'], array(_backup_db, _backup_partial))) !== true) {
                    $msg = _formMessage(3, $check);
                    break;
                }

                // presun souboru
                if (!@move_uploaded_file($_FILES['backup']['tmp_name'], $move_to)) {
                    $msg = _formMessage(1, $_lang['admin.other.backup.restore.upload.err.move']);
                    break;
                }

                // ok
                $msg = _formMessage(1, $_lang['admin.other.backup.restore.upload.ok']);
                break;

                // akce se zalohou
            case 2:

                // kontrola vyberu
                if (!isset($_POST['fname'])) {
                    $msg = _formMessage(2, $_lang['global.noselect']);
                    break;
                }

                // kontrola souboru
                $fname = $backup_dir . basename($_POST['fname']);
                if (!file_exists($fname)) {
                    $msg = _formMessage(2, $_lang['global.badinput']);
                    break;
                }

                // akce
                switch ($_POST['sub_action']) {

                        // obnova
                    case 1:

                        $restore = _backupRestore($fname);
                        if ($restore === true) $msg = _formMessage(1, $_lang['admin.other.backup.restore.success']);
                        else {
                            if ($restore[1]) $msg .= _formMessage(3, $_lang['admin.other.backup.restore.err.fatal']);
                            $msg .= _formMessage(2, $restore[0]);
                        }

                        // obnoveni nebo fatal - ukoncit zbytek modulu
                        if ($restore === true || $restore[1]) {
                            $output .= $msg;

                            return;
                        }
                        break;

                        // smazani
                    case 2:
                        if (unlink($fname)) $msg = _formMessage(1, $_lang['global.done']);
                        else $msg = _formMessage(2, $_lang['global.fileerr']);
                        break;

                        // nezvoleno
                    default:
                        $msg = _formMessage(2, $_lang['global.noaction']);
                        break;

                }

                break;

        }

    }

    // formulare
    $output .= "
<p class='bborder'>" . $_lang['admin.other.backup.restore.info'] . "</p>
<p><a href='index.php?p=other-backup'><img src='images/icons/delete2.png' alt='cancel' class='icon' />" . $_lang['global.cancel2'] . "</a></p>

" . $msg . "
<form method='post' enctype='multipart/form-data' action='index.php?p=other-backup'>
<fieldset>
<legend>1) " . $_lang['admin.other.backup.restore.upload'] . "</legend>

<input type='hidden' name='action' value='1' />
<input type='hidden' name='do_restore' value='1' />

<p>
    <input type='file' name='backup' />
    <input type='submit' value='" . $_lang['admin.other.backup.restore.upload.submit'] . "' /> " . ((($uplimit = _getUploadLimit(true)) !== null) ? "<small>(" . $_lang['global.uploadlimit'] . ": <em>" . $uplimit . "MB</em>)</small>" : '') . "
</p>

" . _admin_smallNote(str_replace('*dir*', 'data/backup/', $_lang['admin.other.backup.restore.upload.hint']), true) . "

</fieldset>
" . _xsrfProtect() . "</form>

<br />

<form method='post' action='index.php?p=other-backup'>
<fieldset>
<legend>2) " . $_lang['admin.other.backup.restore.use'] . "</legend>

<input type='hidden' name='action' value='2' />
<input type='hidden' name='do_restore' value='1' />
";

    // nacteni zaloh
    $backups = array();
    $handle = opendir($backup_dir);
    while (false !== ($item = readdir($handle))) {
        if ($item === '.' || $item === '..' || !is_file($backup_dir . $item)) continue;
        $backups[] = $item;
    }

    // serazeni a vypis
    if (!empty($backups)) {

        natsort($backups);
        $output .= "<table class='list'>
<thead><tr><td>" . $_lang['admin.other.backup.restore.item'] . "</td><td>" . $_lang['global.type'] . "</td><td>" . $_lang['global.time'] . "</td><td>" . $_lang['global.note'] . "</td><td>" . $_lang['global.size'] . "</td><td>" . $_lang['global.extra'] . "</td></tr></thead>
<tbody>\n";

        foreach ($backups as $file) {

            // info o archivu
            $file_err = false;
            $file_vars = _backupCheckFile($backup_dir . $file, array(_backup_db, _backup_partial), true);
            if (!is_array($file_vars)) $file_err = true;
            $file_h = _htmlStr($file);

            // polozka
            $output .= "<tr>
<td><label><input type='radio' name='fname' value='" . $file_h . "'" . ($file_err ? " disabled='disabled'" : '') . " /> " . $file_h . "</label></td>
<td>" . ($file_err ? '-' : $_lang['admin.other.backup.backup.' . (($file_vars['type'] === _backup_db) ? 'db' : 'partial')]) . "</td>
<td>" . ($file_err ? '-' : _formatTime($file_vars['time'])) . "</td>
<td>" . ($file_err ? "<img src='images/icons/warn.png' alt='err' /> " . $_lang['global.error'] : (empty($file_vars['note']) ? '-' : _htmlStr(_cutStr($file_vars['note'], 48, false)))) . "</td>
<td>" . number_format(filesize($backup_dir . $file) / 1024, 0, '.', ' ') . "kB</td>
<td><a href='" . $backup_dir . $file_h . "'>" . $_lang['global.download'] . "</a></td>
</tr>\n";

        }

        $output .= "</tbody></table><br />

<p><strong>" . $_lang['global.action'] . ":</strong> &nbsp;
<select name='sub_action'>
    <option value='-1'>...</option>
    <option value='1'>" . $_lang['admin.other.backup.restore.do'] . "</option>
    <option value='2'>" . $_lang['global.delete'] . "</option>
</select> &nbsp;
<input type='submit' value='" . $_lang['global.do'] . "' onclick='return _sysConfirm();' />&nbsp;
<a href='index.php?p=fman&amp;dir=" . urlencode('../data/backup/') . "'><img src='images/icons/list.png' class='icon' alt='fman' />" . $_lang['admin.other.backup.restore.fman'] . "</a>
</p>

" . _admin_smallNote($_lang['admin.other.backup.restore.warning'], true, 'warn');

    } else {
        $output .= '<p>' . $_lang['admin.other.backup.restore.none'] . '</p>';
    }

    $output .= "
</fieldset>
" . _xsrfProtect() . "</form>
";

} else {

    /* ----- volba akce ----- */

    $output .= (_loginright_adminbackup ? "
<fieldset>
<legend>" . $_lang['admin.other.backup.backup'] . "</legend>
<form action='index.php?p=other-backup' method='post'>
<p>" . $_lang['admin.other.backup.backup.p'] . "</p>

<input type='hidden' name='do_backup' value='1' />
<p><input type='submit' value='" . $_lang['admin.other.backup.backup.db'] . "' name='type_db' />&nbsp; <small class='note'>(" . $_lang['admin.other.backup.backup.db.hint'] . ")</small></p>
<p><input type='submit' value='" . $_lang['admin.other.backup.backup.partial'] . "' name='type_partial' />&nbsp; <small class='note'>(" . $_lang['admin.other.backup.backup.partial.hint'] . ")</small></p>
<p><input type='submit' value='" . $_lang['admin.other.backup.backup.full'] . "' name='type_full' />&nbsp; <small class='note'>(" . $_lang['admin.other.backup.backup.full.hint'] . ")</small></p>

" . _xsrfProtect() . "</form>
</fieldset>" : '') . (_loginright_adminrestore ? "

<fieldset>
<legend>" . $_lang['admin.other.backup.restore'] . "</legend>
<form class='cform' method='post'>
<p>" . $_lang['admin.other.backup.restore.p'] . "</p>
<p><input type='submit' name='do_restore' value='" . $_lang['global.continue'] . "' /></p>
" . _xsrfProtect() . "</form>
</fieldset>
" : '');

}
