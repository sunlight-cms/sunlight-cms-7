<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  pripava promennych  --- */
$levelconflict = false;
$sysgroups_array = array(1, 2 /*,3 is not necessary*/ );
$unregistered_useable = array("postcomments", "artrate", "pollvote");

// id
$continue = false;
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $query = DB::query("SELECT * FROM `" . _mysql_prefix . "-groups` WHERE id=" . $id);
    if (DB::size($query) != 0) {
        $query = DB::row($query);
        $systemitem = in_array($query['id'], $sysgroups_array);
        if (_loginright_level > $query['level']) {
            $continue = true;
        } else {
            $levelconflict = true;
        }
    }
}

if ($continue) {

    // pole prav
    $rights_array = array("changeusername", "postcomments", "locktopics", "stickytopics", "movetopics", "unlimitedpostaccess", "artrate", "pollvote", "adminposts", "selfdestruction", "-" . $_lang['admin.users.groups.adminrights'], "administration", "adminsettings", "adminusers", "admingroups", "adminfman", "adminfmanlimit", "adminfmanplus", "adminhcmphp", "adminbackup", "adminrestore", "adminmassemail", "adminbans", "-" . $_lang['admin.users.groups.admincontentrights'], "admincontent", "adminconfirm", "adminneedconfirm", "adminart", "adminallart", "adminchangeartauthor", "adminsection", "admincategory", "adminbook", "adminseparator", "admingallery", "adminlink", "adminintersection", "adminforum", "adminpluginpage", "adminpoll", "adminpollall", "adminsbox", "adminbox");

    $rights = "";
    foreach ($rights_array as $item) {
        if (($id == 2 and !in_array($item, $unregistered_useable)) or (mb_substr($item, 0, 1) != "-" and _userHasNotRight($item))) {
            continue;
        }
        if (mb_substr($item, 0, 1) != "-") {
            $rights .= "
        <tr>
        <td><strong>" . $_lang['admin.users.groups.' . $item] . "</strong></td>
        <td><input type='checkbox' name='$item' value='1'" . _checkboxActivate($query[$item]) . _inputDisable($id != 1) . " /></td>
        <td class='lpad'>" . $_lang['admin.users.groups.' . $item . '.help'] . "</td>
        </tr>
        ";
        } else {
            $rights .= "</table></fieldset><fieldset><legend>" . mb_substr($item, 1) . "</legend><table>";
        }
    }

    /* ---  ulozeni  --- */
    if (isset($_POST['title'])) {

        $newdata = array();

        // zakladni atributy
        $newdata['title'] = DB::esc(_htmlStr(trim($_POST['title'])));
        if ($newdata['title'] == "") $newdata['title'] = DB::esc($_lang['global.novalue']);
        $newdata['descr'] = DB::esc(_htmlStr(trim($_POST['descr'])));
        if ($id != 2) $newdata['icon'] = DB::esc(_htmlStr(trim($_POST['icon'])));
        $newdata['color'] = DB::esc(preg_replace('/([^0-9a-zA-Z#])/s', '', trim($_POST['color'])));
        if ($id > 2) $newdata['blocked'] = _checkboxLoad("blocked");
        if ($id != 2) $newdata['reglist'] = _checkboxLoad("reglist");

        // uroven, blokovani
        if ($id > 2) {
            $newdata['level'] = intval($_POST['level']);
            if ($newdata['level'] > _loginright_level) $newdata['level'] = _loginright_level - 1;
            if ($newdata['level'] >= 10000) $newdata['level'] = 9999;
            if ($newdata['level'] < 0) $newdata['level'] = 0;
        }

        // prava
        if ($id != 1) {
            foreach ($rights_array as $item) {
                if (($id == 2 and !in_array($item, $unregistered_useable)) or _userHasNotRight($item)) continue;
                $newdata[$item] = _checkboxLoad($item);
            }
        }

        // ulozeni
        $sql = '';
        $last = sizeof($newdata) - 1;
        $counter = 0;
        foreach ($newdata as $col => $val) {
            $sql .= '`' . $col . '`=\'' . $val . '\'';
            if ($counter !== $last) $sql .= ',';
            ++$counter;
        }
        DB::query('UPDATE `' . _mysql_prefix . '-groups` SET ' . $sql . ' WHERE id=' . $id);

        // reload stranky
        define('_redirect_to', 'index.php?p=users-editgroup&id=' . $id . '&saved');

        return;

    }

    /* ---  vystup  --- */
    $output .= "
  <p class='bborder'>" . $_lang['admin.users.groups.editp'] . "</p>
  " . (isset($_GET['saved']) ? _formMessage(1, $_lang['global.saved']) : '') . "
  " . ($systemitem ? _admin_smallNote($_lang['admin.users.groups.specialgroup.editnotice']) : '') . "
  <form action='index.php?p=users-editgroup&amp;id=" . $id . "' method='post'>
  <table>

  <tr>
  <td><strong>" . $_lang['global.name'] . "</strong></td>
  <td><input type='text' name='title' class='inputmedium' value='" . $query['title'] . "' maxlength='32' /></td>
  </tr>

  <tr>
  <td><strong>" . $_lang['global.descr'] . "</strong></td>
  <td><input type='text' name='descr' class='inputmedium' value='" . $query['descr'] . "' maxlength='128' /></td>
  </tr>

  <tr>
  <td class='rpad'><strong>" . $_lang['admin.users.groups.level'] . "</strong></td>
  <td><input type='text' name='level' class='inputmedium' value='" . $query['level'] . "'" . _inputDisable(!$systemitem) . " /></td>
  </tr>

  " . (($id != 2) ? "
  <tr><td><strong>" . $_lang['admin.users.groups.icon'] . "</strong></td><td><input type='text' name='icon' class='inputsmall' value='" . $query['icon'] . "' maxlength='16' /></td></tr>
  <tr><td><strong>" . $_lang['admin.users.groups.color'] . "</strong></td><td><input type='text' name='color' class='inputsmall' value='" . $query['color'] . "' maxlength='16' /></td></tr>
  <tr><td class='rpad'><strong>" . $_lang['admin.users.groups.reglist'] . "</strong></td><td><input type='checkbox' name='reglist' value='1'" . _checkboxActivate($query['reglist']) . " /></td></tr>
  " : '') . "

  <tr>
  <td class='rpad'><strong>" . $_lang['admin.users.groups.blocked'] . "</strong></td>
  <td><input type='checkbox' name='blocked' value='1'" . _checkboxActivate($query['blocked']) . _inputDisable($id != 1 and $id != 2) . " /></td>
  </tr>

  </table><br />

  <fieldset>
  <legend>" . $_lang['admin.users.groups.commonrights'] . "</legend>
  <table>

  " . $rights . "


  </table></fieldset><br />


  <br />
  <input type='submit' value='" . $_lang['global.save'] . "' />&nbsp;&nbsp;<small>" . $_lang['admin.content.form.thisid'] . " " . $id . "</small>

  " . _xsrfProtect() . "</form>
  ";

} else {
    if ($levelconflict == false) {
        $output .= _formMessage(3, $_lang['global.badinput']);
    } else {
        $output .= _formMessage(3, $_lang['global.disallowed']);
    }
}
