<?php
/* ---  incializace jadra  --- */

require '../require/load.php';
SL::init('../');

/* --  nacteni promennych  -- */

// kontrola zvoleni
_checkKeys('_POST', array('_posttarget', '_posttype', 'text'));
_checkKeys('_GET', array('_return'));

// jmeno hosta nebo ID uzivatele
if (_loginindicator) {
    $guest = "";
    $author = _loginid;
} else {
    if (isset($_POST['guest'])) {
        $guest = $_POST['guest'];
        if (mb_strlen($guest) > 24) {
            $guest = mb_substr($guest, 0, 24);
        }
        $guest = _anchorStr($guest, false);
    } else {
        $guest = "";
    }

    $author = -1;
}

// typ, domov, text
$posttarget = intval($_POST['_posttarget']);
$posttype = intval($_POST['_posttype']);
$text = DB::esc(_htmlStr(_wsTrim(_cutStr($_POST['text'], (($posttype != 4) ? 16384 : 255), false))));

// domovsky prispevek
if ($posttype != 4) {
    _checkKeys('_POST', array('_xhome'));
    $xhome = intval($_POST['_xhome']);
} else {
    $xhome = -1;
}

// predmet
if ($xhome == -1 and $posttype != 4) {
    _checkKeys('_POST', array('subject'));
    $subject = DB::esc(_htmlStr(_wsTrim(_cutStr($_POST['subject'], (($posttype == 5) ? 48 : 22), false))));
} else {
    $subject = "";
}

// plugin flag
if ($posttype == 8) {
    if (!isset($_POST['_pluginflag'])) die;
    $pluginflag = intval($_POST['_pluginflag']);
} else $pluginflag = 0;

// vyplneni prazdnych poli
if ($subject == "" and $xhome == -1 and $posttype != 4) $subject = "-";
if ($guest == "" and !_loginindicator) $guest = $_lang['posts.anonym'];

// posuny v posttype (je v tom bordel!)
if ($posttype == 6) $posttype = 5;
elseif ($posttype == 7) $posttype = 6;
elseif ($posttype == 8) $posttype = 7;

/* --  kontrola cile  -- */
$continue = false;
switch ($posttype) {

        // sekce
    case 1:
        $tdata = DB::query("SELECT public,var1,var3,level FROM `" . _mysql_prefix . "-root` WHERE id=" . $posttarget . " AND type=1");
        if (DB::size($tdata) != 0) {
            $tdata = DB::row($tdata);
            if (_publicAccess($tdata['public'], $tdata['level']) and $tdata['var1'] == 1 and $tdata['var3'] != 1) {
                $continue = true;
            }
        }
        break;

        // clanek
    case 2:
        $tdata = DB::query("SELECT id,time,confirmed,public,home1,home2,home3,comments,commentslocked FROM `" . _mysql_prefix . "-articles` WHERE id=" . $posttarget);
        if (DB::size($tdata) != 0) {
            $tdata = DB::row($tdata);
            if (_articleAccess($tdata) == 1 and $tdata['comments'] == 1 and $tdata['commentslocked'] == 0) {
                $continue = true;
            }
        }
        break;

        // kniha
    case 3:
        $tdata = DB::query("SELECT public,var1,var3,level FROM `" . _mysql_prefix . "-root` WHERE id=" . $posttarget . " AND type=3");
        if (DB::size($tdata) != 0) {
            $tdata = DB::row($tdata);
            if (_publicAccess($tdata['public'], $tdata['level']) and _publicAccess($tdata['var1']) and $tdata['var3'] != 1) {
                $continue = true;
            }
        }

        break;

        // shoutbox
    case 4:
        $tdata = DB::query("SELECT public,locked FROM `" . _mysql_prefix . "-sboxes` WHERE id=" . $posttarget);
        if (DB::size($tdata) != 0) {
            $tdata = DB::row($tdata);
            if (_publicAccess($tdata['public']) and $tdata['locked'] != 1) {
                $continue = true;
            }
        }
        break;

        // forum
    case 5:
        $tdata = DB::query("SELECT public,var2,var3,level FROM `" . _mysql_prefix . "-root` WHERE id=" . $posttarget . " AND type=8");
        if (DB::size($tdata) != 0) {
            $tdata = DB::row($tdata);
            if (_publicAccess($tdata['public'], $tdata['level']) and _publicAccess($tdata['var3']) and $tdata['var2'] != 1) {
                $continue = true;
            }
        }
        break;

        // zprava
    case 6:
        if (_messages && _loginindicator) {
            $tdata = DB::query_row('SELECT sender,receiver FROM `' . _mysql_prefix . '-pm` WHERE id=' . $posttarget . ' AND (sender=' . _loginid . ' OR receiver=' . _loginid . ') AND sender_deleted=0 AND receiver_deleted=0');
            if ($tdata !== false) {
                $continue = true;
                $xhome = $posttarget;
            }
        }
        break;

        // plugin post
    case 7:
        _extend('call', 'posts.' . $pluginflag . '.validate', array('home' => $posttarget, 'valid' => &$continue));
        break;

        // blbost
    default:
        die;

}

