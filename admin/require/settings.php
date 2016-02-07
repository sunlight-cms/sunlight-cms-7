<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  ulozeni  --- */
if (isset($_POST['template'])) {

    // pole vstupu (0 => nazev, 1=> checkbox?, 2 => zpracovani: 0-db_esc 1-htmlstr+db_esc 2-intval, 3 => [trim 1/0], 4 => [is_ext_set 1/0])
    $settings_array = array(
        array("template", false, 0),
        array("time_format", false, 1),
        array("language", false, 0),
        array("language_allowcustom", true, 0),
        array("showpages", false, 2),
        array("pagingmode", false, 2),
        array("notpublicsite", true, 0),
        array("extend_enabled", true, 0),
        array("proxy_mode", true, 0),
        array("title", false, 1),
        array("titletype", false, 2),
        array("description", false, 1),
        array("keywords", false, 1),
        array("author", false, 1),
        array("url", false, 1),
        array("favicon", true, 0),
        array("titleseparator", false, 1),
        array("modrewrite", true, 0),
        array("wysiwyg", true, 0),
        array("ajaxfm", true, 0),
        array("ulist", true, 0),
        array("registration", true, 0),
        array("registration_confirm", true, 0),
        array("registration_grouplist", true, 0),
        array("rules", false, 0, true, true),
        array("lostpass", true, 0),
        array("search", true, 0),
        array("comments", true, 0),
        array("messages", true, 0),
        array("captcha", true, 0),
        array("bbcode", true, 0),
        array("smileys", true, 0),
        array("lightbox", true, 0),
        array("codemirror", true, 0),
        array("printart", true, 0),
        array("ratemode", false, 2),
        array("rss", true, 0),
        array("profileemail", true, 0),
        array("atreplace", false, 1),
        array("maxloginattempts", false, 2),
        array("maxloginexpire", false, 2),
        array("artreadexpire", false, 2),
        array("artrateexpire", false, 2),
        array("accactexpire", false, 2),
        array("lostpassexpire", false, 2),
        array("pollvoteexpire", false, 2),
        array("postsendexpire", false, 2),
        array("commentsperpage", false, 2),
        array("messagesperpage", false, 2),
        array("extratopicslimit", false, 2),
        array("rsslimit", false, 2),
        array("sboxmemory", false, 2),
        array("galuploadresize_w", false, 2),
        array("galuploadresize_h", false, 2),
        array("article_pic_w", false, 2),
        array("article_pic_h", false, 2),
        array("topic_hot_ratio", false, 2),
        array("mailerusefrom", true, 0),
        array("sysmail", false, 0, true),
        array("uploadavatar", true, 0),
        array("show_avatars", true, 0),
        array("postadmintime", false, 2),
        array("defaultgroup", false, 2),
        array("adminintro", false, 0),
        array("adminlinkprivate", true, 0),
        array("adminscheme", false, 2),
        array("adminscheme_mode", false, 2),
        array("cacheid", false, 2),
        array("cron_auto", true, 0),
        array("cron_auth", false, 0, true, true),
        array("thumb_cleanup_threshold", false, 2),
        array("thumb_touch_threshold", false, 2),
        array("maintenance_interval", false, 2),
    );

    // ulozeni
    foreach ($settings_array as $item) {

        // nacteni a zpracovani hodnoty
        if ($item[1] == false) {
            if (!isset($_POST[$item[0]])) {
                $_POST[$item[0]] = "0";
            }
            if (isset($item[3])) {
                $_POST[$item[0]] = trim($_POST[$item[0]]);
            }
            switch ($item[2]) {
                case 0:
                    $val = DB::esc($_POST[$item[0]]);
                    break;
                case 1:
                    $val = DB::esc(_htmlStr($_POST[$item[0]]));
                    break;
                case 2:
                    $val = intval($_POST[$item[0]]);
                    break;
            }
        } else {
            $val = _checkboxLoad($item[0]);
        }

        // individualni akce
        switch ($item[0]) {
            case "url":
                $val = _removeSlashesFromEnd($val);
                break;
            case "defaultgroup":
                if (DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-groups` WHERE id=" . $val), 0) == 0) {
                    $val = 3;
                }
                break;
            case "showpages":
                $val = intval(abs($val - 1) / 2);
                if ($val == 0) {
                    $val = 1;
                }
                break;
            case "pagingmode":
                if ($val < 1 or $val > 3) {
                    $val = 1;
                }
                break;
            case "commentsperpage":
            case "messagesperpage":
            case "extratopicslimit":
            case "rsslimit":
            case "sboxmemory":
                if ($val <= 0) {
                    $val = 1;
                }
                break;
            case "postadmintime":
                $val = $val * 60 * 60;
                if ($val <= 0) {
                    $val = 3600;
                }
                break;
            case "maxloginexpire":
            case "artreadexpire":
            case "pollvoteexpire":
            case "artrateexpire":
            case "accactexpire":
            case "lostpassexpire":
                $val = $val * 60;
                if ($val < 0) {
                    $val = 0;
                }
                break;
            case "maxloginattempts":
                if ($val < 1) {
                    $val = 1;
                }
                break;
            case "postsendexpire":
            case "cacheid":
                if ($val < 0) {
                    $val = 0;
                }
                break;
            case "galuploadresize_w":
            case "galuploadresize_h":
            case "article_pic_w":
            case "article_pic_h":
                if ($val < 50) $val = 50;
                break;
            case "topic_hot_ratio":
                if ($val < 10) $val = 10;
                break;
        }

        // ulozeni
        $is_ext_set = (isset($item[4]) && $item[4]);
        if ($val != ($is_ext_set ? SL::$settings[$item[0]] : @constant('_' . $item[0]))) {
            DB::query("UPDATE `" . _mysql_prefix . "-settings` SET val='" . $val . "' WHERE var='" . ($is_ext_set ? '.' : '') . $item[0] . "'");
        }

    }

    // presmerovani
    define('_redirect_to', 'index.php?p=settings&r=1');

    return;

}

/* ---  priprava promennych  --- */

// vyber motivu
$template_select = '<select name="template">';
$handle = @opendir(_indexroot . "plugins/templates/");
while (false !== ($item = @readdir($handle))) {
    if ($item == "." or $item == "..") {
        continue;
    }
    if ($item == _template) {
        $selected = ' selected="selected"';
    } else {
        $selected = "";
    }
    $template_select .= '<option value="' . $item . '"' . $selected . '>' . $item . '</option>';
}
closedir($handle);
$template_select .= '</select>';

// vyber jazyka
$language_select = '<select name="language">';
$handle = @opendir(_indexroot . "plugins/languages/");
while (false !== ($item = @readdir($handle))) {
    if ($item == "." or $item == ".." or @is_dir(_indexroot . $item)) {
        continue;
    }

    // kontrola polozky
    $item = pathinfo($item);
    if (!isset($item['extension']) or $item['extension'] != "php") {
        continue;
    }
    $item = mb_substr($item['basename'], 0, mb_strrpos($item['basename'], "."));

    if ($item == _language) {
        $selected = ' selected="selected"';
    } else {
        $selected = "";
    }
    $language_select .= '<option value="' . $item . '"' . $selected . '>' . $item . '</option>';
}
closedir($handle);
$language_select .= '</select>';

// vyber vychozi skupiny
$defaultgroup_select = _admin_authorSelect("defaultgroup", _defaultgroup, "id!=2", null, null, true);

// vyber zobrazeni strankovani
$pagingmode_select = '<select name="pagingmode">';
for ($x = 1; $x < 4; $x++) {
    if ($x == _pagingmode) {
        $selected = " selected='selected'";
    } else {
        $selected = "";
    }
    $pagingmode_select .= "<option value='" . $x . "'" . $selected . ">" . $_lang['admin.settings.mods.pagingmode.' . $x] . "</option>";
}
$pagingmode_select .= '</select>';

// vyber schematu administrace
$adminscheme_select = '<select name="adminscheme">';
for ($x = 0; $x < 11; $x++) {
    if ($x == _adminscheme) {
        $selected = " selected='selected'";
    } else {
        $selected = "";
    }
    $adminscheme_select .= "<option value='" . $x . "'" . $selected . ">" . $_lang['admin.settings.admin.adminscheme.' . $x] . "</option>";
}
$adminscheme_select .= '</select>';

// vyber modu schematu administrace
$adminscheme_mode_select = '<select name="adminscheme_mode">';
for ($x = 0; $x < 3; $x++) {
    if ($x == _adminscheme_mode) {
        $selected = " selected='selected'";
    } else {
        $selected = "";
    }
    $adminscheme_mode_select .= "<option value='" . $x . "'" . $selected . ">" . $_lang['admin.settings.admin.adminscheme_mode.' . $x] . "</option>";
}
$adminscheme_mode_select .= '</select>';

// vyber zpusobu zobrazeni titulku
$titletype_select = '<select name="titletype" class="selectmedium">';
for ($x = 1; $x < 3; $x++) {
    if ($x == _titletype) {
        $selected = " selected='selected'";
    } else {
        $selected = "";
    }
    $titletype_select .= "<option value='" . $x . "'" . $selected . ">" . $_lang['admin.settings.info.titletype.' . $x] . "</option>";
}
$titletype_select .= '</select>';

// vyber zpusobu hodnoceni clanku
$ratemode_select = '<select name="ratemode">';
for ($x = 0; $x < 3; $x++) {
    if ($x == _ratemode) {
        $selected = " selected='selected'";
    } else {
        $selected = "";
    }
    $ratemode_select .= "<option value='" . $x . "'" . $selected . ">" . $_lang['admin.settings.mods.ratemode.' . $x] . "</option>";
}
$ratemode_select .= '</select>';

/* ---  vystup  --- */
$output .= '
<p class="bborder">' . $_lang['admin.settings.p'] . '</p>

' . (isset($_GET['r']) ? _formMessage(1, $_lang['admin.settings.saved']) : '') . '

<form action="index.php?p=settings" method="post">

<div id="settingsnav">
<div>
<input type="submit" value="' . $_lang['global.savechanges'] . '" />
<ul>
    <li><a href="#settings_main">' . $_lang['admin.settings.main'] . '</a></li>
    <li><a href="#settings_info">' . $_lang['admin.settings.info'] . '</a></li>
    <li><a href="#settings_admin">' . $_lang['admin.settings.admin'] . '</a></li>
    <li><a href="#settings_rewrite">' . $_lang['admin.settings.rewrite'] . '</a></li>
    <li><a href="#settings_users">' . $_lang['admin.settings.users'] . '</a></li>
    <li><a href="#settings_emails">' . $_lang['admin.settings.emails'] . '</a></li>
    <li><a href="#settings_articles">' . $_lang['admin.settings.articles'] . '</a></li>
    <li><a href="#settings_forum">' . $_lang['admin.settings.forum'] . '</a></li>
    <li><a href="#settings_galleries">' . $_lang['admin.settings.galleries'] . '</a></li>
    <li><a href="#settings_various">' . $_lang['admin.settings.various'] . '</a></li>
    <li><a href="#settings_paging">' . $_lang['admin.settings.paging'] . '</a></li>
    <li><a href="#settings_iplog">' . $_lang['admin.settings.iplog'] . '</a></li>
    <li><a href="#settings_cron">' . $_lang['admin.settings.cron'] . '</a></li>
</ul>
</div>
</div>

<div id="settingsform">

  <!-- *************** MAIN *************** -->
  <fieldset id="settings_main">
  <legend>' . $_lang['admin.settings.main'] . '</legend>

  <table>

  <tr>
  <td colspan="3"><p class="big">&nbsp;&nbsp;<a href="index.php?p=settings-plugins"><img src="images/icons/plugin.png" alt="plugin" /> ' . $_lang['admin.settings.plugins.link'] . '</a></p></td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.main.template'] . '</strong></td>
  <td>' . $template_select . '</td>
  <td class="lpad">' . $_lang['admin.settings.main.template.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.main.time_format'] . '</strong></td>
  <td><input type="text" name="time_format" size="10" value="' . _time_format . '" /></td>
  <td class="lpad">' . $_lang['admin.settings.main.time_format.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.main.cacheid'] . '</strong></td>
  <td><input type="text" name="cacheid" size="10" value="' . _cacheid . '" maxlength="8" /></td>
  <td class="lpad">' . $_lang['admin.settings.main.cacheid.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.main.language'] . '</strong></td>
  <td>' . $language_select . '</td>
  <td class="lpad">' . $_lang['admin.settings.main.language.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.main.language_allowcustom'] . '</strong></td>
  <td><input type="checkbox" name="language_allowcustom" value="1"' . _checkboxActivate(_language_allowcustom) . ' /></td>
  <td class="lpad">' . $_lang['admin.settings.main.language_allowcustom.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.main.notpublicsite'] . '</strong></td>
  <td><input type="checkbox" name="notpublicsite" value="1"' . _checkboxActivate(_notpublicsite) . ' /></td>
  <td class="lpad">' . $_lang['admin.settings.main.notpublicsite.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.main.proxy_mode'] . '</strong></td>
  <td><input type="checkbox" name="proxy_mode" value="1"' . _checkboxActivate(_proxy_mode) . ' /></td>
  <td class="lpad">' . str_replace('*ip*', _userip, $_lang['admin.settings.main.proxy_mode.help']) . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.main.extend'] . '</strong></td>
  <td><input type="checkbox" name="extend_enabled" value="1"' . _checkboxActivate(_extend_enabled) . ' /></td>
  <td class="lpad">' . $_lang['admin.settings.main.extend.help'] . '</td>
  </tr>

  </table>

  </fieldset>



  <!-- *************** INFO *************** -->
  <fieldset id="settings_info">
  <legend>' . $_lang['admin.settings.info'] . '</legend>

  <table>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.info.title'] . '</strong></td>
  <td><input type="text" name="title" class="inputmedium" value="' . _title . '" /></td>
  <td class="lpad">' . $_lang['admin.settings.info.title.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.info.titletype'] . '</strong></td>
  <td>' . $titletype_select . '</td>
  <td class="lpad">' . $_lang['admin.settings.info.titletype.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.info.description'] . '</strong></td>
  <td><input type="text" name="description" class="inputmedium" value="' . _description . '" /></td>
  <td class="lpad">' . $_lang['admin.settings.info.description.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.info.keywords'] . '</strong></td>
  <td><input type="text" name="keywords" class="inputmedium" value="' . _keywords . '" /></td>
  <td class="lpad">' . $_lang['admin.settings.info.keywords.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.info.author'] . '</strong></td>
  <td><input type="text" name="author" class="inputmedium" value="' . _author . '" /></td>
  <td class="lpad">' . $_lang['admin.settings.info.author.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.info.titleseparator'] . '</strong></td>
  <td><input type="text" name="titleseparator" class="inputmedium" value="' . _titleseparator . '" /></td>
  <td class="lpad">' . $_lang['admin.settings.info.titleseparator.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.info.url'] . '</strong></td>
  <td><input type="text" name="url" class="inputmedium" value="' . _url . '" /></td>
  <td class="lpad">' . $_lang['admin.settings.info.url.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.info.favicon'] . '</strong></td>
  <td><input type="checkbox" name="favicon" value="1"' . _checkboxActivate(_favicon) . ' /></td>
  <td class="lpad">' . $_lang['admin.settings.info.favicon.help'] . '</td>
  </tr>

  </table>
  </fieldset>


  <!-- *************** ADMIN *************** -->
  <fieldset id="settings_admin">
  <legend>' . $_lang['admin.settings.admin'] . '</legend>

  <table>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.admin.wysiwyg'] . '</strong></td>
  <td><input type="checkbox" name="wysiwyg" value="1"' . _checkboxActivate(_wysiwyg) . _inputDisable(@file_exists(_indexroot . "admin/modules/wysiwyg.php")) . ' /></td>
  <td class="lpad">' . $_lang['admin.settings.admin.wysiwyg.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.admin.ajaxfm'] . '</strong></td>
  <td><input type="checkbox" name="ajaxfm" value="1"' . _checkboxActivate(_ajaxfm) . _inputDisable(@file_exists(_indexroot . "admin/modules/ajaxfm/ajaxfilemanager.php")) . ' /></td>
  <td class="lpad">' . $_lang['admin.settings.admin.ajaxfm.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.admin.adminlinkprivate'] . '</strong></td>
  <td><input type="checkbox" name="adminlinkprivate" value="1"' . _checkboxActivate(_adminlinkprivate) . ' /></td>
  <td class="lpad">' . $_lang['admin.settings.admin.adminlinkprivate.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.admin.adminscheme'] . '</strong></td>
  <td>' . $adminscheme_select . '</td>
  <td class="lpad">' . $_lang['admin.settings.admin.adminscheme.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.admin.adminscheme_mode'] . '</strong></td>
  <td>' . $adminscheme_mode_select . '</td>
  <td class="lpad">' . $_lang['admin.settings.admin.adminscheme_mode.help'] . '</td>
  </tr>

  </table>
  </fieldset>


  <!-- *************** MOD_REWRITE *************** -->
  <fieldset id="settings_rewrite">
  <legend>' . $_lang['admin.settings.rewrite'] . '</legend>

  <table>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.mods.modrewrite'] . '</strong></td>
  <td><input type="checkbox" name="modrewrite" value="1"' . _checkboxActivate(_modrewrite) . _inputDisable(@file_exists(_indexroot . ".htaccess")) . ' /></td>
  <td class="lpad">' . $_lang['admin.settings.mods.modrewrite.help'] . '</td>
  </tr>

  <tr>
  <td></td><td></td>
  <td class="lpad"><p>' . $_lang['admin.settings.mods.modrewrite.help2'] . '</p></td>
  </tr>

  </table>
  </fieldset>


  <!-- *************** USERS *************** -->
  <fieldset id="settings_users">
  <legend>' . $_lang['admin.settings.users'] . '</legend>

  <table>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.mods.registration'] . '</strong></td>
  <td><input type="checkbox" name="registration" value="1"' . _checkboxActivate(_registration) . ' /></td>
  <td class="lpad">' . $_lang['admin.settings.mods.registration.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.mods.registration_confirm'] . '</strong></td>
  <td><input type="checkbox" name="registration_confirm" value="1"' . _checkboxActivate(_registration_confirm) . ' /></td>
  <td class="lpad">' . $_lang['admin.settings.mods.registration_confirm.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.mods.registration_grouplist'] . '</strong></td>
  <td><input type="checkbox" name="registration_grouplist" value="1"' . _checkboxActivate(_registration_grouplist) . ' /></td>
  <td class="lpad">' . $_lang['admin.settings.mods.registration_grouplist.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.mods.defaultgroup'] . '</strong></td>
  <td>' . $defaultgroup_select . '</td>
  <td class="lpad">' . $_lang['admin.settings.mods.defaultgroup.help'] . '</td>
  </tr>

  <tr class="valign-top">
  <td class="rpad"><strong>' . $_lang['admin.settings.mods.rules'] . '</strong></td>
  <td colspan="2">
  <p>' . $_lang['admin.settings.mods.rules.help'] . '</p>
  <textarea name="rules" rows="9" cols="33" class="areasmallwide codemirror">' . _htmlStr(SL::$settings['rules']) . '</textarea>
  </td>
  </tr>

      <tr><td colspan="3">&nbsp;</td></tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.mods.messages'] . '</strong></td>
  <td><input type="checkbox" name="messages" value="1"' . _checkboxActivate(_messages) . ' /></td>
  <td class="lpad">' . $_lang['admin.settings.mods.messages.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.mods.lostpass'] . '</strong></td>
  <td><input type="checkbox" name="lostpass" value="1"' . _checkboxActivate(_lostpass) . ' /></td>
  <td class="lpad">' . $_lang['admin.settings.mods.lostpass.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.mods.ulist'] . '</strong></td>
  <td><input type="checkbox" name="ulist" value="1"' . _checkboxActivate(_ulist) . ' /></td>
  <td class="lpad">' . $_lang['admin.settings.mods.ulist.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.mods.uploadavatar'] . '</strong></td>
  <td><input type="checkbox" name="uploadavatar" value="1"' . _checkboxActivate(_uploadavatar) . ' /></td>
  <td class="lpad">' . $_lang['admin.settings.mods.uploadavatar.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.mods.show_avatars'] . '</strong></td>
  <td><input type="checkbox" name="show_avatars" value="1"' . _checkboxActivate(_show_avatars) . ' /></td>
  <td class="lpad">' . $_lang['admin.settings.mods.show_avatars.help'] . '</td>
  </tr>

  </table>
  </fieldset>


  <!-- *************** EMAILS *************** -->
  <fieldset id="settings_emails">
  <legend>' . $_lang['admin.settings.emails'] . '</legend>

  <table>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.mods.profileemail'] . '</strong></td>
  <td><input type="checkbox" name="profileemail" value="1"' . _checkboxActivate(_profileemail) . ' /></td>
  <td class="lpad">' . $_lang['admin.settings.mods.profileemail.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.mods.mailerusefrom'] . '</strong></td>
  <td><input type="checkbox" name="mailerusefrom" value="1"' . _checkboxActivate(_mailerusefrom) . ' /></td>
  <td class="lpad">' . $_lang['admin.settings.mods.mailerusefrom.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.mods.sysmail'] . '</strong></td>
  <td><input type="text" name="sysmail" class="inputsmall" value="' . _htmlStr(_sysmail) . '" /></td>
  <td class="lpad">' . $_lang['admin.settings.mods.sysmail.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.mods.atreplace'] . '</strong></td>
  <td><input type="text" name="atreplace" class="inputsmall" value="' . _atreplace . '" /></td>
  <td class="lpad">' . $_lang['admin.settings.mods.atreplace.help'] . '</td>
  </tr>

  </table>
  </fieldset>


  <!-- *************** ARTICLES *************** -->
  <fieldset id="settings_articles">
  <legend>' . $_lang['admin.settings.articles'] . '</legend>

  <table>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.mods.printart'] . '</strong></td>
  <td><input type="checkbox" name="printart" value="1"' . _checkboxActivate(_printart) . ' /></td>
  <td class="lpad">' . $_lang['admin.settings.mods.printart.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.mods.ratemode'] . '</strong></td>
  <td>' . $ratemode_select . '</td>
  <td class="lpad">' . $_lang['admin.settings.mods.ratemode.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.mods.article_pic_w'] . '</strong></td>
  <td><input type="text" name="article_pic_w" class="inputmini" value="' . _article_pic_w . '" /></td>
  <td class="lpad">' . $_lang['admin.settings.mods.article_pic_w.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.mods.article_pic_h'] . '</strong></td>
  <td><input type="text" name="article_pic_h" class="inputmini" value="' . _article_pic_h . '" /></td>
  <td class="lpad">' . $_lang['admin.settings.mods.article_pic_h.help'] . '</td>
  </tr>

  </table>
  </fieldset>


  <!-- *************** FORUM *************** -->
  <fieldset id="settings_forum">
  <legend>' . $_lang['admin.settings.forum'] . '</legend>

  <table>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.forum.hotratio'] . '</strong></td>
  <td><input type="text" name="topic_hot_ratio" class="inputmini" value="' . _topic_hot_ratio . '" /></td>
  <td class="lpad">' . $_lang['admin.settings.forum.hotratio.help'] . '</td>
  </tr>

  </table>
  </fieldset>


  <!-- *************** GALLERIES *************** -->
  <fieldset id="settings_galleries">
  <legend>' . $_lang['admin.settings.galleries'] . '</legend>

  <table>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.mods.galuploadresize_w'] . '</strong></td>
  <td><input type="text" name="galuploadresize_w" class="inputmini" value="' . _galuploadresize_w . '" /></td>
  <td class="lpad">' . $_lang['admin.settings.mods.galuploadresize_w.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.mods.galuploadresize_h'] . '</strong></td>
  <td><input type="text" name="galuploadresize_h" class="inputmini" value="' . _galuploadresize_h . '" /></td>
  <td class="lpad">' . $_lang['admin.settings.mods.galuploadresize_h.help'] . '</td>
  </tr>

  </table>
  </fieldset>


  <!-- *************** VARIOUS *************** -->
  <fieldset id="settings_various">
  <legend>' . $_lang['admin.settings.various'] . '</legend>

  <table>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.mods.comments'] . '</strong></td>
  <td><input type="checkbox" name="comments" value="1"' . _checkboxActivate(_comments) . ' /></td>
  <td class="lpad">' . $_lang['admin.settings.mods.comments.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.mods.search'] . '</strong></td>
  <td><input type="checkbox" name="search" value="1"' . _checkboxActivate(_search) . ' /></td>
  <td class="lpad">' . $_lang['admin.settings.mods.search.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.mods.rss'] . '</strong></td>
  <td><input type="checkbox" name="rss" value="1"' . _checkboxActivate(_rss) . ' /></td>
  <td class="lpad">' . $_lang['admin.settings.mods.rss.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.mods.captcha'] . '</strong></td>
  <td><input type="checkbox" name="captcha" value="1"' . _checkboxActivate(_captcha) . ' /></td>
  <td class="lpad">' . $_lang['admin.settings.mods.captcha.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.mods.bbcode'] . '</strong></td>
  <td><input type="checkbox" name="bbcode" value="1"' . _checkboxActivate(_bbcode) . ' /></td>
  <td class="lpad">' . $_lang['admin.settings.mods.bbcode.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.mods.smileys'] . '</strong></td>
  <td><input type="checkbox" name="smileys" value="1"' . _checkboxActivate(_smileys) . ' /></td>
  <td class="lpad">' . $_lang['admin.settings.mods.smileys.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.mods.lightbox'] . '</strong></td>
  <td><input type="checkbox" name="lightbox" value="1"' . _checkboxActivate(_lightbox) . ' /></td>
  <td class="lpad">' . $_lang['admin.settings.mods.lightbox.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.mods.codemirror'] . '</strong></td>
  <td><input type="checkbox" name="codemirror" value="1"' . _checkboxActivate(_codemirror) . ' /></td>
  <td class="lpad">' . $_lang['admin.settings.mods.codemirror.help'] . '</td>
  </tr>

  </table>
  </fieldset>


  <!-- *************** PAGING *************** -->
  <fieldset id="settings_paging">
  <legend>' . $_lang['admin.settings.paging'] . '</legend>

  <table>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.mods.pagingmode'] . '</strong></td>
  <td>' . $pagingmode_select . '</td>
  <td class="lpad">' . $_lang['admin.settings.mods.pagingmode.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.mods.showpages'] . '</strong></td>
  <td><input type="text" name="showpages" class="inputmini" value="' . (_showpages * 2 + 1) . '" /></td>
  <td class="lpad">' . $_lang['admin.settings.mods.showpages.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.mods.commentsperpage'] . '</strong></td>
  <td><input type="text" name="commentsperpage" class="inputmini" value="' . _commentsperpage . '" /></td>
  <td class="lpad">' . $_lang['admin.settings.mods.commentsperpage.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.mods.messagesperpage'] . '</strong></td>
  <td><input type="text" name="messagesperpage" class="inputmini" value="' . _messagesperpage . '" /></td>
  <td class="lpad">' . $_lang['admin.settings.mods.messagesperpage.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.mods.extratopicslimit'] . '</strong></td>
  <td><input type="text" name="extratopicslimit" class="inputmini" value="' . _extratopicslimit . '" /></td>
  <td class="lpad">' . $_lang['admin.settings.mods.extratopicslimit.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.mods.rsslimit'] . '</strong></td>
  <td><input type="text" name="rsslimit" class="inputmini" value="' . _rsslimit . '" /></td>
  <td class="lpad">' . $_lang['admin.settings.mods.rsslimit.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.mods.sboxmemory'] . '</strong></td>
  <td><input type="text" name="sboxmemory" class="inputmini" value="' . _sboxmemory . '" /></td>
  <td class="lpad">' . $_lang['admin.settings.mods.sboxmemory.help'] . '</td>
  </tr>

  </table>
  </fieldset>



  <!-- *************** IPLOG EXPIRE TIMES ETC. *************** -->
  <fieldset id="settings_iplog">
  <legend>' . $_lang['admin.settings.iplog'] . '</legend>

  <table>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.mods.postadmintime'] . '</strong></td>
  <td><input type="text" name="postadmintime" class="inputsmaller" value="' . (_postadmintime / 60 / 60) . '" /></td>
  <td class="lpad">' . $_lang['admin.settings.mods.postadmintime.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.iplog.maxloginattempts'] . '</strong></td>
  <td><input type="text" name="maxloginattempts" class="inputsmaller" value="' . _maxloginattempts . '" /></td>
  <td class="lpad">' . $_lang['admin.settings.iplog.maxloginattempts.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.iplog.maxloginexpire'] . '</strong></td>
  <td><input type="text" name="maxloginexpire" class="inputsmaller" value="' . (_maxloginexpire / 60) . '" /></td>
  <td class="lpad">' . $_lang['admin.settings.iplog.maxloginexpire.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.iplog.artreadexpire'] . '</strong></td>
  <td><input type="text" name="artreadexpire" class="inputsmaller" value="' . (_artreadexpire / 60) . '" /></td>
  <td class="lpad">' . $_lang['admin.settings.iplog.artreadexpire.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.iplog.artrateexpire'] . '</strong></td>
  <td><input type="text" name="artrateexpire" class="inputsmaller" value="' . (_artrateexpire / 60) . '" /></td>
  <td class="lpad">' . $_lang['admin.settings.iplog.artrateexpire.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.iplog.pollvoteexpire'] . '</strong></td>
  <td><input type="text" name="pollvoteexpire" class="inputsmaller" value="' . (_pollvoteexpire / 60) . '" /></td>
  <td class="lpad">' . $_lang['admin.settings.iplog.pollvoteexpire.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.iplog.postsendexpire'] . '</strong></td>
  <td><input type="text" name="postsendexpire" class="inputsmaller" value="' . _postsendexpire . '" /></td>
  <td class="lpad">' . $_lang['admin.settings.iplog.postsendexpire.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.iplog.accactexpire'] . '</strong></td>
  <td><input type="text" name="accactexpire" class="inputsmaller" value="' . (_accactexpire / 60) . '" /></td>
  <td class="lpad">' . $_lang['admin.settings.iplog.accactexpire.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.iplog.lostpassexpire'] . '</strong></td>
  <td><input type="text" name="lostpassexpire" class="inputsmaller" value="' . (_lostpassexpire / 60) . '" /></td>
  <td class="lpad">' . $_lang['admin.settings.iplog.lostpassexpire.help'] . '</td>
  </tr>

  </table>
  </fieldset>



  <!-- *************** CRON *************** -->
  <fieldset id="settings_cron">
  <legend>' . $_lang['admin.settings.cron'] . '</legend>

  <table>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.cron.auto'] . '</strong></td>
  <td><input type="checkbox" name="cron_auto" class="inputsmaller" value="1"' . _checkboxActivate(_cron_auto) . ' /></td>
  <td class="lpad">' . $_lang['admin.settings.cron.auto.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.cron.auth'] . '</strong></td>
  <td><input type="text" name="cron_auth" class="inputsmall" value="' . _htmlStr(SL::$settings['cron_auth']) . '" /></td>
  <td class="lpad">' . $_lang['admin.settings.cron.auth.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.cron.maintenance_interval'] . '</strong></td>
  <td><input type="text" name="maintenance_interval" class="inputsmaller" value="' . _maintenance_interval . '" /></td>
  <td class="lpad">' . $_lang['admin.settings.cron.maintenance_interval.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.cron.thumb_cleanup_threshold'] . '</strong></td>
  <td><input type="text" name="thumb_cleanup_threshold" class="inputsmaller" value="' . _thumb_cleanup_threshold . '" /></td>
  <td class="lpad">' . $_lang['admin.settings.cron.thumb_cleanup_threshold.help'] . '</td>
  </tr>

  <tr>
  <td class="rpad"><strong>' . $_lang['admin.settings.cron.thumb_touch_threshold'] . '</strong></td>
  <td><input type="text" name="thumb_touch_threshold" class="inputsmaller" value="' . _thumb_touch_threshold . '" /></td>
  <td class="lpad">' . $_lang['admin.settings.cron.thumb_touch_threshold.help'] . '</td>
  </tr>

  </table>
  </fieldset>

</div>

' . _xsrfProtect() . '</form>

<script type="text/javascript">
void function () {
    $(document).ready(function () {
        var scrollWatchMenu = $("#settingsnav > div > ul").scrollWatchMenu("fieldset[id]", {
            resolutionMode: 1,
            focusRatio: 0,
            focusOffset: 50,
            menuWindowScrollOffset: -20
        });
        $("#settingsnav").scrollFix("div", {
            fixBoundaryOffset: -10,
            unfixBoundaryOffset: -10
        });
    });
}();
</script>

';
