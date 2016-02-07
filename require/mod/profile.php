<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  priprava  --- */
$form = false;
$message = "";
$id = null;

if (isset($_GET['id'])) {
    $id = DB::esc($_GET['id']);
    $query = DB::query("SELECT * FROM `" . _mysql_prefix . "-users` WHERE username='" . $id . "'");
    if (DB::size($query) != 0) {
        $query = DB::row($query);
        $groupdata = DB::query_row("SELECT title,descr,icon,color,blocked FROM `" . _mysql_prefix . "-groups` WHERE id=" . $query['group']);
        $form = true;

        // promenne
        if ($query['note'] == "") $note = "";
        else $note = "<tr class='valign-top'><td><strong>" . $_lang['global.note'] . "</strong></td><td><div class='note'>" . _parsePost($query['note']) . "</div></td></tr>";

        // cesta k avataru
        $query['avatar'] = _getAvatar($query['id'], true, false, true);

        // clanky autora
        $arts = DB::result(DB::query("SELECT COUNT(*) FROM `" . _mysql_prefix . "-articles` AS art WHERE author=" . $query['id'] . " AND " . _sqlArticleFilter()), 0);
        if ($arts != 0) {

            // zjisteni prumerneho hodnoceni
            $avgrate = DB::result(DB::query("SELECT ROUND(SUM(ratesum)/SUM(ratenum)) FROM `" . _mysql_prefix . "-articles` WHERE rateon=1 AND ratenum!=0 AND confirmed=1 AND author=" . $query['id']), 0);
            if ($avgrate === null) $avgrate = $_lang['article.rate.nodata'];
            else $avgrate = "&Oslash; " . $avgrate . "%";

            // sestaveni kodu
            $arts = "\n<tr><td><strong>" . $_lang['global.articlesnum'] . "</strong></td><td>" . $arts . ", <a href='index.php?m=profile-arts&amp;id=" . $id . "'>" . $_lang['global.show'] . " &gt;</a></td></tr>\n";
            if (_ratemode != 0) $arts .= "\n<tr><td><strong>" . $_lang['article.rate'] . "</strong></td><td>" . $avgrate . "</td></tr>\n";

        } else {
            $arts = "";
        }

        // odkaz na prispevky uzivatele
        $posts_count = DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-posts` WHERE author=" . $query['id'] . ' AND `type`!=6 AND `type`!=4'), 0);
        if ($posts_count > 0) {
            $posts_viewlink = ", <a href='index.php?m=profile-posts&amp;id=" . $id . "'>" . $_lang['global.show'] . " &gt;</a>";
        } else {
            $posts_viewlink = "";
        }

    } else {
        $message = _formMessage(2, $_lang['global.baduser']);
        $found = false;
    }
}

/* ---  modul  --- */

// titulek
if (_template_autoheadings == 1) {
    $module .= "<h1>" . $_lang['mod.profile'] . "</h1><br />";
}

// vyhledavaci pole
$module .= "
  <form action='index.php' method='get' class='profileform'>
  <input type='hidden' name='m' value='profile' />
  <input type='text' name='id'" . (($id != null) ? " value='" . $id . "'" : '') . " class='inputmedium' /> <input type='submit' value='" . $_lang['global.open'] . "' />
  </form><br />
  " . $message;

// tabulka
if ($form == true) {

    // poznamka o blokovani
    if ($query['blocked'] == 1 or $groupdata['blocked'] == 1) {
        $module .= "\n<strong class='important'>" . $_lang['mod.profile.blockednote'] . "</strong><br /><br />\n";
    }

    $module .= "
  <table>

  <tr class='valign-top'>

  <td class='avatartd'>
  <div class='avatar'>
  <img src='" . $query['avatar'] . "' alt='avatar' />
  </div>
  </td>

  <td>
    <table class='profiletable'>

    <tr>
    <td><strong>" . $_lang['login.username'] . "</strong></td>
    <td>" . $query['username'] . "</td>
    </tr>

    " . (($query['publicname'] != "") ? "<tr><td><strong>" . $_lang['mod.settings.publicname'] . "</strong></td><td>" . $query['publicname'] . "</td></tr>" : '') . "

    <tr>
    <td><strong>" . $_lang['global.group'] . "</strong></td>
    <td>" . (($groupdata['icon'] != "") ? "<img src='" . _indexroot . "pictures/groupicons/" . $groupdata['icon'] . "' alt='icon' class='icon' /> " : '') . (($groupdata['color'] !== '') ? '<span style="color:' . $groupdata['color'] . ';">' . $groupdata['title'] . '</span>' : $groupdata['title']) . "</td>
    </tr>

    " . (($groupdata['descr'] !== '') ? "<tr>
    <td><strong>" . $_lang['mod.profile.groupdescr'] . "</strong></td>
    <td>" . $groupdata['descr'] . "</td>
    </tr>" : '') . "

    <tr>
    <td><strong>" . $_lang['mod.profile.lastact'] . "</strong></td>
    <td>" . _formatTime($query['activitytime']) . "</td>
    </tr>

    </table>
  </td>

  </tr>
  </table>

  <div class='hr'><hr /></div>

  <div class='wlimiter'>
  <table class='profiletable'>

  <tr><td><strong>" . $_lang['mod.profile.regtime'] . "</strong></td><td>" . _formatTime($query['registertime']) . "</td></tr>
  " . (_profileemail ? "<tr><td><strong>" . $_lang['global.email'] . "</strong></td><td>" . _mailto($query['email']) . "</td></tr>" : '') . "
  " . (($query['icq'] != 0) ? "<tr><td><strong>" . $_lang['global.icq'] . "</strong></td><td>" . $query['icq'] . " <img src='http://status.icq.com/online.gif?icq=" . $query['icq'] . "&amp;img=5' alt='icq status' class='icon' /></td></tr>" : '') . "
  " . (($query['skype'] != "") ? "<tr><td><strong>" . $_lang['global.skype'] . "</strong></td><td>" . $query['skype'] . "</td></tr>" : '') . "
  " . (($query['msn'] != "") ? "<tr><td><strong>" . $_lang['global.msn'] . "</strong></td><td>" . _mailto(_htmlStr($query['msn'])) . "</td></tr>" : '') . "
  " . (($query['jabber'] != "") ? "<tr><td><strong>" . $_lang['global.jabber'] . "</strong></td><td>" . _mailto(_htmlStr($query['jabber'])) . "</td></tr>" : '') . "
  " . (($query['web'] != "") ? "<tr><td><strong>" . $_lang['global.web'] . "</strong></td><td><a href='http://" . $query['web'] . "' target='_blank' rel='nofollow'>" . _cutStr($query['web'], 32) . "</a></td></tr>" : '') . "

  <tr>
  <td><strong>" . $_lang['global.postsnum'] . "</strong></td>
  <td>" . $posts_count . $posts_viewlink . "</td>
  </tr>

  " . $arts . "
  " . $note . "

  <tr>
  <td><strong>" . $_lang['mod.profile.logincounter'] . "</strong></td>
  <td>" . $query['logincounter'] . "</td>
  </tr>

  </table>
  </div>
  ";

    // odkaz na zaslani vzkazu
    if (_loginindicator and _messages and $query['id'] != _loginid and $query['blocked'] == 0 and $groupdata['blocked'] == 0) {
        $module .= "<p><img src='" . _templateImage("icons/bubble.png") . "' alt='msg' class='icon' /> <a href='index.php?m=messages&amp;a=new&amp;receiver=" . $query['username'] . "'>" . $_lang['mod.messages.new'] . " &gt;</a></p>";
    }

}
