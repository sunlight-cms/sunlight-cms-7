<?php
/* ---  incializace jadra  --- */
require '../require/load.php';
define('_session_regenerate', true);
SL::init('../');

/* ---  prihlaseni  --- */
_checkKeys('_POST', array('form_url'));
if (!isset($_POST['username'])) $_POST['username'] = '';
if (!isset($_POST['password'])) $_POST['password'] = '';
$result = 0;
$username = "";
$ipbound = isset($_POST['ipbound']);

if (!_loginindicator) {

    if (_xsrfCheck()) {
        if (_iplogCheck(1)) {

            // nacteni promennych
            $username = DB::esc($_POST['username']);
            $email = (strpos($_POST['username'], '@') !== false);
            $password = $_POST['password'];
            $persistent = _checkboxLoad('persistent');

            // nalezeni uzivatele
            $query = DB::query("SELECT * FROM `" . _mysql_prefix . "-users` WHERE `" . ($email ? 'email' : 'username') . "`='" . $username . "'" . ((!$email && $username !== '') ? ' OR publicname=\'' . $username . '\'' : ''));
            if (DB::size($query) != 0) {

                $query = DB::row($query);
                if (empty($username)) $username = $query['username'];
                $groupblock = DB::query_row("SELECT blocked FROM `" . _mysql_prefix . "-groups` WHERE id=" . $query['group']);
                if ($query['blocked'] == 0 and $groupblock['blocked'] == 0) {
                    if (_md5Salt($password, $query['salt']) == $query['password']) {

                        // navyseni poctu prihlaseni
                        DB::query("UPDATE `" . _mysql_prefix . "-users` SET logincounter=logincounter+1 WHERE id=" . $query['id']);

                        // zaslani cookie pro stale prihlaseni
                        if ($persistent) {
                            $persistent_cookie_data = array();
                            $persistent_cookie_data[] = $query['id'];
                            $persistent_cookie_data[] = ($ipbound ? '1' : '0');
                            $persistent_cookie_data[] = _md5HMAC($query['password'] . '$' . $query['email'], $ipbound ? _userip : _sessionprefix);
                            setcookie(_sessionprefix . "persistent_key", implode('$', $persistent_cookie_data), (time() + 2592000), "/");
                        }

                        // ulozeni dat pro session
                        $_SESSION[_sessionprefix . "user"] = $query['id'];
                        $_SESSION[_sessionprefix . "password"] = $query['password'];
                        $_SESSION[_sessionprefix . "ip"] = _userip;
                        $_SESSION[_sessionprefix . "ipbound"] = $ipbound;
                        $result = 1;

                    } else {
                        _iplogUpdate(1);
                    }
                } else {
                    $result = 2;
                }

            }

        } else {
            $result = 5;
        }
    } else {
        $result = 6;
    }

}

/* ---  presmerovani  --- */
if ($result != 1) $_GET['_return'] = _addFdGetToLink(_addGetToLink($_POST['form_url'], '_mlr=' . $result, false), array('username' => $username));
_returnHeader();
