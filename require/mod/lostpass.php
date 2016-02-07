<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  pripava  --- */
if (isset($_GET['link'])) {
    $mode = 1;
} else {
    $mode = 0;
}

/* ---  vystup  --- */
if (_template_autoheadings == 1) {
    $module .= "<h1>" . $_lang['mod.lostpass'] . "</h1>";
}

switch ($mode) {

    case 1:

        // nacteni promennych
        if (_iplogCheck(1)) {
            if (isset($_GET['user']) and isset($_GET['hash'])) {
                $user = _anchorStr($_GET['user'], false);
                $hash = DB::esc($_GET['hash']);
                $done = false;

                // text
                $module .= "<p class='bborder'>" . $_lang['mod.lostpass.p2'] . "</p>";

                // zmena nebo zadani hesla
                $errors = array();
                if (isset($_POST['action'])) {

                    // uzivatelske jmeno and otisk
                    $badlink = false;
                    $userdata = DB::query("SELECT id,email,password,salt,username FROM `" . _mysql_prefix . "-users` WHERE username='" . DB::esc($user) . "'");
                    if (DB::size($userdata) == 0) {
                        $errors[] = $_lang['mod.lostpass.badlink'];
                        $badlink = true;
                    } else {
                        $userdata = DB::row($userdata);
                        if ($hash != md5($userdata['email'] . $userdata['salt'] . $userdata['password'])) {
                            $errors[] = $_lang['mod.lostpass.badlink'];
                            $badlink = true;
                        }
                    }

                    // zmena a odeslani emailu nebo vypis chyb
                    if (count($errors) == 0) {
                        $newpass = _md5Salt(_wordGen());
                        $text_tags = array("*domain*", "*username*", "*newpass*", "*date*", "*ip*");
                        $text_contents = array(_getDomain(), $userdata['username'], $newpass[2], _formatTime(time()), _userip);
                        if (_mail($userdata['email'], str_replace('*domain*', _getDomain(), $_lang['mod.lostpass.mail.subject']), str_replace($text_tags, $text_contents, $_lang['mod.lostpass.mail.text2']), "Content-Type: text/plain; charset=UTF-8\n" . _sysMailHeader())) {
                            DB::query("UPDATE `" . _mysql_prefix . "-users` SET password='" . $newpass[0] . "', salt='" . $newpass[1] . "' WHERE id=" . $userdata['id']);
                            $module .= _formMessage(1, $_lang['mod.lostpass.generated']);
                        } else {
                            $module .= _formMessage(3, $_lang['hcm.mailform.msg.failure2']);
                        }
                        $done = true;
                    } else {
                        $module .= _formMessage(2, _eventList($errors, "errors"));
                        if ($badlink) {
                            _iplogUpdate(1);
                        }
                    }

                }

                // formular
                if (!$done and count($errors) == 0) {
                    $module .= _formOutput("lostpassform", "index.php?m=lostpass&amp;link&amp;user=" . _htmlStr($user) . "&amp;hash=" . _htmlStr($hash), array(), array(), $_lang['mod.lostpass.generate'], "<input type='hidden' name='action' value='1' />");
                }

            }
        } else {
            $module .= _formMessage(2, str_replace(array("*1*", "*2*"), array(_maxloginattempts, _maxloginexpire / 60), $_lang['login.attemptlimit']));
        }

        break;

    default:

        $module .= "<p class='bborder'>" . $_lang['mod.lostpass.p'] . "</p>";

        // kontrola promennych, odeslani emailu
        $sent = false;
        if (isset($_POST['username'])) {
            if (_iplogCheck(7)) {

                // nacteni promennych
                $username = _anchorStr($_POST['username'], false);
                $email = DB::esc($_POST['email']);

                // kontrola promennych
                if (_captchaCheck()) {
                    $userdata = DB::query("SELECT email,password,salt,username FROM `" . _mysql_prefix . "-users` WHERE username='" . DB::esc($username) . "' AND email='" . $email . "'");
                    if (DB::size($userdata) != 0) {

                        // odeslani emailu
                        $userdata = DB::row($userdata);
                        $link = _url . "/index.php?m=lostpass&link&user=" . $username . "&hash=" . md5($userdata['email'] . $userdata['salt'] . $userdata['password']);
                        $text_tags = array("*domain*", "*username*", "*link*", "*date*", "*ip*");
                        $text_contents = array(_getDomain(), $userdata['username'], $link, _formatTime(time()), _userip);
                        if (_mail($userdata['email'], str_replace('*domain*', _getDomain(), $_lang['mod.lostpass.mail.subject']), str_replace($text_tags, $text_contents, $_lang['mod.lostpass.mail.text']), "Content-Type: text/plain; charset=UTF-8\n" . _sysMailHeader())) {
                            $module .= _formMessage(1, $_lang['mod.lostpass.cmailsent']);
                            _iplogUpdate(7);
                            $sent = true;
                        } else {
                            $module .= _formMessage(3, $_lang['hcm.mailform.msg.failure2']);
                        }

                    } else {
                        $module .= _formMessage(2, $_lang['mod.lostpass.notfound']);
                    }
                } else {
                    $module .= _formMessage(2, $_lang['captcha.failure2']);
                }

            } else {
                $module .= _formMessage(3, str_replace('*limit*', _lostpassexpire / 60, $_lang['mod.lostpass.limit']));
            }
        }

        // formular
        if (!$sent) {
            $captcha = _captchaInit();
            $module .= _formOutput(
                "lostpassform",
                "index.php?m=lostpass",
                array(
                    array($_lang['login.username'], "<input type='text' name='username' class='inputsmall' maxlength='24'" . _restorePostValue('username') . " />"),
                    array($_lang['global.email'], "<input type='text' name='email' class='inputsmall' " . _restorePostValue('email', '@') . " />"),
                    $captcha
                ),
                array("username", "email"),
                $_lang['global.send']
            );
        }
        break;

}
