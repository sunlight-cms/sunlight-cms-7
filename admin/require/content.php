<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  priprava  --- */
$message = "";

$ppages = _admin_getPluginPageInfos();

if (_loginright_adminsection or _loginright_admincategory or _loginright_adminbook or _loginright_adminseparator or _loginright_admingallery or _loginright_adminlink or _loginright_adminintersection or _loginright_adminforum or _loginright_adminpluginpage) {

    // akce
    if (isset($_POST['ac'])) {
        $ac = intval($_POST['ac']);

        switch ($ac) {

                // ulozeni poradovych cisel
            case 1:
                foreach ($_POST as $id => $ord) {
                    if ($id == "ac") {
                        continue;
                    }
                    $id = intval($id);
                    $ord = floatval($ord);
                    DB::query("UPDATE `" . _mysql_prefix . "-root` SET ord=" . $ord . " WHERE id=" . $id);
                }
                break;

                // vytvoreni stranky
            case 2:
                $is_ppage = false;
                if (is_numeric($_POST['type'])) $type = intval($_POST['type']);
                else {
                    $type = 9;
                    $type_idt = strval($_POST['type']);
                    if (!isset($ppages[$type_idt])) break;
                    $is_ppage = true;
                }
                if (isset($type_array[$type])) {
                    if (constant('_loginright_admin' . $type_array[$type])) {
                        define('_redirect_to', 'index.php?p=content-edit' . $type_array[$type] . ($is_ppage ? '&idt=' . urlencode($type_idt) : ''));

                        return;
                    }
                }
                break;

        }

    }

    // horni panel

    // seznam typu stranek
    $create_list = "";
    foreach ($type_array as $type => $name) {
        if ($type == 9) continue;
        if (constant('_loginright_admin' . $name)) {
            $create_list .= "<option value='" . $type . "'>" . $_lang['admin.content.' . $name] . "</option>\n";
        }
    }

    // seznam pluginovych typu stranek
    if (_loginright_adminpluginpage && !empty($ppages)) {
        $create_list .= "<option value='' disabled='disabled'>---</option>\n";
        foreach($ppages as $ppage_idt => $ppage_label) $create_list .= "<option value='" . $ppage_idt . "'>" . $ppage_label . "</option>\n";
    }

    $rootitems = '
    <td class="contenttable-box" style="' . ((_loginright_adminart or _loginright_adminconfirm or _loginright_admincategory or _loginright_adminpoll or _loginright_adminsbox or _loginright_adminbox) ? 'width: 75%; ' : 'border-right: none;') . 'padding-bottom: 0px;">

    <form action="index.php?p=content" method="post" class="inline">
    <input type="hidden" name="ac" value="2" />
    <img src="images/icons/new.png" alt="new" class="contenttable-icon" />
    <select name="type">
    ' . $create_list . '
    </select>
    <input type="submit" value="' . $_lang['global.create'] . '" />
    ' . _xsrfProtect() . '</form>

    <span style="color:#b2b2b2;">&nbsp;&nbsp;|&nbsp;&nbsp;</span>

    <a href="index.php?p=content-setindex"><img src="images/icons/list.png" alt="act" class="contenttable-icon" />' . $_lang['admin.content.setindex'] . '</a>&nbsp;&nbsp;

    <span style="color:#b2b2b2;">&nbsp;&nbsp;|&nbsp;&nbsp;</span>

    <a href="index.php?p=content-move"><img src="images/icons/action.png" alt="move" class="contenttable-icon" />' . $_lang['admin.content.move'] . '</a>&nbsp;&nbsp;
    <a href="index.php?p=content-titles"><img src="images/icons/action.png" alt="titles" class="contenttable-icon" />' . $_lang['admin.content.titles'] . '</a>&nbsp;&nbsp;
    <a href="index.php?p=content-redir"><img src="images/icons/action.png" alt="redir" class="contenttable-icon" />' . $_lang['admin.content.redir'] . '</a>

    <div class="hr"><hr /></div>

    <form action="index.php?p=content" method="post">
    <input type="hidden" name="ac" value="1" />
    <div class="pad">
    <table id="contenttable-list">
    ';

    // funkce pro vypis polozky
    $counter = 0;
    function _admin_rootItemOutput($item, $itr)
    {
        global $_lang, $counter, $highlight, $ppages;
        $type_array = _admin_getTypeArray();

        // pristup k polozce
        if (!constant('_loginright_admin' . $type_array[$item['type']])) {
            $denied = true;
        } else {
            $denied = false;
        }

        // trida pro neviditelnost anebo neverejnost
        $sclass = "";
        if ($item['visible'] == 0 xor $item['public'] == 0) {
            if ($item['visible'] == 0) {
                $sclass = " class='invisible'";
            }
            if ($item['public'] == 0) {
                $sclass = " class='notpublic'";
            }
        } else {
            if ($item['visible'] == 0 and $item['public'] == 0) {
                $sclass = " class='invisible-notpublic'";
            } else {
                $sclass = " class='normal'";
            }
        }

        // pozadi oddelovace
        if ($item['type'] == 4) {
            $sepbg_start = "<div class='sep'" . (($counter == 0) ? " style='padding-top:0;'" : '') . "><div class='sepbg'>";
            $sepbg_end = "</div></div>";
            $highlight = false;
        } else {
            $sepbg_start = "";
            $sepbg_end = "";
            $sepbg_start_sub = "";
            $sepbg_end_sub = "";
        }

        // kod radku
        $dclass = "";
        if ($itr == true) {
            if ($highlight) {
                $dclass = " class='intersecpad-hl'";
            } else {
                $dclass = " class='intersecpad'";
            }
        } else {
            if ($highlight) {
                $dclass = " class='hl'";
            }
        }

        $extra_actions = '';
        if (!$denied) {
            if ($item['type'] == 5) {
                $extra_actions = "&nbsp;&nbsp;&nbsp;<a href='index.php?p=content-manageimgs&amp;g=" . $item['id'] . "'><img src='images/icons/list.png' alt='images' class='contenttable-icon' />" . $_lang['admin.content.form.showpics'] . "</a>";
            }
            _extend('call', 'admin.root.actions', array('item' => $item, 'extra_actions' => &$extra_actions));
        }

        $extendOutput = _extend('buffer', 'admin.root.item', array('item' => $item, 'denied' => $denied, 'extra_actions' => $extra_actions, 'class' => $dclass));
        if ('' !== $extendOutput) {
            if (false === $extendOutput) {
                return '';
            }

            return $extendOutput;
        }

        return "
        <tr" . $dclass . ">
        <td class='name'>" . $sepbg_start . "<input type='text' name='" . $item['id'] . "' value='" . $item['ord'] . "' />" . (($item['id'] == _index_page_id) ? "<img src='images/icons/tag.png' alt='index' class='contenttable-icon' />" : '') . "<a" . (($item['type'] != 4) ? " href='" . _indexroot . _linkRoot($item['id'], $item['title_seo']) . "' target='_blank'" . $sclass : '') . ">" . $item['title'] . "</a>" . $sepbg_end . "</td>
        <td class='type'" . ($denied ? " colspan='2'" : '') . ">" . $sepbg_start . "<div class='tpad'>" . (($item['type'] != 9) ? $_lang['admin.content.' . $type_array[$item['type']]] : (isset($ppages[$item['type_idt']]) ? $ppages[$item['type_idt']] : _htmlStr($item['type_idt']))) . " <small>(" . $item['id'] . ")</small></div>" . $sepbg_end . "</td>
        " . (!$denied ? "<td class='actions'>" . $sepbg_start . "<div class='tpad'><a href='index.php?p=content-edit" . $type_array[$item['type']] . "&amp;id=" . $item['id'] . "'><img src='images/icons/edit.png' alt='edit' class='contenttable-icon' />" . $_lang['global.edit'] . "</a>&nbsp;&nbsp;&nbsp;<a href='index.php?p=content-delete&amp;id=" . $item['id'] . "'><img src='images/icons/delete.png' alt='del' class='contenttable-icon' />" . $_lang['global.delete'] . "</a>" . $extra_actions . "</div>" . $sepbg_end . "</td>" : '') . "
        </tr>\n
        ";
    }

    // tabulka polozek
    $highlight = false;
    $query = DB::query("SELECT id,title,title_seo,type,type_idt,visible,public,ord FROM `" . _mysql_prefix . "-root` WHERE intersection=-1 ORDER BY ord");
    if (DB::size($query) != 0) {
        while ($item = DB::row($query)) {
            $rootitems .= _admin_rootItemOutput($item, false);
            $highlight = !$highlight;

            // polozky v rozcestniku
            if ($item['type'] == 7) {
                $iquery = DB::query("SELECT id,title,title_seo,type,type_idt,visible,public,ord FROM `" . _mysql_prefix . "-root` WHERE intersection=" . $item['id'] . " ORDER BY ord");
                while ($iitem = DB::row($iquery)) {
                    $rootitems .= _admin_rootItemOutput($iitem, true);
                    $highlight = !$highlight;
                }
            }

            $counter++;
        }
    } else {
        $rootitems .= "<tr><td colspan='3'>" . $_lang['global.nokit'] . "</td></tr>\n";
    }

    $rootitems .= '
</table></div>

<div class="hr"><hr /></div>
<input type="submit" value="' . $_lang['admin.content.saveord'] . '" />

' . _xsrfProtect() . '</form>

</td>
';
} else {
    $rootitems = "";
}

