<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  pripava promennych  --- */
$message = "";
$query = DB::query_row("SELECT * FROM `" . _mysql_prefix . "-users` WHERE id=" . _loginid);
if ($query['icq'] == 0) $query['icq'] = "";

// cesta k avataru
$avatar_path = _getAvatar(_loginid, true, false, true, true);

/* ---  ulozeni  --- */
if (isset($_POST['username'])) {

    $errors = array();

    /* --  nacteni a kontrola promennych  -- */

    // sebedestrukce
    if (_loginright_selfdestruction and _checkboxLoad("selfremove")) {
        $selfremove_confirm = _md5Salt($_POST['selfremove-confirm'], $query['salt']);
        if ($selfremove_confirm == $query['password']) {
            if (_loginid != 0) {
                _deleteUser(_loginid);
                $_SESSION = array();
                session_destroy();
                define('_redirect_to', 'index.php?m=login&_mlr=4');

                return;
            } else {
                $errors[] = $_lang['mod.settings.selfremove.denied'];
            }
        } else {
            $errors[] = $_lang['mod.settings.selfremove.failed'];
        }
    }

    // username
    $username = $_POST['username'];
    if (mb_strlen($username) > 24) {
        $username = mb_substr($username, 0, 24);
    }
    $username = DB::esc(_anchorStr($username, false));
    if ($username == "") {
        $errors[] = $_lang['admin.users.edit.badusername'];
    } else {
        $usernamechange = false;
        if ($username != _loginname) {
            if (_loginright_changeusername or mb_strtolower($username) == mb_strtolower(_loginname)) {
                if (DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-users` WHERE (username='" . $username . "' OR publicname='" . $username . "') AND id!=" . _loginid), 0) == 0) {
                    $usernamechange = true;
                } else $errors[] = $_lang['admin.users.edit.userexists'];
            } else {
                $errors[] = $_lang['mod.settings.error.usernamechangedenied'];
            }
        }
    }

    // publicname
    $publicname = $_POST['publicname'];
    if (mb_strlen($publicname) > 24) $publicname = mb_substr($publicname, 0, 24);
    $publicname = DB::esc(_htmlStr(_wsTrim($publicname)));
    if ($publicname != $query['publicname'] and $publicname != "") {
        if (DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-users` WHERE (publicname='" . $publicname . "' OR username='" . $publicname . "') AND id!=" . _loginid), 0) != 0) {
            $errors[] = $_lang['admin.users.edit.publicnameexists'];
        }
    }

    // email
    $email = DB::esc(trim($_POST['email']));
    if (!_validateEmail($email)) {
        $errors[] = $_lang['admin.users.edit.bademail'];
    } else {
        if ($email != _loginemail) {
            if (DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-users` WHERE email='" . $email . "' AND id!=" . _loginid), 0) != 0) {
                $errors[] = $_lang['admin.users.edit.emailexists'];
            }
        }
    }

    // massemail, wysiwyg, icq
    $massemail = _checkboxLoad("massemail");
    if (_loginright_administration) {
        $wysiwyg = _checkboxLoad("wysiwyg");
    }
    $icq = intval(str_replace("-", "", $_POST['icq']));

    // skype
    $skype = trim($_POST['skype']);
    if ($skype != "" and !preg_match('|[a-zA-Z0-9._-]{6,62}|', $skype)) {
        $errors[] = $_lang['global.skype.bad'];
    }
    $skype = DB::esc($skype);

    // msn
    $msn = trim($_POST['msn']);
    if ($msn != "" and !_validateEmail($msn)) {
        $errors[] = $_lang['global.msn.bad'];
    }
    $msn = DB::esc($msn);

    // jabber
    $jabber = trim($_POST['jabber']);
    if ($jabber != "" and !_validateEmail($jabber)) {
        $errors[] = $_lang['global.jabber.bad'];
    }
    $jabber = DB::esc($jabber);

    // web
    $web = _htmlStr(trim($_POST['web']));
    if (mb_strlen($web) > 255) {
        $web = mb_substr($web, 0, 255);
    }
    if ($web != "" and !_validateURL("http://" . $web)) {
        $web = "";
    } else {
        $web = DB::esc($web);
    }

    // avatar
    $avatar = $query['avatar'];
    if (_uploadavatar) {

        // smazani avataru
        if (_checkboxLoad("removeavatar") && isset($avatar)) {
            @unlink(_indexroot . 'pictures/avatars/' . $avatar . '.jpg');
            $avatar = null;
        }

        // upload avataru
        if (isset($_FILES['avatar']) && is_uploaded_file($_FILES['avatar']['tmp_name'])) {

            // zpracovani
            $avatarUid = _pictureProcess(array(
                'file_path' => $_FILES['avatar']['tmp_name'],
                'file_name' => $_FILES['avatar']['name'],
                'limit' => array('filesize' => 1048576, 'dimensions' => array('x' => 1400, 'y' => 1400)),
                'resize' => array('mode' => 'zoom', 'x' => 96, 'y' => 128),
                'target_path' => _indexroot . 'pictures/avatars/',
                'target_format' => 'jpg',
                'jpg_quality' => 95,
            ), $avatarError);

            if (false !== $avatarUid) {

                // smazani stareho avataru
                if (null !== $avatar) {
                    @unlink(_indexroot . 'pictures/avatars/' . $avatar . '.jpg');
                }

                // ok
                $avatar = $avatarUid;

            } else {
                $errors[] = $_lang['global.avatar'] . ' - ' . $avatarError;
            }

        }

    }

    // password
    $passwordchange = false;
    if ($_POST['currentpassword'] != "" or $_POST['newpassword'] != "" or $_POST['newpassword-confirm'] != "") {
        $currentpassword = _md5Salt($_POST['currentpassword'], $query['salt']);
        $newpassword = $_POST['newpassword'];
        $newpassword_confirm = $_POST['newpassword-confirm'];
        if ($currentpassword == $query['password']) {
            if ($newpassword == $newpassword_confirm) {
                if ($newpassword != "") {
                    $passwordchange = true;
                    $newpassword = _md5Salt($newpassword);
                } else {
                    $errors[] = $_lang['mod.settings.error.badnewpass'];
                }
            } else {
                $errors[] = $_lang['mod.settings.error.newpassnosame'];
            }
        } else {
            $errors[] = $_lang['mod.settings.error.badcurrentpass'];
        }
    }

    // note
    $note = DB::esc(_htmlStr(_wsTrim(mb_substr($_POST['note'], 0, 1024))));

    // language
    if (_language_allowcustom) {
        $language = DB::esc(_anchorStr($_POST['language'], false));
        if (!@file_exists(_indexroot . "plugins/languages/" . $language . ".php")) {
            $language = "";
        }
    }

    // extend
    $extra = array();
    _extend('call', 'mod.settings.submit', array('query' => &$extra, 'current_query' => $query, 'errors' => &$errors));

    /* --  ulozeni nebo seznam chyb  -- */
    if (count($errors) == 0) {

        // extra polozky
        if (_loginright_administration) $extra['wysiwyg'] = $wysiwyg;
        if (_language_allowcustom) $extra['language'] = $language;
        if ($usernamechange == true) $extra['username'] = $username;
        if ($passwordchange == true) {
            $extra['password'] = $newpassword[0];
            $extra['salt'] = $newpassword[1];
            $_SESSION[_sessionprefix . "password"] = $newpassword[0];
        }

        // extend
        _extend('call', 'mod.settings.save', array('query' => &$extra, 'current_query' => $query));

        // zpracovani extra polozek
        $sql_extra = '';
        if (!empty($extra))
            foreach($extra as $col => $val) $sql_extra .= ',`' . $col . '`=' . DB::val($val);

        // hlavni dotaz
        DB::query("UPDATE `" . _mysql_prefix . "-users` SET email='" . $email . "',avatar=" . (isset($avatar) ? '\'' . $avatar . '\'' : 'NULL') . ",web='" . $web . "',skype='" . $skype . "',msn='" . $msn . "',jabber='" . $jabber . "',icq=" . $icq . ",massemail=" . $massemail . ",note='" . $note . "',publicname='" . $publicname . "'" . $sql_extra . " WHERE id=" . _loginid);
        _extend('call', 'user.edit', array('id' => _loginid, 'username' => $username));
        define('_redirect_to', _url . '/index.php?m=settings&saved');

        return;

    } else {
        $message = _formMessage(2, _eventList($errors, 'errors'));
    }

}

/* ---  modul  --- */
if (isset($_GET['saved'])) {
    $message = _formMessage(1, $_lang['global.saved']);
}
if (_template_autoheadings == 1) {
    $module .= "<h1>" . $_lang['mod.settings'] . "</h1>";
}

// vyber jazyka
if (_language_allowcustom) {
    $language_select = '
    <tr>
    <td><strong>' . $_lang['admin.settings.main.language'] . '</strong></td>
    <td><select name="language" class="inputsmall"><option value="">' . $_lang['global.original'] . '</option>';
    $handle = @opendir(_indexroot . "plugins/languages/");
    while (false !== ($item = @readdir($handle))) {
        if ($item == "." or $item == ".." or @is_dir(_indexroot . $item) or $item == _language . ".php") {
            continue;
        }

        // kontrola polozky
        $item = pathinfo($item);
        if (!isset($item['extension']) or $item['extension'] != "php") {
            continue;
        }
        $item = mb_substr($item['basename'], 0, mb_strrpos($item['basename'], "."));

        if ($item == _loginlanguage) {
            $selected = ' selected="selected"';
        } else {
            $selected = "";
        }
        $language_select .= '<option value="' . $item . '"' . $selected . '>' . $item . '</option>';
    }
    closedir($handle);
    $language_select .= '</select></td></tr>';
} else {
    $language_select = "";
}

// wysiwyg
if (_loginright_administration) {
    $admin = "



  <tr>
  <td><strong>" . $_lang['mod.settings.wysiwyg'] . "</strong></td>
  <td><label><input type='checkbox' name='wysiwyg' value='1'" . _checkboxActivate($query['wysiwyg']) . " /> " . $_lang['mod.settings.wysiwyg.label'] . "</label></td>
  </tr>

  ";
} else {
    $admin = "";
}

$module .= "
<p><a href='index.php?m=profile&amp;id=" . _loginname . "'>" . $_lang['mod.settings.profilelink'] . " &gt;</a></p>
<p>" . $_lang['mod.settings.p'] . "</p>" . $message . "
<form action='index.php?m=settings' method='post' name='setform' enctype='multipart/form-data'>

" . _jsLimitLength(1024, "setform", "note") . "

  <fieldset>
  <legend>" . $_lang['mod.settings.userdata'] . "</legend>
  <table class='profiletable'>

  <tr>
  <td><strong>" . $_lang['login.username'] . "</strong> <span class='important'>*</span></td>
  <td><input type='text' name='username'" . _restorePostValue('username', _loginname) . " class='inputsmall' maxlength='24' />" . (!_loginright_changeusername ? "<span class='hint'>(" . $_lang['mod.settings.namechangenote'] . ")</span>" : '') . "</td>
  </tr>

  <tr>
  <td><strong>" . $_lang['mod.settings.publicname'] . "</strong></td>
  <td><input type='text' name='publicname'" . _restorePostValue('publicname', $query['publicname']) . " class='inputsmall' maxlength='24' /></td>
  </tr>

  <tr class='valign-top'>
  <td><strong>" . $_lang['global.email'] . "</strong> <span class='important'>*</span></td>
  <td><input type='text' name='email'" . _restorePostValue('email', $query['email']) . " class='inputsmall'/></td>
  </tr>

  " . $language_select . "

  <tr>
  <td><strong>" . $_lang['mod.settings.massemail'] . "</strong></td>
  <td><label><input type='checkbox' name='massemail' value='1'" . _checkboxActivate($query['massemail']) . " /> " . $_lang['mod.settings.massemail.label'] . "</label></td>
  </tr>

  " . $admin . "
  </table>
  </fieldset>


  <fieldset>
  <legend>" . $_lang['mod.settings.password'] . "</legend>
  <p class='minip'>" . $_lang['mod.settings.password.hint'] . "</p>
  <table class='profiletable'>

  <tr>
  <td><strong>" . $_lang['mod.settings.password.current'] . "</strong></td>
  <td><input type='password' name='currentpassword' class='inputsmall' autocomplete='off' /></td>
  </tr>

  <tr>
  <td><strong>" . $_lang['mod.settings.password.new'] . "</strong></td>
  <td><input type='password' name='newpassword' class='inputsmall' autocomplete='off' /></td>
  </tr>

  <tr>
  <td><strong>" . $_lang['mod.settings.password.new'] . " (" . $_lang['global.check'] . ")</strong></td>
  <td><input type='password' name='newpassword-confirm' class='inputsmall' autocomplete='off' /></td>
  </tr>

  </table>
  </fieldset>

  " . _extend('buffer', 'mod.settings.form') . "


  <fieldset>
  <legend>" . $_lang['mod.settings.info'] . "</legend>

  <table class='profiletable'>

  <tr>
  <td><strong>" . $_lang['global.icq'] . "</strong></td>
  <td><input type='text' name='icq'" . _restorePostValue('icq', $query['icq']) . " class='inputsmall' /></td>
  </tr>

  <tr>
  <td><strong>" . $_lang['global.skype'] . "</strong></td>
  <td><input type='text' name='skype'" . _restorePostValue('skype', $query['skype']) . " class='inputsmall' /></td>
  </tr>

  <tr>
  <td><strong>" . $_lang['global.msn'] . "</strong></td>
  <td><input type='text' name='msn'" . _restorePostValue('msn', $query['msn']) . " class='inputsmall' /></td>
  </tr>

  <tr>
  <td><strong>" . $_lang['global.jabber'] . "</strong></td>
  <td><input type='text' name='jabber'" . _restorePostValue('jabber', $query['jabber']) . " class='inputsmall' /></td>
  </tr>

  <tr>
  <td><strong>" . $_lang['global.web'] . "</strong></td>
  <td><input type='text' name='web' value='" . $query['web'] . "' class='inputsmall' /><span class='hint'>" . $_lang['mod.settings.web.hint'] . "</span></td>
  </tr>

  <tr class='valign-top'>
  <td><strong>" . $_lang['global.note'] . "</strong></td>
  <td><textarea name='note' class='areasmall' rows='9' cols='33'>" . _restorePostValue('note', $query['note'], true) . "</textarea></td>
  </tr>

  <tr><td></td>
  <td>" . _getPostFormControls("setform", "note") . "</td>
  </tr>

  </table>

  </fieldset>
";

if (_uploadavatar) {
    $module .= "
  <fieldset>
  <legend>" . $_lang['mod.settings.avatar'] . "</legend>
  " . _extend('buffer', 'mod.settings.avatar', array('extra' => array('query' => $query))) . "
  <p><strong>" . $_lang['mod.settings.avatar.upload'] . ":</strong> <input type='file' name='avatar' /></p>
    <table>
    <tr class='valign-top'>
    <td width='106'><div class='avatar'><img src='" . $avatar_path . "' alt='avatar' /></div></td>
    <td><p class='minip'>" . $_lang['mod.settings.avatar.hint'] . "</p><p><label><input type='checkbox' name='removeavatar' value='1' /> " . $_lang['mod.settings.avatar.remove'] . "</label></p></td>
    </tr>
    </table>
  </fieldset>
";
}

if (_loginright_selfdestruction and _loginid != 0) {
    $module .= "

  <fieldset>
  <legend>" . $_lang['mod.settings.selfremove'] . "</legend>
  <label><input type='checkbox' name='selfremove' value='1' onclick='if (this.checked==true) {return _sysConfirm();}' /> " . $_lang['mod.settings.selfremove.box'] . "</label><br /><br />
  <div class='lpad'><strong>" . $_lang['mod.settings.selfremove.confirm'] . ":</strong> <input type='password' name='selfremove-confirm' class='inputsmall' /></div>
  </fieldset>

";
}

$module .= "
<br />
<input type='submit' value='" . $_lang['mod.settings.submit'] . "' />
<input type='reset' value='" . $_lang['global.reset'] . "' onclick='return _sysConfirm();' />

" . _xsrfProtect() . "</form>
";
