<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  akce  --- */
$sysgroups_array = array(1, 2, 3);
$msg = 0;

// vytvoreni skupiny
if (isset($_POST['type']) and _loginright_admingroups) {
    $type = intval($_POST['type']);
    if ($type == -1) {
        // prazdna skupina
        DB::query("INSERT INTO `" . _mysql_prefix . "-groups` (title,level,icon) VALUES ('" . $_lang['admin.users.groups.new.empty'] . "',0,'')");
        $msg = 1;
    } else {
        // kopirovat skupinu
        $cgroup = DB::query("SELECT * FROM `" . _mysql_prefix . "-groups` WHERE id=" . $type);
        if (DB::size($cgroup) != 0) {
            $cgroup = DB::row($cgroup);
            $ngroup = array();
            $skip = false;
            $columns = "";
            $values = "";

            // sesbirani dat
            foreach ($cgroup as $column => $val) {

                $quotes = "";
                switch ($column) {
                    case "id":
                        $val = 'NULL';
                        break;
                    case "level":
                        if ($val >= 10000) {
                            $val = 9999;
                        }
                        if ($val >= _loginright_level) {
                            $val = _loginright_level - 1;
                        }
                        break;
                    case "title":
                        $val = $_lang['global.copy'] . " - " . $val;
                        $quotes = "'";
                        break;
                    case "descr":
                        $val = '';
                        $quotes = "'";
                        break;
                    case "icon":
                    case "color":
                        $quotes = "'";
                        break;
                    case "blocked":
                    case "reglist":
                        /*nic*/
                        break;
                    default:
                        /*prava*/
                        if (_userHasNotRight($column)) $val = 0;
                        break;
                }
                $ngroup[$column] = $val;
                $columns .= $column . ",";
                $values .= $quotes . $val . $quotes . ",";

            }

            // sql dotaz
            $columns = trim($columns, ",");
            $values = trim($values, ",");
            DB::query("INSERT INTO `" . _mysql_prefix . "-groups` (" . $columns . ") VALUES (" . $values . ")");
            $msg = 1;

        } else {
            $msg = 4;
        }
    }
}

/* ---  pripava promennych  --- */

// vypis skupin
if (_loginright_admingroups) {
    $groups = "<table class='list'>
<thead><tr><td>" . $_lang['global.name'] . "</td><td>" . $_lang['admin.users.groups.level'] . "</td><td>" . $_lang['admin.users.groups.members'] . "</td><td>" . $_lang['global.action'] . "</td></tr></thead>
<tbody>";
    $query = DB::query("SELECT id,title,icon,color,blocked,level,reglist,(SELECT COUNT(id) FROM `" . _mysql_prefix . "-users` WHERE `group`=`" . _mysql_prefix . "-groups`.id) AS user_count FROM `" . _mysql_prefix . "-groups` ORDER BY level DESC");
    while ($item = DB::row($query)) {
        $is_sys = in_array($item['id'], $sysgroups_array);
        $groups .= "
  <tr>
  <td><a href='index.php?p=users-editgroup&amp;id=" . $item['id'] . "'" . (($item['blocked'] == 1) ? " class='invisible'" : '') . (($item['color'] !== '') ? " style='color:" . $item['color'] . ";'" : '') . ">" . (($item['reglist'] == 1) ? "<img src='images/icons/list.png' alt='reglist' class='icon' title='" . $_lang['admin.users.groups.reglist'] . "' />" : '') . (($item['icon'] != "") ? "<img src='" . _indexroot . "pictures/groupicons/" . $item['icon'] . "' alt='icon' class='groupicon' /> " : '') . ($is_sys ? "<u>" : '') . $item['title'] . ($is_sys ? "</u>" : '') . "</a></td>
  <td>" . $item['level'] . "</td>
  <td><a href='index.php?p=users-list&amp;group=" . $item['id'] . "'>" . (($item['id'] != 2) ? $item['user_count'] : "-") . "</a></td>
  <td><a href='index.php?p=users-delgroup&amp;id=" . $item['id'] . "' title='" . $_lang['global.delete'] . "'><img src='images/icons/delete.png' alt='del' class='icon' />" . $_lang['global.delete'] . "</a></td>
  </tr>\n";
    }
    $groups .= "</tbody>\n</table>";
} else {
    $groups = "";
}

// zprava
switch ($msg) {
    case 1:
        $message = _formMessage(1, $_lang['global.done']);
        break;
    case 2:
        $message = _formMessage(2, $_lang['admin.users.groups.specialgroup.delnotice']);
        break;
    case 3:
        $message = _formMessage(3, $_lang['global.disallowed']);
        break;
    case 4:
        $message = _formMessage(3, $_lang['global.badgroup']);
        break;
    default:
        $message = "";
        break;
}

/* ---  vystup  --- */
$output .= "
<p>" . $_lang['admin.users.p'] . "</p>

" . $message . "

<table class='wintable'>
<tr class='valign-top'>

  " . (_loginright_adminusers ? "
  <td" . (_loginright_admingroups ? " style='width: 40%;' class='rbor'" : '') . ">
  <h2>" . $_lang['admin.users.users'] . "</h2>
  <p class='bborder'>
<a href='index.php?p=users-edit'><img src='images/icons/new.png' alt='new' class='icon' />" . $_lang['global.create'] . "</a>
<span style='color:#b2b2b2;'>&nbsp;&nbsp;|&nbsp;&nbsp;</span>
<a href='index.php?p=users-list'><img src='images/icons/action.png' alt='act' class='contenttable-icon' />" . $_lang['admin.users.list'] . "</a>&nbsp;&nbsp;
<a href='index.php?p=users-move'><img src='images/icons/action.png' alt='act' class='contenttable-icon' />" . $_lang['admin.users.move'] . "</a></p>

  <div class='lpad'>

    <form class='cform' action='index.php' method='get' name='edituserform'" . _jsCheckForm("edituserform", array("id")) . ">
    <input type='hidden' name='p' value='users-edit' />
    <strong>" . $_lang['admin.users.edituser'] . "</strong><br /><input type='text' name='id' class='inputsmall' />
    <input type='submit' value='" . $_lang['global.continue'] . "' />
    </form><br />

    <form class='cform' action='index.php' method='get' name='deleteuserform'" . _jsCheckForm("deleteuserform", array("id")) . ">
    <input type='hidden' name='p' value='users-delete' />
    " . _xsrfProtect() . "
    <strong>" . $_lang['admin.users.deleteuser'] . "</strong><br /><input type='text' name='id' class='inputsmall' />
    <input type='submit' value='" . $_lang['global.do'] . "' onclick='return _sysConfirm();' />
    </form>

  </div>

  </td>
  " : '') . "


  " . (_loginright_admingroups ? "<td>
  <h2>" . $_lang['admin.users.groups'] . "</h2>
  <form action='index.php?p=users' method='post'><p class='bborder'><strong>" . $_lang['admin.users.groups.new'] . ":</strong> " . _admin_authorSelect("type", -1, "1", null, $_lang['admin.users.groups.new.empty'], true) . " <input type='submit' value='" . $_lang['global.do'] . "' /></p>" . _xsrfProtect() . "</form>
  " . $groups . "
  </td>
  " : '') . "


</tr>
</table>
";
