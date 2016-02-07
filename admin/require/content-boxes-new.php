<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  priprava  --- */
if (isset($_GET['c'])) {
    $c = _get('c');
    $returntolist = true;
} else {
    $c = '1';
    $returntolist = false;
}

/* ---  ulozeni  --- */
if (isset($_POST['title'])) {

    // nacteni promennych
    $title = DB::esc(_htmlStr($_POST['title']));
    $column = _post('column');
    $ord = floatval($_POST['ord']);
    $content = DB::esc(_filtrateHCM($_POST['content']));
    $visible = _checkboxLoad('visible');
    $public = _checkboxLoad('public');
    $class = trim($_POST['class']);
    if ($class === '') $class = null;
    else $class = DB::esc(_htmlStr($class));

    // vlozeni
    DB::query("INSERT INTO `" . _mysql_prefix . "-boxes` (ord,title,content,visible,public,`column`,class) VALUES (" . $ord . ",'" . $title . "','" . $content . "'," . $visible . "," . $public . ",'" . DB::esc($column) . "'," . (isset($class) ? '\'' . $class . '\'' : 'NULL') . ")");
    define('_redirect_to', 'index.php?p=content-boxes-edit&c=' . urlencode($column) . '&created');

    return;

}

/* ---  vystup  --- */
$output .= "
<a href='index.php?p=" . ($returntolist ? "content-boxes-edit&amp;c=" . urlencode($c) : "content-boxes") . "' class='backlink'>&lt; " . $_lang['global.return'] . "</a>
<h1>" . $_lang['admin.content.boxes.new.title'] . "</h1>
<p class='bborder'></p>

<form class='cform' action='index.php?p=content-boxes-new&amp;c=" . urlencode($c) . "' method='post'>

<table class='formtable'>

<tr>
<td class='rpad'><strong>" . $_lang['admin.content.form.title'] . "</strong></td>
<td><input type='text' name='title' class='inputmedium' maxlength='96' /></td>
</tr>

<tr>
<td class='rpad'><strong>" . $_lang['admin.content.boxes.column'] . "</strong></td>
<td><input type='text' maxlength='64' name='column' value='" . _htmlStr($c) . "' class='inputmedium' /></td>
</tr>

<tr>
<td class='rpad'><strong>" . $_lang['admin.content.form.ord'] . "</strong></td>
<td><input type='text' name='ord' value='1' class='inputmedium' /></td>
</tr>

<tr>
<td class='rpad'><strong>" . $_lang['admin.content.form.class'] . "</strong></td>
<td><input type='text' name='class' class='inputmedium' maxlength='24' /></td>
</tr>

<tr class='valign-top'>
<td class='rpad'><strong>" . $_lang['admin.content.form.content'] . "</strong></td>
<td><textarea name='content' class='areasmall_100pwidth codemirror' rows='9' cols='33'></textarea></td>
</tr>

<tr>
<td class='rpad'><strong>" . $_lang['admin.content.form.settings'] . "</strong></td>
<td>
<label><input type='checkbox' name='visible' value='1' checked='checked' /> " . $_lang['admin.content.form.visible'] . "</label>&nbsp;&nbsp;
<label><input type='checkbox' name='public' value='1' checked='checked' /> " . $_lang['admin.content.form.public'] . "</label>
</td>
</tr>

<tr>
<td></td>
<td><input type='submit' value='" . $_lang['global.create'] . "' /></td>
</tr>

</table>

" . _xsrfProtect() . "</form>

";
