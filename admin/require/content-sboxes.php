<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  priprava a akce  --- */
$message = "";
if (isset($_POST['action'])) {

    switch ($_POST['action']) {

            // vytvoreni
        case 1:
            // nacteni zakladnich promennych
            $title = DB::esc(_htmlStr($_POST['title']));
            $public = _checkboxLoad("public");
            $locked = _checkboxLoad("lockedc");

            // vlozeni
            DB::query("INSERT INTO `" . _mysql_prefix . "-sboxes` (title,locked,public) VALUES('" . $title . "'," . $locked . "," . $public . ")");
            $message = _formMessage(1, $_lang['global.created']);
            break;

            // ulozeni
        case 2:
            $lastid = -1;
            $sql = "";
            foreach ($_POST as $var => $val) {
                if ($var == "action") {
                    continue;
                }
                $var = @explode("_", $var);
                if (count($var) == 2) {
                    $id = intval(mb_substr($var[0], 1));
                    $var = DB::esc($var[1]);
                    if ($lastid == -1) {
                        $lastid = $id;
                    }
                    $quotes = "'";
                    $skip = false;
                    switch ($var) {
                        case "title":
                            $val = DB::esc(_htmlStr(trim($val)));
                            break;
                        case "lockedtrigger":
                            $var = "locked";
                            $val = _checkboxLoad("s" . $id . "_locked");
                            $quotes = '';
                            break;
                        case "publictrigger":
                            $var = "public";
                            $val = _checkboxLoad("s" . $id . "_public");
                            $quotes = '';
                            break;
                        case "delposts":
                            $skip = true;
                            DB::query("DELETE FROM `" . _mysql_prefix . "-posts` WHERE home=" . $id . " AND type=4");
                            break;
                        default:
                            $skip = true;
                            break;
                    }

                    // ukladani a cachovani
                    if (!$skip) {

                        // ulozeni
                        if ($lastid != $id) {
                            $sql = trim($sql, ",");
                            DB::query("UPDATE `" . _mysql_prefix . "-sboxes` SET " . $sql . " WHERE id=" . $lastid);
                            $sql = "";
                            $lastid = $id;
                        }

                        $sql .= $var . "=" . $quotes . $val . $quotes . ",";
                    }

                }
            }

            // ulozeni posledniho nebo jedineho shoutboxu
            if ($sql != "") {
                $sql = trim($sql, ",");
                DB::query("UPDATE `" . _mysql_prefix . "-sboxes` SET " . $sql . " WHERE id=" . $id);
            }

            $message = _formMessage(1, $_lang['global.saved']);
            break;

    }

}

/* ---  odstraneni shoutboxu  --- */
if (isset($_GET['del']) && _xsrfCheck(true)) {
    $del = intval($_GET['del']);
    DB::query("DELETE FROM `" . _mysql_prefix . "-sboxes` WHERE id=" . $del);
    DB::query("DELETE FROM `" . _mysql_prefix . "-posts` WHERE home=" . $del . " AND type=4");
    $message = _formMessage(1, $_lang['global.done']);
}

/* ---  vystup  --- */
$output .= "
<p class='bborder'>" . $_lang['admin.content.sboxes.p'] . "</p>

" . $message . "

<fieldset>
<legend>" . $_lang['admin.content.sboxes.create'] . "</legend>
<form class='cform' action='index.php?p=content-sboxes' method='post'>
<input type='hidden' name='action' value='1' />

<table>

<tr>
<td class='rpad'><strong>" . $_lang['admin.content.form.title'] . "</strong></td>
<td><input type='text' name='title' class='inputbig' maxlength='64' /></td>
</tr>

<tr class='valign-top'>
<td class='rpad'><strong>" . $_lang['admin.content.form.settings'] . "</strong></td>
<td>
<label><input type='checkbox' name='public' value='1' checked='checked' /> " . $_lang['admin.content.form.unregpost'] . "</label><br />
<label><input type='checkbox' name='locked' value='1' /> " . $_lang['admin.content.form.locked2'] . "</label>
</td>
</tr>

<tr>
<td></td>
<td><input type='submit' value='" . $_lang['global.create'] . "' /></td>
</tr>

</table>

" . _xsrfProtect() . "</form>
</fieldset>


<fieldset>
<legend>" . $_lang['admin.content.sboxes.manage'] . "</legend>
<form class='cform' action='index.php?p=content-sboxes' method='post'>
<input type='hidden' name='action' value='2' />

<input type='submit' value='" . $_lang['admin.content.sboxes.savechanges'] . "' />
<div class='hr'><hr /></div>
";

// vypis shoutboxu
$shoutboxes = DB::query("SELECT * FROM `" . _mysql_prefix . "-sboxes` ORDER BY id DESC");
if (DB::size($shoutboxes) != 0) {
    while ($shoutbox = DB::row($shoutboxes)) {

        $output .= "
    <br />
    <table>

    <tr>
    <td class='rpad'><strong>" . $_lang['admin.content.form.title'] . "</strong></td>
    <td><input type='text' name='s" . $shoutbox['id'] . "_title' class='inputmedium' value='" . $shoutbox['title'] . "' /></td>
    </tr>

    <tr>
    <td><strong>" . $_lang['global.id'] . "</strong></td>
    <td>" . $shoutbox['id'] . "</td>
    </tr>

    <tr class='valign-top'>
    <td class='rpad'><strong>" . $_lang['admin.content.form.settings'] . "</strong></td>
    <td>
    <input type='hidden' name='s" . $shoutbox['id'] . "_publictrigger' value='1' /><input type='hidden' name='s" . $shoutbox['id'] . "_lockedtrigger' value='1' />
    <label><input type='checkbox' name='s" . $shoutbox['id'] . "_public' value='1'" . _checkboxActivate($shoutbox['public']) . " /> " . $_lang['admin.content.form.unregpost'] . "</label><br />
    <label><input type='checkbox' name='s" . $shoutbox['id'] . "_locked' value='1'" . _checkboxActivate($shoutbox['locked']) . " /> " . $_lang['admin.content.form.locked2'] . "</label><br />
    <label><input type='checkbox' name='s" . $shoutbox['id'] . "_delposts' value='1' /> " . $_lang['admin.content.form.delposts'] . "</label><br /><br />
    <a href='" . _xsrfLink("index.php?p=content-sboxes&amp;del=" . $shoutbox['id']) . "' onclick='return _sysConfirm();'><img src='images/icons/delete.png' alt='del' class='icon' />" . $_lang['global.delete'] . "</a>
    </td>
    </tr>

    </table>
    <br /><div class='hr'><hr /></div>
    ";
    }
} else {
    $output .= $_lang['global.nokit'];
}

$output .= "
" . _xsrfProtect() . "</form>
</fieldset>

";