// odkazy pluginu
$other_links = array();
_extend('call', 'admin.content.links', array('links' => &$other_links));

/* ---  vystup  --- */

// zprava
if (isset($_GET['done'])) {
    $message = _formMessage(1, $_lang['global.done']);
}

$output .= '
<p>' . $_lang['admin.content.p'] . '</p>
' . $message . '
<table id="contenttable">
<tr class="valign-top">

  ' . $rootitems . '

  ' . ((_loginright_adminart or _loginright_adminconfirm or _loginright_admincategory or _loginright_adminpoll or _loginright_adminsbox or _loginright_adminbox) ? '<td class="contenttable-box" style="border: none ;">

      ' . ((_loginright_adminart or _loginright_adminconfirm or _loginright_admincategory) ? '<h2>' . $_lang['admin.content.articles'] . '</h2><p>' : '') . '
      ' . (_loginright_adminart ? '<a href="index.php?p=content-articles-edit"><img src="images/icons/action.png" alt="act" class="icon" />' . $_lang['admin.content.newart'] . '</a><br /><a href="index.php?p=content-articles"><img src="images/icons/action.png" alt="act" class="icon" />' . $_lang['admin.content.manage'] . '</a><br />' : '') . '
      ' . (_loginright_adminconfirm ? '<a href="index.php?p=content-confirm"><img src="images/icons/action.png" alt="act" class="icon" />' . $_lang['admin.content.confirm'] . '</a><br />' : '') . '
      ' . (_loginright_admincategory ? '<a href="index.php?p=content-movearts"><img src="images/icons/action.png" alt="act" class="icon" />' . $_lang['admin.content.movearts'] . '</a><br />' : '') . '
      ' . (_loginright_admincategory ? '<a href="index.php?p=content-artfilter"><img src="images/icons/action.png" alt="act" class="icon" />' . $_lang['admin.content.artfilter'] . '</a><br />' : '') . '
      ' . ((_loginright_adminart or _loginright_adminconfirm or _loginright_admincategory) ? '</p><br />' : '') . '

      ' . ((_loginright_adminpoll or _loginright_adminsbox or _loginright_adminbox or !empty($other_links)) ? '<h2>' . $_lang['admin.content.other'] . '</h2><p>' : '') . '
      ' . (_loginright_adminpoll ? '<a href="index.php?p=content-polls"><img src="images/icons/action.png" alt="act" class="icon" />' . $_lang['admin.content.polls'] . '</a><br />' : '') . '
      ' . (_loginright_adminsbox ? '<a href="index.php?p=content-sboxes"><img src="images/icons/action.png" alt="act" class="icon" />' . $_lang['admin.content.sboxes'] . '</a><br />' : '') . '
      ' . (_loginright_adminbox ? '<a href="index.php?p=content-boxes"><img src="images/icons/action.png" alt="act" class="icon" />' . $_lang['admin.content.boxes'] . '</a><br />' : '') . '
      ' . (!empty($other_links) ? implode('<br />', $other_links) . '<br />' : '') . '
      ' . ((_loginright_adminpoll or _loginright_adminsbox or _loginright_adminbox or !empty($other_links)) ? '</p>' : '') . '


    </td>' : '') . '


</tr>
</table>
';
