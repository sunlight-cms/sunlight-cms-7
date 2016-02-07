<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  pripava  --- */
$message = "";

function _tmp_selectTime($name)
{
    global $_lang;
    $opts = array(1, 2, 4, 8, 25, 52, 104);
    $active = (isset($_POST[$name]) ? intval($_POST[$name]) : 25);
    $output = "<select name='" . $name . "'>\n";
    for($i = 0; isset($opts[$i]); ++$i) $output .= "<option value='" . $opts[$i] . "'" . (($active === $opts[$i]) ? " selected='selected'" : '') . ">" . $_lang['admin.other.cleanup.time.' . $opts[$i]] . "</option>\n";
    $output .= "</select>\n";

    return $output;
}

/* ---  akce  --- */
if (isset($_POST['action'])) {

    switch ($_POST['action']) {

            // cistka
        case 1:

            // nahled ci smazani?
            if (isset($_POST['do_cleanup'])) $prev = false;
            else {
                $prev = true;
                $prev_count = array();
            }

            // vzkazy
            $messages = $_POST['messages'];
            switch ($messages) {

                case 1:
                    $messages_time = time() - ($_POST['messages-time'] * 7 * 24 * 60 * 60);
                    if ($prev) $prev_count['mod.messages'] = DB::count(_mysql_prefix . '-pm', 'update_time<' . $messages_time);
                    else DB::query("DELETE `" . _mysql_prefix . "-pm`,post FROM `" . _mysql_prefix . "-pm` LEFT JOIN `" . _mysql_prefix . "-posts` AS post ON (post.type=6 AND post.home=`" . _mysql_prefix . "-pm`.id) WHERE update_time<" . $messages_time);
                    break;

                case 2:
                    if ($prev) $prev_count['mod.messages'] = DB::count(_mysql_prefix . '-posts', 'type=6');
                    else {
                        DB::query("TRUNCATE TABLE `" . _mysql_prefix . "-pm`");
                        DB::query("DELETE FROM `" . _mysql_prefix . "-posts` WHERE type=6");
                    }
                    break;

            }

            // komentare, prispevky, iplog
            if (_checkboxLoad("comments")) {
                if ($prev) $prev_count['admin.settings.mods.comments'] = DB::count(_mysql_prefix . '-posts', 'type=1 OR type=2');
                else DB::query("DELETE FROM `" . _mysql_prefix . "-posts` WHERE type=1 OR type=2");
            }
            if (_checkboxLoad("posts")) {
                if ($prev) $prev_count['global.posts'] = DB::count(_mysql_prefix . '-posts', 'type IN(3,4,5)');
                else DB::query("DELETE FROM `" . _mysql_prefix . "-posts` WHERE type IN(3,4,5)");
            }
            if (_checkboxLoad("iplog")) {
                if ($prev) $prev_count['admin.settings.iplog2'] = DB::count(_mysql_prefix . '-iplog');
                else DB::query("TRUNCATE TABLE `" . _mysql_prefix . "-iplog`");
            }

            // uzivatele
            if (_checkboxLoad("users")) {

                $users_time = time() - ($_POST['users-time'] * 7 * 24 * 60 * 60);
                $users_group = intval($_POST['users-group']);
                if ($users_group == -1) $users_group = "";
                else $users_group = " AND `group`=" . $users_group;

                if ($prev) $prev_count['admin.users.users'] = DB::count(_mysql_prefix . '-users', 'id!=0 AND activitytime<' . $users_time . $users_group);
                else {
                    $userids = DB::query("SELECT id FROM `" . _mysql_prefix . "-users` WHERE id!=0 AND activitytime<" . $users_time . $users_group);
                    while($userid = DB::row($userids)) _deleteUser($userid['id']);
                    DB::free($userids);
                }

            }

            // udrzba
            if (_checkboxLoad('maintenance') && !$prev) {
                SL::doMaintenance();
            }

            // optimalizace
            if (_checkboxLoad('optimize') && !$prev) {

                $tables = array();
                $q = DB::query('SHOW TABLES LIKE \'' . _mysql_prefix . '-%\'');
                while($r = DB::rown($q)) DB::query('OPTIMIZE TABLE `' . $r[0] . '`');

            }

            // zprava
            if ($prev) {
                if (empty($prev_count)) {
                    $message = _formMessage(2, $_lang['global.noaction']);
                    break;
                }
                $message = "<br /><ul>\n";
                foreach($prev_count as $key => $val) $message .= "<li><strong>" . $_lang[$key] . ":</strong> <code>" . $val . "</code></li>\n";
                $message .= "</ul>";
            } else {
                $message = _formMessage(1, $_lang['global.done']);
            }

            break;

            // deinstalace
        case 2:
            $pass = $_POST['pass'];
            $confirm = _checkboxLoad("confirm");
            if ($confirm) {
                $right_pass = DB::query_row("SELECT password,salt FROM `" . _mysql_prefix . "-users` WHERE id=0");
                if (_md5Salt($pass, $right_pass['salt']) == $right_pass['password']) {

                    // ziskani tabulek
                    $tables = array();
                    $q = DB::query('SHOW TABLES LIKE \'' . _mysql_prefix . '-%\'');
                    while($r = DB::rown($q)) $tables[] = $r[0];

                    // odstraneni tabulek
                    foreach($tables as $table) DB::query("DROP TABLE `" . $table . "`");

                    // zprava
                    _userLogout();
                    echo "<h1>" . $_lang['global.done'] . "</h1>\n<p>" . $_lang['admin.other.cleanup.uninstall.done'] . "</p>";
                    exit;

                } else {
                    $message = _formMessage(2, $_lang['admin.other.cleanup.uninstall.badpass']);
                }
            }
            break;

    }

}

