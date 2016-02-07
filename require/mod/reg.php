<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  funkce registrace  --- */
function _tmpRegister($mode, $group, $username, $pass_and_salt, $massemail, $ip, $email)
{
    // vlozeni do tabulky uzivatelu
    if ($mode == 0) {
        DB::query("INSERT INTO `" . _mysql_prefix . "-users` (`group`,levelshift,username,password,salt,logincounter,registertime,activitytime,blocked,massemail,wysiwyg,language,ip,email,web,skype,msn,jabber,icq,note) VALUES (" . $group . ",0,'" . $username . "','" . $pass_and_salt[0] . "','" . $pass_and_salt[1] . "',0," . time() . "," . time() . ",0," . $massemail . ",0,'','" . _userip . "','" . $email . "','','','','',0,'')");
        $insert_id = DB::insertID();
        _extend('call', 'user.new', array('id' => $insert_id, 'username' => $username));
        _extend('call', 'mod.reg.success', array('user_id' => $insert_id));

        return;
    }

    // vlozeni do tabulky pro potvrzeni
    $code = str_replace('.', '-', uniqid('', true));
    DB::query("INSERT INTO `" . _mysql_prefix . "-user-activation` (`code`,`expire`,`group`,`username`,`password`,`salt`,`massemail`,`ip`,`email`) VALUES('" . $code . "'," . (time() + 3600) . "," . $group . ",'" . $username . "','" . $pass_and_salt[0] . "','" . $pass_and_salt[1] . "'," . $massemail . ",'" . $ip . "','" . $email . "')");

    return $code;
}

/* ---  potvrzeni registrace  --- */
if (isset($_GET['confirm']) && _registration_confirm) {

    // nadpis
    if (_template_autoheadings == 1) $module .= "<h1>" . $_lang['mod.reg.confirm'] . "</h1>";

    // kontrola iplogu
    if (!_iplogCheck(6)) {
        $module .= _formMessage(3, str_replace('*limit*', _accactexpire / 60, $_lang['mod.reg.confirm.limit']));

        return;
    }

    // nacteni dat
    $code = DB::esc(trim($_GET['confirm']));
    if (strlen($code) !== 23) {
        $module .= _formMessage(3, $_lang['mod.reg.confirm.badcode']);

        return;
    }

    // vymazani expirovanych zadosti
    DB::query('DELETE FROM `' . _mysql_prefix . '-user-activation` WHERE `expire`<' . time());

    // nacteni dat aktivace
    $query = DB::query('SELECT * FROM `' . _mysql_prefix . '-user-activation` WHERE `code`="' . $code . '"');

    // test zaznamu
    if (DB::size($query) === 0) {
        // nenalezeno
        _iplogUpdate(6);
        $module .= _formMessage(3, $_lang['mod.reg.confirm.notfound']);

        return;
    }

    // zaznam ok, nacteni dat
    $query = DB::row($query);

    // kontrola dostupnosti uziv. jmena a emailu
    if (DB::result(DB::query('SELECT COUNT(*) FROM `' . _mysql_prefix . '-users` WHERE `username`="' . $query['username'] . '" OR `email`="' . $query['email'] . '"'), 0) != 0) {
        // zabrano
        $module .= _formMessage(3, $_lang['mod.reg.confirm.emailornametaken']);

        return;
    }

    // vsechno je ok.. vytvoreni uctu
    _tmpRegister(0, $query['group'], $query['username'], array($query['password'], $query['salt']), $query['massemail'], $query['ip'], $query['email']);
    $module .= "<p>" . str_replace("*username*", $query['username'], $_lang['mod.reg.done']) . "</p>";

    // smazani pouzite zadosti
    DB::query('DELETE FROM `' . _mysql_prefix . '-user-activation` WHERE `id`=' . $query['id']);

    // konec skriptu
    return;

}

