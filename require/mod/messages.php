<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  priprava promennych  --- */
if (isset($_GET['a'])) $a = strval($_GET['a']);
else $a = 'list';

/* ---  modul  --- */
$list = false;
$mod_title = 'mod.messages';

// obsah
switch ($a) {

        /* ---  nova zprava  --- */
    case 'new':

        // titulek
        $mod_title = 'mod.messages.new';

        // odeslani
        if (isset($_POST['receiver'])) {

            // nacteni dat
            $receiver = _post('receiver');
            $subject = _htmlStr(_wsTrim(_cutStr(_post('subject'), 22, false)));
            $text = _htmlStr(_wsTrim(_cutStr(_post('text'), 16384, false)));

            // kontrola a odeslani
            do {

                /* ---  kontrola  --- */

                // text
                if ($text === '') {
                    $message = _formMessage(2, $_lang['mod.messages.error.notext']);
                    break;
                }

                // predmet
                if ($subject === '') {
                    $message = _formMessage(2, $_lang['mod.messages.error.nosubject']);
                    break;
                }

                // prijemce
                if ($receiver !== '') {
                    $rq = DB::query_row('SELECT usr.id AS usr_id,usr.blocked AS usr_blocked, ugrp.blocked AS ugrp_blocked FROM `' . _mysql_prefix . '-users` AS usr JOIN `' . _mysql_prefix . '-groups` AS ugrp ON (usr.group=ugrp.id) WHERE usr.username=\'' . DB::esc($receiver) . '\' OR usr.publicname=\'' . DB::esc($receiver) . '\'');
                } else {
                    $rq = false;
                }
                if ($rq === false || $rq['usr_id'] == _loginid) {
                    $message = _formMessage(2, $_lang['mod.messages.error.badreceiver']);
                    break;
                } elseif ($rq['usr_blocked'] || $rq['ugrp_blocked']) {
                    $message = _formMessage(2, $_lang['mod.messages.error.blockedreceiver']);
                    break;
                }

                // anti spam limit
                if (!_iplogCheck(5)) {
                    $message = _formMessage(2, str_replace('*postsendexpire*', _postsendexpire, $_lang['misc.requestlimit']));
                    break;
                }

                /* ---  vse ok, odeslani  --- */

                // zaznam v logu
                if (!_loginright_unlimitedpostaccess) {
                    _iplogUpdate(5);
                }

                // extend
                _extend('call', 'mod.messages.new', array(
                    'receiver' => $rq['usr_id'],
                    'subject' => &$subject,
                    'text' => &$text,
                ));

                // vlozeni do pm tabulky
                DB::query('INSERT INTO `' . _mysql_prefix . '-pm` (sender,sender_readtime,sender_deleted,receiver,receiver_readtime,receiver_deleted,update_time) VALUES(' . _loginid . ',UNIX_TIMESTAMP(),0,' . $rq['usr_id'] . ',0,0,UNIX_TIMESTAMP())');
                $pm_id = DB::insertID();

                // vlozeni do posts tabulky
                DB::query("INSERT INTO `" . _mysql_prefix . "-posts` (type,home,xhome,subject,text,author,guest,time,ip,bumptime) VALUES (6," . $pm_id . ",-1,'" . DB::esc($subject) . "','" . DB::esc($text) . "'," . _loginid . ",''," . time() . ",'" . _userip . "',0)");

                // presmerovani a konec
                define('_redirect_to', _url . '/' . _indexOutput_url . '&a=list&read=' . $pm_id);

                return;

            } while (false);

        }

        // formular
        if (isset($message)) $module .= $message . "\n";
        $module .= "<form action='' method='post' name='newmsg'" . _jsCheckForm('newmsg', array('receiver')) . ">
<table>

<tr>
    <td><strong>" . $_lang['mod.messages.receiver'] . "</strong></td>
    <td><input type='text' name='receiver' class='inputsmall' maxlength='24'" . _restorePostValue("receiver", _get('receiver')) . " /></td>
</tr>

<tr>
    <td><strong>" . $_lang['posts.subject'] . "</strong></td>
    <td><input type='text' name='subject' class='inputsmall' maxlength='22'" . _restorePostValue("subject", _get('subject')) . " /></td>
</tr>

<tr class='valign-top'>
    <td><strong>" . $_lang['mod.messages.message'] . "</strong></td>
    <td><textarea name='text' class='areamedium' rows='5' cols='33'>" . _restorePostValue("text", null, true) . "</textarea></td>
</tr>

<tr>
    <td></td>
    <td><input type='submit' value='" . $_lang['global.send'] . "' />" . _getPostFormControls('newmsg', 'text') . "</td>
</tr>

</table>

" . _jsLimitLength(16384, 'newmsg', 'text') . "

" . _xsrfProtect() . "</form>\n";

        break;

        /* ---  vypis  --- */
    default:

        // cteni vzkazu
        if (isset($_GET['read'])) {

            // promenne
            $id = intval($_GET['read']);

            // nacist data
            $q = DB::query_row('SELECT pm.*,post.subject,post.time FROM `' . _mysql_prefix . '-pm` AS pm JOIN `' . _mysql_prefix . '-posts` AS post ON (post.type=6 AND post.home=pm.id AND post.xhome=-1) WHERE pm.id=' . $id . ' AND (sender=' . _loginid . ' AND sender_deleted=0 OR receiver=' . _loginid . ' AND receiver_deleted=0)');
            if ($q === false) {
                $module .= _formMessage(3, $_lang['global.badinput']);
                break;
            }

            // titulek
            $mod_title = 'mod.messages.read';

            // stavy
            $locked = ($q['sender_deleted'] || $q['receiver_deleted']);
            list($role, $role_other) = (($q['sender'] == _loginid) ? array('sender', 'receiver') : array('receiver', 'sender'));

            // citace neprectenych zprav
            $counter = DB::result(DB::query('SELECT COUNT(*) FROM `' . _mysql_prefix . '-posts` WHERE home=' . $q['id'] . ' AND type=6 AND time>' . $q[$role_other . '_readtime']), 0);
            $counter_s = array('', '');
            $counter_s[($role === 'sender' ? 1 : 0)] = ' (' . $counter . ')';

            // vystup
            require_once (_indexroot . 'require/functions-posts.php');
            $module .= "<h2>" . $_lang['mod.messages.message'] . ": " . $q['subject'] . "</h2>
<p><small>" . $_lang['global.postauthor'] . ' ' . _linkUser($q['sender']) . $counter_s[0] . ' ' . $_lang['mod.messages.receiver.inview'] . ' ' . _linkUser($q['receiver']) . $counter_s[1] . ' ' . _formatTime($q['time']) . "</small></p>\n";
            $module .= _postsOutput(7, $q['id'], array($locked), false, $_SERVER['REQUEST_URI']);

            // aktualizace casu precteni
            DB::query('UPDATE `' . _mysql_prefix . '-pm` SET ' . $role . '_readtime=UNIX_TIMESTAMP() WHERE id=' . $id);

            break;
        }

        // je vypis
        $list = true;

        // smazani vzkazu
        if (isset($_POST['action'])) {

            // promenne
            $valid = false;
            $delcond = null;
            $delcond_sadd = null;
            $delcond_radd = null;

            // akce
            switch ($_POST['action']) {

                case 1:
                    if (!isset($_POST['msg']) || !is_array($_POST['msg'])) break;
                    $valid = true;
                    $delcond = array();
                    for($i = 0; isset($_POST['msg'][$i]); ++$i) $delcond[] = intval($_POST['msg'][$i]);
                    $delcond = 'id IN(' . implode(',', $delcond) . ')';
                    break;

                case 2:
                    $valid = true;
                    $delcond_sadd = 'sender_readtime>=update_time';
                    $delcond_radd = 'receiver_readtime>=update_time';
                    break;

                case 3:
                    $valid = true;
                    break;

            }

            // smazani a info
            if ($valid) {

                // vyhledani vzkazu ke smazani
                $q = DB::query('SELECT id,sender,sender_deleted,receiver,receiver_deleted FROM `' . _mysql_prefix . '-pm` WHERE (sender=' . _loginid . ' AND sender_deleted=0' . (isset($delcond_sadd) ? ' AND ' . $delcond_sadd : '') . ' OR receiver=' . _loginid . ' AND receiver_deleted=0' . (isset($delcond_radd) ? ' AND ' . $delcond_radd : '') . ')' . ((isset($delcond)) ? ' AND ' . $delcond : ''));
                $del_list = array();
                while ($r = DB::row($q)) {

                    // zjisteni roli
                    list($role, $role_other) = (($r['sender'] == _loginid) ? array('sender', 'receiver') : array('receiver', 'sender'));

                    // smazani nebo oznaceni
                    if ($r[$role_other . '_deleted']) {
                        // druha strana jiz smazala, smazat uplne
                        $del_list[] = $r['id'];
                    } else {
                        // pouze oznacit
                        DB::query('UPDATE `' . _mysql_prefix . '-pm` SET ' . $role . '_deleted=1 WHERE id=' . $r['id']);
                    }

                }

                // fyzicke vymazani
                if (!empty($del_list)) DB::query('DELETE `' . _mysql_prefix . '-pm`,post FROM `' . _mysql_prefix . '-pm` JOIN `' . _mysql_prefix . '-posts` AS post ON (post.type=6 AND post.home=`' . _mysql_prefix . '-pm`.id) WHERE `' . _mysql_prefix . '-pm`.id IN(' . implode(',', $del_list) . ')');

                // info
                $module .= _formMessage(1, $_lang['mod.messages.delete.done']);

            }

        }

        // strankovani
        $paging = _resultPaging(_indexOutput_url, _messagesperpage, 'pm', 'sender=' . _loginid . ' OR receiver=' . _loginid, '&amp;a=' . $a);
        if (_pagingmode == 1 or _pagingmode == 2) $module .= $paging[0];

        // tabulka
        $module .= "
        <form method='post' action=''>
<p class='messages-menu'>
    <img src='" . _templateImage('icons/bubble.png') . "' alt='new' class='icon' /><a href='" . _indexOutput_url . "&amp;a=new'>" . $_lang['mod.messages.new'] . "</a>
</p>

<table class='messages-table'>
<tr><td width='10'><input type='checkbox' name='selector' onchange=\"var that=this;$('table.messages-table input').each(function(){this.checked=that.checked;});\" /></td><td><strong>" . $_lang['mod.messages.message'] . "</strong></td><td><strong>" . $_lang['global.user'] . "</strong></td><td><strong>" . $_lang['mod.messages.time.update'] . "</strong></td></tr>\n";
        $q = DB::query('SELECT pm.id,pm.sender,pm.receiver,pm.sender_readtime,pm.receiver_readtime,pm.update_time,post.subject,(SELECT COUNT(*) FROM `' . _mysql_prefix . '-posts` AS countpost WHERE countpost.home=pm.id AND countpost.type=6 AND (pm.sender=' . _loginid . ' AND countpost.time>pm.receiver_readtime OR pm.receiver=' . _loginid . ' AND countpost.time>pm.sender_readtime)) AS unread_counter FROM `' . _mysql_prefix . '-pm` AS pm JOIN `' . _mysql_prefix . '-posts` AS post ON (post.home=pm.id AND post.type=6 AND post.xhome=-1) WHERE pm.sender=' . _loginid . ' AND pm.sender_deleted=0 OR pm.receiver=' . _loginid . ' AND pm.receiver_deleted=0 ORDER BY pm.update_time DESC ' . $paging[1]);
        while ($r = DB::row($q)) {
            $read = ($r['sender'] == _loginid && $r['sender_readtime'] >= $r['update_time'] || $r['receiver'] == _loginid && $r['receiver_readtime'] >= $r['update_time']);
            $module .= "<tr><td><input type='checkbox' name='msg[]' value='" . $r['id'] . "' /></td><td><a href='" . _indexOutput_url . "&amp;a=list&amp;read=" . $r['id'] . "'" . ($read ? '' : ' class="notreaded"') . ">" . $r['subject'] . "</a></td><td>" . _linkUser(($r['sender'] == _loginid) ? $r['receiver'] : $r['sender']) . " <small>(" . $r['unread_counter'] . ")</small></td><td>" . _formatTime($r['update_time']) . "</td></tr>\n";
        }
        if (!isset($read)) $module .= "<tr><td colspan='4'>" . $_lang['mod.messages.nokit'] . "</td></tr>\n";

        $module .= "
<tr><td colspan='4'>
    <div class='hr'><hr /></div>
    <select name='action'>
    <option value='1'>" . $_lang['mod.messages.delete.selected'] . "</option>
    <option value='2'>" . $_lang['mod.messages.delete.readed'] . "</option>
    <option value='3'>" . $_lang['mod.messages.delete.all'] . "</option>
    </select>
    <input type='submit' value='" . $_lang['global.do'] . "' onclick='return _sysConfirm();' />
</td></tr>

</table>
" . _xsrfProtect() . "</form>\n";

        // strankovani dole
        if (_pagingmode == 2 or _pagingmode == 3) $module .= '<br />' . $paging[0];

        break;

}

// zpetny odkaz, titulek
$start = '';
if (!$list) $start .= "<a href='" . _indexOutput_url . "' class='backlink'>&lt; " . $_lang['global.return'] . "</a>\n";
if (_template_autoheadings == 1) $start .= "<h1>" . $_lang[$mod_title] . "</h1>\n";
if (!$list) $start .= "<div class='hr'><hr /></div>\n";
$module = $start . $module;