/* --  kontrola prispevku pro odpoved  -- */
if ($xhome != -1 && $posttype != 6) {
    $continue2 = false;
    $tdata = DB::query("SELECT xhome FROM `" . _mysql_prefix . "-posts` WHERE id=" . $xhome . " AND home=" . $posttarget . " AND locked=0");
    if (DB::size($tdata) != 0) {
        $tdata = DB::row($tdata);
        if ($tdata['xhome'] == -1) {
            $continue2 = true;
        }
    }
} else {
    $continue2 = true;
}

/* --  ulozeni prispevku  -- */
if ($continue and $continue2 and $text != "" and ($posttype == 4 || _captchaCheck())) {
    if (_xsrfCheck()) {
        if ($posttype == 4 or _loginright_unlimitedpostaccess or _iplogCheck(5)) {
            if ($guest === '' || DB::result(DB::query('SELECT COUNT(*) FROM `' . _mysql_prefix . '-users` WHERE username=\'' . DB::esc($guest) . '\' OR publicname=\'' . DB::esc($guest) . '\''), 0) == 0) {

                // zpracovani pluginem
                $allow = true;
                _extend('call', 'posts.submit', array('allow' => &$allow, 'posttype' => $posttype, 'posttarget' => $posttarget, 'xhome' => $xhome, 'subject' => &$subject, 'text' => &$text, 'author' => $author, 'guest' => $guest));
                if ($allow) {

                    // ulozeni
                    DB::query("INSERT INTO `" . _mysql_prefix . "-posts` (type,home,xhome,subject,text,author,guest,time,ip,bumptime,flag) VALUES (" . $posttype . "," . $posttarget . "," . $xhome . ",'" . $subject . "','" . $text . "'," . $author . ",'" . $guest . "'," . time() . ",'" . _userip . "'," . (($posttype == 5 && $xhome == -1) ? 'UNIX_TIMESTAMP()' : '0') . "," . $pluginflag . ")");
                    $insert_id = DB::insertID();
                    if (!_loginright_unlimitedpostaccess and $posttype != 4) _iplogUpdate(5);
                    $return = 1;

                    _extend('call', 'posts.new', array('id' => $insert_id, 'posttype' => $posttype));

                    // topicy - aktualizace bumptime
                    if ($posttype == 5 && $xhome != -1) {
                        DB::query("UPDATE `" . _mysql_prefix . "-posts` SET bumptime=UNIX_TIMESTAMP() WHERE id=" . $xhome);
                    }

                    // zpravy - aktualizace casu zmeny a precteni
                    if ($posttype == 6) {
                        $role = (($tdata['sender'] == _loginid) ? 'sender' : 'receiver');
                        DB::query('UPDATE `' . _mysql_prefix . '-pm` SET update_time=UNIX_TIMESTAMP(),' . $role . '_readtime=UNIX_TIMESTAMP() WHERE id=' . $posttarget);
                    }

                    // shoutboxy - odstraneni prispevku za hranici limitu
                    if ($posttype == 4) {
                        $pnum = DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-posts` WHERE type=4 AND home=" . $posttarget), 0);
                        if ($pnum > _sboxmemory) {
                            $dnum = $pnum - _sboxmemory;
                            $dposts = DB::query("SELECT id FROM `" . _mysql_prefix . "-posts` WHERE type=4 AND home=" . $posttarget . " ORDER BY id LIMIT " . $dnum);
                            while ($dpost = DB::row($dposts)) {
                                DB::query("DELETE FROM `" . _mysql_prefix . "-posts` WHERE id=" . $dpost['id']);
                            }
                        }
                    }

                } else {
                    $return = 0;
                }

            } else {
                $return = 3;
            }
        } else {
            $return = 2;
        }
    } else {
        $return = 4;
    }
} else {
    $return = 0;
}

/* ---  presmerovani  --- */
if ($posttype != 4) {
    if (!isset($_POST['subject'])) $_POST['subject'] = "";
    if ($return != 1) $returnurl = _addFdGetToLink(_addGetToLink($_GET['_return'], "replyto=" . $xhome, false), array("guest" => $guest, "subject" => $_POST['subject'], "text" => $_POST['text'])) . "&addpost";
    else $returnurl = $_GET['_return'];
    $_GET['_return'] = _addGetToLink($returnurl, "r=" . $return . (($posttype == 5) ? '&autolast' : '') . (($posttype != 4 && isset($insert_id)) ? "#post-" . $insert_id : ''), false);
}

_returnHeader();