/* ---  registrace  --- */
$phase = 0;
$message = "";
if (isset($_POST['username'])) {

    $errors = array();

    // kontrola iplogu
    if (!_iplogCheck(5)) {
        $errors[] = str_replace("*postsendexpire*", _postsendexpire, $_lang['misc.requestlimit']);
    }

    // nacteni a kontrola promennych
    $username = $_POST['username'];
    if (mb_strlen($username) > 24) {
        $username = mb_substr($username, 0, 24);
    }
    $username = DB::esc(_anchorStr($username, false));
    if ($username == "") {
        $errors[] = $_lang['admin.users.edit.badusername'];
    } elseif (DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-users` WHERE username='" . $username . "' OR publicname='" . $username . "'"), 0) != 0) {
        $errors[] = $_lang['admin.users.edit.userexists'];
    }

    $password = $_POST['password'];
    $password2 = $_POST['password2'];
    if ($password != $password2) {
        $errors[] = $_lang['mod.reg.nosame'];
    }
    if ($password != "") {
        $password = _md5Salt($password);
    } else {
        $errors[] = $_lang['mod.reg.passwordneeded'];
    }

    $email = DB::esc(trim($_POST['email']));
    if (!_validateEmail($email)) {
        $errors[] = $_lang['admin.users.edit.bademail'];
    }
    if (DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-users` WHERE email='" . $email . "'"), 0) != 0) {
        $errors[] = $_lang['admin.users.edit.emailexists'];
    }

    if (!_captchaCheck()) {
        $errors[] = $_lang['captcha.failure'];
    }

    $massemail = _checkboxLoad('massemail');

    if (_registration_grouplist and isset($_POST['group'])) {
        $group = intval($_POST['group']);
        $groupdata = DB::query("SELECT id FROM `" . _mysql_prefix . "-groups` WHERE id=" . $group . " AND blocked=0 AND reglist=1");
        if (DB::size($groupdata) == 0) {
            $errors[] = $_lang['global.badinput'];
        }
    } else {
        $group = _defaultgroup;
    }

    if (SL::$settings['rules'] != "" and !_checkboxLoad("agreement")) {
        $errors[] = $_lang['mod.reg.rules.disagreed'];
    }

    // vlozeni do databaze nebo seznam chyb
    if (count($errors) == 0) {
        _iplogUpdate(5);
        $code = _tmpRegister(_registration_confirm, $group, $username, $password, $massemail, _userip, $email);
        if (isset($code)) {
            // poslat potvrzeni
            $phase = 2;
            $domain = _getDomain();
            $mail = _mail($email, str_replace('*domain*', $domain, $_lang['mod.reg.confirm.subject']), str_replace(array('*username*', '*domain*', '*url*', '*ip*', '*date*', '*code*'), array($username, $domain, _url, _userip, _formatTime(time()), $code), $_lang['mod.reg.confirm.text']), "Content-Type: text/plain; charset=UTF-8\n" . _sysMailHeader());
        } else {
            // registrace ok
            $phase = 1;
        }
    } else {
        $message = _formMessage(2, _eventList($errors, 'errors'));
    }

}

/* ---  modul  --- */
if (_template_autoheadings == 1) {
    $module .= "<h1>" . $_lang['mod.reg'] . "</h1>";
}

switch ($phase) {

        // registracni formular
    case 0:
        // priprava vyberu skupiny
        $groupselect = array(null);
        if (_registration_grouplist) {
            $groupselect_items = DB::query("SELECT id,title FROM `" . _mysql_prefix . "-groups` WHERE `blocked`=0 AND reglist=1 ORDER BY title");
            if (DB::size($groupselect_items) != 0) {
                $groupselect_content = "";
                while ($groupselect_item = DB::row($groupselect_items)) {
                    $groupselect_content .= "<option value='" . $groupselect_item['id'] . "'" . (($groupselect_item['id'] == _defaultgroup) ? " selected='selected'" : '') . ">" . $groupselect_item['title'] . "</option>\n";
                }
                $groupselect = array($_lang['global.group'], "<select name='group'>" . $groupselect_content . "</select>");
            }
        }

        // priprava podminek
        if (SL::$settings['rules'] != "") {
            $rules = array("<div class='hr'><hr /></div><h2>" . $_lang['mod.reg.rules'] . "</h2>" . SL::$settings['rules'] . "<br /><label><input type='checkbox' name='agreement' value='1'" . _checkboxActivate(isset($_POST['agreement'])) . " /> " . $_lang['mod.reg.rules.agreement'] . "</label><div class='hr'><hr /></div><br />", "", true);
        } else {
            $rules = array(null);
        }

        // formular
        $captcha = _captchaInit();
        $module .= "<p class='bborder'>" . $_lang['mod.reg.p'] . (_registration_confirm ? ' ' . $_lang['mod.reg.confirm.extratext'] : '') . "</p>";
        $module .= $message . _formOutput(
            "regform",
            "index.php?m=reg", array(
                array($_lang['login.username'], "<input type='text' name='username' class='inputsmall' maxlength='24'" . _restorePostValue('username') . " />"),
                array($_lang['login.password'], "<input type='password' name='password' class='inputsmall' />"),
                array($_lang['login.password'] . " (" . $_lang['global.check'] . ")", "<input type='password' name='password2' class='inputsmall' />"),
                array($_lang['global.email'], "<input type='text' name='email' class='inputsmall' " . _restorePostValue('email', '@') . " />"),
                array($_lang['mod.settings.massemail'], "<input type='checkbox' name='massemail' value='1' checked='checked' /> " . $_lang['mod.settings.massemail.label']),
                $groupselect,
                $captcha,
                $rules,
            ),
            array("username", "email", "password", "password2"),
            $_lang['mod.reg.submit' . (_registration_confirm ? '2' : '')]
        );
        break;

        // uspesna registrace
    case 1:
        $module .= "<p>" . str_replace("*username*", $username, $_lang['mod.reg.done']) . "</p>";
        break;

        // odeslano potvrzeni
    case 2:
        $module .= _formMessage(1, str_replace('*email*', $email, $_lang['mod.reg.confirm.sent']));
        break;

}
