<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* -- konfigurace -- */
$disabled = defined('_no_session');

/* --  akce  -- */
if (!$disabled) {

    session_name(_sessionprefix . 'session');
    session_start();

    if (defined('_session_regenerate')) {
        define('_session_old_id', session_id());
        session_regenerate_id(true);
    }

}

$result = 0;

// pole konstant opravneni
$rights_array = _getRightsArray();

// pouziti cookie persistentniho prihlaseni, pokud existuje
$persistent_cookie_found = false;
if (!$disabled && isset($_COOKIE[_sessionprefix . "persistent_key"])) {
    // nacist data
    $persistent_cookie = $_COOKIE[_sessionprefix . "persistent_key"];
    $persistent_cookie = explode('$', $persistent_cookie);
    if (count($persistent_cookie) == 3) {
        $persistent_cookie[0] = intval($persistent_cookie[0]);
        $persistent_cookie[1] = _boolean($persistent_cookie[1]);
        $persistent_cookie_found = true;
    }
}

// kontrola existence session
if (!$disabled && ($persistent_cookie_found or (isset($_SESSION[_sessionprefix . "user"]) and isset($_SESSION[_sessionprefix . "password"]) and isset($_SESSION[_sessionprefix . "ip"])))) {

    // pouziti cookie pro nastaveni dat session (pokud neexistuji)
    $persistent_cookie_used = false;
    $persistent_cookie_bad = false;
    if ($persistent_cookie_found and !(isset($_SESSION[_sessionprefix . "user"]) and isset($_SESSION[_sessionprefix . "password"]) and isset($_SESSION[_sessionprefix . "ip"])) and _iplogCheck(1)) {
        $persistent_cookie_bad = true;
        $uquery = DB::query("SELECT * FROM `" . _mysql_prefix . "-users` WHERE id=" . $persistent_cookie[0]);
        if (DB::size($uquery) != 0) {
            $uquery = DB::row($uquery);
            $persistent_cookie_used = true;
            if ($persistent_cookie[2] == _md5HMAC($uquery['password'] . '$' . $uquery['email'], $persistent_cookie[1] ? _userip : _sessionprefix)) {
                // platna cooke
                $_SESSION[_sessionprefix . "user"] = $persistent_cookie[0];
                $_SESSION[_sessionprefix . "password"] = $uquery['password'];
                $_SESSION[_sessionprefix . "ip"] = _userip;
                $_SESSION[_sessionprefix . "ipbound"] = true;
                $persistent_cookie_bad = false;
            } else {
                // neplatna cookie - zaznam v ip logu
                _iplogUpdate(1);
            }
        }
    }

    // kontroly
    $continue = false;
    if (!$persistent_cookie_bad) {
        $id = intval($_SESSION[_sessionprefix . "user"]);
        $pass = $_SESSION[_sessionprefix . "password"];
        $ip = $_SESSION[_sessionprefix . "ip"];
        if (!$persistent_cookie_used) $uquery = DB::query("SELECT * FROM `" . _mysql_prefix . "-users` WHERE id=" . $id);

        if ($persistent_cookie_used or DB::size($uquery) != 0) {
            if (!$persistent_cookie_used) $uquery = DB::row($uquery);
            $gquery = DB::query_row("SELECT * FROM `" . _mysql_prefix . "-groups` WHERE id=" . $uquery['group']);
            if (
                $uquery['password'] == $pass
                and $uquery['blocked'] == 0
                and $gquery['blocked'] == 0
                and (!$_SESSION[_sessionprefix . 'ipbound'] or $ip == _userip)

            ) $continue = true; // vse ok
        }
    }

    // zabiti neplatne session
    if ($continue != true) {
        _userLogout(false);
    }

    // definovani konstant
    if ($continue) {

        $result = 1;
        define('_loginid', $uquery['id']);
        define('_loginname', $uquery['username']);
        if ($uquery['publicname'] != "") define('_loginpublicname', $uquery['publicname']);
        else define('_loginpublicname', $uquery['username']);
        define('_loginemail', $uquery['email']);
        define('_loginwysiwyg', $uquery['wysiwyg']);
        define('_loginlanguage', $uquery['language']);
        define('_logincounter', $uquery['logincounter']);

        // konstanty skupiny
        define('_loginright_group', $gquery['id']);
        define('_loginright_groupname', $gquery['title']);

        // zvyseni levelu pro superuzivatele
        if ($uquery['levelshift'] == 1) $gquery['level'] += 1;

        // konstanty opravneni
        foreach($rights_array as $item) define('_loginright_' . $item, $gquery[$item]);

        // zaznamenani casu aktivity (max 1x za 30 sekund)
        if (time() - $uquery['activitytime'] > 30) {
            DB::query("UPDATE `" . _mysql_prefix . "-users` SET activitytime='" . time() . "', ip='" . _userip . "' WHERE id=" . _loginid);
        }

    }

}

if (1 !== $result) {

    // konstanty hosta
    define('_loginid', -1);
    define('_loginname', '');
    define('_loginpublicname', '');
    define('_loginemail', '');
    define('_loginwysiwyg', 0);
    define('_loginlanguage', '');
    define('_logincounter', 0);

    // konstanty skupiny
    $gquery = DB::query_row("SELECT * FROM `" . _mysql_prefix . "-groups` WHERE id=2");
    define('_loginright_group', $gquery['id']);
    define('_loginright_groupname', $gquery['title']);

    foreach ($rights_array as $item) {
        define('_loginright_' . $item, $gquery[$item]);
    }
}

// konstanta pro indikaci prihlaseni
define('_loginindicator', $result);
