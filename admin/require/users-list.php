<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  vystup  --- */

$output .= "<p>" . $_lang['admin.users.list.p'] . "</p>";

// filtr skupiny
$grouplimit = "";
$grouplimit2 = "1";
if (isset($_GET['group'])) {
    $group = intval($_GET['group']);
    if ($group != -1) {
        $grouplimit = " AND `" . _mysql_prefix . "-groups`.id=" . $group;
        $grouplimit2 = "`group`=" . $group;
    }
} else {
    $group = -1;
}

// aktivace vyhledavani
if (isset($_GET['search']) and $_GET['search'] != "") {
    $search = true;
    $searchword = DB::esc($_GET['search']);
} else {
    $search = false;
}

// filtry - vyber skupiny, vyhledavani
$output .= '
  <table class="wintable">
  <tr>

  <td>
  <form class="cform" action="index.php" method="get">
  <input type="hidden" name="p" value="users-list" />
  <input type="hidden" name="search"' . _restoreGetValue('search', '') . ' />
  <strong>' . $_lang['admin.users.list.groupfilter'] . ':</strong> ' . _admin_authorSelect("group", $group, "id!=2", null, $_lang['global.all'], true) . '
  </select> <input type="submit" value="' . $_lang['global.apply'] . '" />
  </form>
  </td>

  <td>
  <form class="cform" action="index.php" method="get">
  <input type="hidden" name="p" value="users-list" />
  <input type="hidden" name="group" value="' . $group . '" />
  <strong>' . $_lang['admin.users.list.search'] . ':</strong> <input type="text" name="search" class="inputsmall"' . _restoreGetValue('search') . ' /> <input type="submit" value="' . $_lang['mod.search.submit'] . '" />
  ' . ($search ? '&nbsp;<a href="index.php?p=users-list&amp;group=' . $group . '">' . $_lang['global.cancel'] . '</a>' : '') . '
  </form>
  </td>

  </tr>
  </table>
  ';

// tabulka

// priprava strankovani
if (!$search) {
    $paging = _resultPaging("index.php?p=users-list&amp;group=" . $group, 50, "users", $grouplimit2);
    $output .= $paging[0];
}

// tabulka
$output .= "<br />
  <table class='list'>
  <thead><tr><td>ID</td><td>" . $_lang['login.username'] . "</td><td>" . $_lang['global.email'] . "</td><td>" . $_lang['mod.settings.publicname'] . "</td><td colspan='2'>" . $_lang['global.group'] . "</td></tr></thead>
  <tbody>
  ";

// dotaz na db
if (!$search) {
    $query = DB::query("SELECT `" . _mysql_prefix . "-users`.id, `" . _mysql_prefix . "-users`.username, `" . _mysql_prefix . "-users`.publicname, `" . _mysql_prefix . "-users`.levelshift, `" . _mysql_prefix . "-users`.email, `" . _mysql_prefix . "-groups`.title, `" . _mysql_prefix . "-groups`.icon, `" . _mysql_prefix . "-users`.id FROM `" . _mysql_prefix . "-users`, `" . _mysql_prefix . "-groups` WHERE `" . _mysql_prefix . "-users`.`group`=`" . _mysql_prefix . "-groups`.id" . $grouplimit . " ORDER BY `" . _mysql_prefix . "-groups`.level DESC,`" . _mysql_prefix . "-users`.id " . $paging[1]);
} else {
    $query = DB::query("SELECT `" . _mysql_prefix . "-users`.username, `" . _mysql_prefix . "-users`.publicname, `" . _mysql_prefix . "-users`.levelshift, `" . _mysql_prefix . "-users`.email, `" . _mysql_prefix . "-groups`.title, `" . _mysql_prefix . "-groups`.icon, `" . _mysql_prefix . "-users`.id FROM `" . _mysql_prefix . "-users`, `" . _mysql_prefix . "-groups` WHERE `" . _mysql_prefix . "-users`.`group`=`" . _mysql_prefix . "-groups`.id AND (`" . _mysql_prefix . "-users`.username LIKE '%" . $searchword . "%' OR `" . _mysql_prefix . "-users`.publicname LIKE '%" . $searchword . "%' OR `" . _mysql_prefix . "-users`.email LIKE '%" . $searchword . "%' OR `" . _mysql_prefix . "-users`.ip LIKE '%" . $searchword . "%')" . $grouplimit . " ORDER BY `" . _mysql_prefix . "-groups`.level DESC,`" . _mysql_prefix . "-users`.id LIMIT 100");
}

// vypis
if (DB::size($query) != 0) {
    while ($item = DB::row($query)) {
        $output .= "<tr><td>" . $item['id'] . "</td><td>" . (($item['icon'] != "") ? "<img src='" . _indexroot . "pictures/groupicons/" . $item['icon'] . "' alt='icon' class='groupicon' /> " : '') . "<a href='index.php?p=users-edit&amp;id=" . $item['username'] . "'>" . (($item['levelshift'] == 1) ? "<strong>" : '') . $item['username'] . (($item['levelshift'] == 1) ? "</strong>" : '') . "</a></td><td>" . $item['email'] . "</td><td>" . (($item['publicname'] != "") ? $item['publicname'] : "-") . "</td><td>" . $item['title'] . "</td><td><a href='" . _xsrfLink("index.php?p=users-delete&amp;id=" . $item['username']) . "' onclick='return _sysConfirm();'><img src='images/icons/delete.png' alt='del' class='icon' />" . $_lang['global.delete'] . "</a></td></tr>\n";
    }
} else {
    $output .= "<tr><td colspan='5'>" . $_lang['global.nokit'] . "</td></tr>\n";
}

$output .= "</tbody></table>";

// pocet uzivatelu
$totalusers = DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-users`"), 0);
$output .= "\n<br />" . $_lang['admin.users.list.totalusers'] . ": " . $totalusers;