/* ---  vystup  --- */
$output .= $message . "
<br />
<fieldset>
<legend>" . $_lang['admin.other.cleanup.cleanup'] . "</legend>
<form class='cform' action='index.php?p=other-cleanup' method='post'>
<input type='hidden' name='action' value='1' />
<p>" . $_lang['admin.other.cleanup.cleanup.p'] . "</p>

<table>
<tr class='valign-top'>

<td rowspan='2'>
  <fieldset>
  <legend>" . $_lang['mod.messages'] . "</legend>
  <label><input type='radio' name='messages' value='0'" . _checkboxActivate(!isset($_POST['messages']) || $_POST['messages'] == 0) . " /> " . $_lang['global.noaction'] . "</label><br />
  <label><input type='radio' name='messages' value='1'" . _checkboxActivate(isset($_POST['messages']) && $_POST['messages'] == 1) . " /> " . $_lang['admin.other.cleanup.messages.1'] . "</label> " . _tmp_selectTime("messages-time") . "<br />
  <label><input type='radio' name='messages' value='2'" . _checkboxActivate(isset($_POST['messages']) && $_POST['messages'] == 2) . " /> " . $_lang['admin.other.cleanup.messages.2'] . "</label>
  </fieldset>

  <fieldset>
  <legend>" . $_lang['admin.users.users'] . "</legend>
  <p class='bborder'><label><input type='checkbox' name='users' value='1'" . _checkboxActivate(isset($_POST['users'])) . " /> " . $_lang['admin.other.cleanup.users'] . "</label></p>
  <table>

  <tr>
  <td><strong>" . $_lang['admin.other.cleanup.users.time'] . "</strong></td>
  <td>" . _tmp_selectTime("users-time") . "</td>
  </tr>

  <tr>
  <td><strong>" . $_lang['admin.other.cleanup.users.group'] . "</strong></td>
  <td>" . _admin_authorSelect("users-group", (isset($_POST['users-group']) ? intval($_POST['users-group']) : -1), "1", null, $_lang['global.all'], true) . "</td>
  </tr>

  </table>
  </fieldset>
</td>

<td>
  <fieldset>
  <legend>" . $_lang['global.other'] . "</legend>
  <label><input type='checkbox' name='maintenance' value='1' checked='checked' /> " . $_lang['admin.other.cleanup.other.maintenance'] . "</label><br />
  <label><input type='checkbox' name='optimize' value='1' checked='checked' /> " . $_lang['admin.other.cleanup.other.optimize'] . "</label><br />
  <label><input type='checkbox' name='comments' value='1'" . _checkboxActivate(isset($_POST['comments'])) . " /> " . $_lang['admin.other.cleanup.other.comments'] . "</label><br />
  <label><input type='checkbox' name='posts' value='1'" . _checkboxActivate(isset($_POST['posts'])) . " /> " . $_lang['admin.other.cleanup.other.posts'] . "</label><br />
  <label><input type='checkbox' name='iplog' value='1'" . _checkboxActivate(isset($_POST['iplog'])) . " /> " . $_lang['admin.other.cleanup.other.iplog'] . "</label>
  </fieldset>
</td>

</tr>

<tr class='valign-top'>

<td align='center'><p>
<input type='submit' value='" . $_lang['admin.other.cleanup.prev'] . "' /><br /><br />
<input type='submit' name='do_cleanup' value='" . $_lang['admin.other.cleanup.do'] . "' onclick='return _sysConfirm();' />
</p></td>

</tr>

</table>

" . _xsrfProtect() . "</form>
</fieldset>
<br />

<fieldset>
<legend>" . $_lang['admin.other.cleanup.uninstall'] . "</legend>
<form class='cform' action='index.php?p=other-cleanup' method='post'>
<input type='hidden' name='action' value='2' />
<p class='bborder'>" . $_lang['admin.other.cleanup.uninstall.p'] . "</p>
" . _admin_smallNote(str_replace('*prefix*', _mysql_prefix, $_lang['admin.other.cleanup.uninstall.note']), true) . "
<p><label><input type='checkbox' name='confirm' value='1' /> " . str_replace('*dbname*', _mysql_db, $_lang['admin.other.cleanup.uninstall.confirm']) . "</label></p>
<p><strong>" . $_lang['admin.other.cleanup.uninstall.pass'] . ":</strong> &nbsp;<input type='password' class='inputsmall' name='pass' autocomplete='off' /></p>
<input type='submit' value='" . $_lang['global.do'] . "' onclick='return _sysConfirm();' />
" . _xsrfProtect() . "</form>
</fieldset>
";
