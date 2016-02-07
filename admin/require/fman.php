<?php
/* ----  core test  ---- */

if (!defined('_core')) {
    exit;
}

/* ----  priprava funkci  ---- */

function _admin_cparam($value, $encoded = true)
{
    if ($encoded) {
        $value = @base64_decode($value);
    }

    return basename($value);
}

function _admin_mparam($value, $urlencode = true)
{
    $output = base64_encode($value);
    if ($urlencode) {
        $output = urlencode($output);
    }

    return $output;
}

function _admin_issafefile($filename)
{
    if (!_loginright_adminfmanplus) return _isSafeFile($filename);
    return true;
}

function _admin_deletefolder($dirname, $only_empty = false)
{
    if (!@is_dir($dirname)) return false;
    $dscan = array(realpath($dirname));
    $darr = array();
    while (!empty($dscan)) {
        $dcur = array_pop($dscan);
        $darr[] = $dcur;
        if ($d = @opendir($dcur)) {
            while (false !== ($f = @readdir($d))) {
                if ($f == '.' || $f == '..') continue;
                $f = $dcur . '/' . $f;
                if (@is_dir($f)) $dscan[] = $f;
                else @unlink($f);
            }
            closedir($d);
        }
    }
    $i_until = ($only_empty) ? 1 : 0;
    for ($i = count($darr) - 1; $i >= $i_until; $i--) {
        @rmdir($darr[$i]);

    }

    return (($only_empty) ? (count(scandir) <= 2) : (!@is_dir($dirname)));
}

/* ----  priprava promennych  ---- */

$continue = true;
$message = "";
$action_code = "";
if (!_loginright_adminfmanlimit) {
    $defdir = _upload_dir;
} else {
    $defdir_subpath = _loginname . "/";
    _extend('call', 'admin.fman.userdir', array('subpath' => &$defdir_subpath));
    $defdir = _upload_dir . $defdir_subpath;
}

// adresar
if (isset($_GET['dir'])) {
    $dir = str_replace("\\", "/", $_GET['dir']);
    if (mb_substr($dir, -1, 1) != "/") {
        $dir .= "/";
    }
    $dir = _parsePath($dir);
    if ((!_loginright_adminfmanplus and mb_substr_count($dir, "..") > 1) or mb_substr_count($dir, "..") > mb_substr_count($defdir, "..")) {
        $dir = $defdir;
    }
    if (!_loginright_adminfmanplus or _loginright_adminfmanlimit) {
        if (mb_substr($dir, 0, mb_strlen($defdir)) != $defdir) {
            $dir = $defdir;
        }
    }
    if (!@file_exists($dir) or !@is_dir($dir)) {
        $dir = $defdir;
    }
} else {
    $dir = $defdir;
}

// vytvoreni vychoziho adresare
if (!(@file_exists($defdir) and @is_dir($defdir))) {
    $test = @mkdir($defdir, 0777, true);
    if (!$test) {
        $continue = false;
        $output .= _formMessage(3, $_lang['admin.fman.msg.defdircreationfailure']);
    } else {
        @chmod($defdir, 0777);
    }
}

$url_base = "index.php?p=fman&amp;";
$url = $url_base . "dir=" . urlencode($dir);
$uploaded = array();

/* ----  akce, vystup  ---- */
if ($continue) {

    /* ---  post akce  --- */
    if (isset($_POST['action'])) {

        switch ($_POST['action']) {

                // upload
            case "upload":
                $total = 0;
                $done = 0;
                foreach ($_FILES as $item) {
                    if (!is_array($item['name'])) continue;
                    for ($i = 0; isset($item['name'][$i]); ++$i) {
                        $name = _anchorStr(_admin_cparam($item['name'][$i], false), false);
                        $tmp_name = $item['tmp_name'][$i];
                        $exists = @file_exists($dir . $name);
                        if (@is_uploaded_file($tmp_name) && _admin_issafefile($name) and (!$exists or isset($_POST['upload_rewrite']) and @unlink($dir . $name))) {
                            if (@move_uploaded_file($tmp_name, $dir . $name)) {
                                ++$done;
                                $uploaded[$name] = true;
                            }
                        }
                        ++$total;
                    }
                }
                $tfrom = array("*done*", "*total*");
                $tto = array($done, $total);
                if ($done == $total) $micon = 1;
                else $micon = 2;
                $message = _formMessage($micon, str_replace($tfrom, $tto, $_lang['admin.fman.msg.upload.done']));
                break;

                // novy adresar
            case "newfolder":
                $name = _admin_cparam($_POST['name'], false);
                if (!@file_exists($dir . $name)) {
                    $test = @mkdir($dir . $name);
                    if ($test) {
                        $message = _formMessage(1, $_lang['admin.fman.msg.newfolder.done']);
                        @chmod($dir . $name, 0777);
                    } else {
                        $message = _formMessage(2, $_lang['admin.fman.msg.newfolder.failure']);
                    }
                } else {
                    $message = _formMessage(2, $_lang['admin.fman.msg.newfolder.failure2']);
                }
                break;

                // odstraneni
            case "delete":
                $name = _admin_cparam($_POST['name']);
                if (@file_exists($dir . $name)) {
                    if (!@is_dir($dir . $name)) {
                        if (_admin_issafefile($name)) {
                            if (@unlink($dir . $name)) {
                                $message = _formMessage(1, $_lang['admin.fman.msg.delete.done']);
                            } else {
                                $message = _formMessage(2, $_lang['admin.fman.msg.delete.failure']);
                            }
                        } else {
                            $message = _formMessage(2, $_lang['admin.fman.msg.disallowedextension']);
                        }
                    } else {
                        _admin_deletefolder($dir . $name);
                        if (!@file_exists($dir . $name)) {
                            $message = _formMessage(1, $_lang['admin.fman.msg.delete.done']);
                        } else {
                            $message = _formMessage(2, $_lang['admin.fman.msg.delete.failure']);
                        }
                    }
                }
                break;

                // prejmenovani
            case "rename":
                $name = _admin_cparam($_POST['name']);
                $newname = _admin_cparam($_POST['newname'], false);
                if (@file_exists($dir . $name)) {
                    if (!@file_exists($dir . $newname)) {
                        if (_admin_issafefile($newname) and _admin_issafefile($name)) {
                            if (@rename($dir . $name, $dir . $newname)) {
                                $message = _formMessage(1, $_lang['admin.fman.msg.rename.done']);
                            } else {
                                $message = _formMessage(2, $_lang['admin.fman.msg.rename.failure']);
                            }
                        } else {
                            $message = _formMessage(2, $_lang['admin.fman.msg.disallowedextension']);
                        }
                    } else {
                        $message = _formMessage(2, $_lang['admin.fman.msg.exists']);
                    }
                }
                break;

                // uprava
            case "edit":
                $name = _admin_cparam($_POST['name'], false);
                $content = $_POST['content'];
                if (_admin_issafefile($name)) {
                    $file = @fopen($dir . $name, "w");
                    if ($file) {
                        @fwrite($file, $content);
                        fclose($file);
                        $message = _formMessage(1, $_lang['admin.fman.msg.edit.done'] . "&nbsp;&nbsp;<small>(" . _formatTime(time()) . ")</small>");
                    } else {
                        $message = _formMessage(2, $_lang['admin.fman.msg.edit.failure']);
                    }
                } else {
                    $message = _formMessage(2, $_lang['admin.fman.msg.disallowedextension']);
                }
                break;

                // presun
            case "move":
                $newdir = _arrayRemoveValue(explode("/", $_POST['param']), "");
                $newdir = implode("/", $newdir);
                if (mb_substr($newdir, -1, 1) != "/") {
                    $newdir .= "/";
                }
                $newdir = _parsePath($dir . $newdir);
                if (_loginright_adminfmanplus or mb_substr($newdir, 0, mb_strlen($defdir)) == $defdir) {
                    $done = 0;
                    $total = 0;

                    foreach ($_POST as $var => $val) {
                        if ($var == "action" or $var == "param") {
                            continue;
                        }
                        $val = _admin_cparam($val);
                        if (@file_exists($dir . $val) and !@file_exists($newdir . $val) and !@is_dir($dir . $val) and _admin_issafefile($val)) {
                            if (@rename($dir . $val, $newdir . $val)) {
                                $done++;
                            }
                        }

                        $total++;
                    }

                    $tfrom = array("*done*", "*total*");
                    $tto = array($done, $total);
                    if ($done == $total) {
                        $micon = 1;
                    } else {
                        $micon = 2;
                    }
                    $message = _formMessage($micon, str_replace($tfrom, $tto, $_lang['admin.fman.msg.move.done']));
                } else {
                    $message = _formMessage(2, $_lang['admin.fman.msg.rootlimit']);
                }
                break;

                // odstraneni vyberu
            case "deleteselected":
                $done = 0;
                $total = 0;

                foreach ($_POST as $var => $val) {
                    if ($var == "action" or $var == "param") {
                        continue;
                    }
                    $val = _admin_cparam($val);
                    if (@file_exists($dir . $val) and !@is_dir($dir . $val) and _admin_issafefile($val)) {
                        if (@unlink($dir . $val)) {
                            $done++;
                        }
                    }

                    $total++;
                }

                $tfrom = array("*done*", "*total*");
                $tto = array($done, $total);
                if ($done == $total) {
                    $micon = 1;
                } else {
                    $micon = 2;
                }
                $message = _formMessage($micon, str_replace($tfrom, $tto, $_lang['admin.fman.msg.deleteselected.done']));
                break;

                // pridani vyberu do galerie - formular pro vyber galerie
            case "addtogallery_showform":
                if (_loginright_admingallery and _loginright_admincontent) {
                    $_GET['a'] = "addtogallery";
                }
                break;

                // pridani vyberu do galerie - ulozeni
            case "addtogallery":
                if (_loginright_admingallery and _loginright_admincontent) {

                    // priprava promennych
                    $counter = 0;
                    $galid = intval($_POST['gallery']);

                    // vlozeni obrazku
                    if (DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-root` WHERE id=" . $galid . " AND type=5"), 0) != 0) {

                        // nacteni nejmensiho poradoveho cisla
                        $smallestord = DB::query("SELECT ord FROM `" . _mysql_prefix . "-images` WHERE home=" . $galid . " ORDER BY ord LIMIT 1");
                        if (DB::size($smallestord) != 0) {
                            $smallestord = DB::row($smallestord);
                            $smallestord = $smallestord['ord'];
                        } else {
                            $smallestord = 1;
                        }

                        // posunuti poradovych cisel
                        DB::query("UPDATE `" . _mysql_prefix . "-images` SET ord=ord+" . (count($_POST) - 2) . " WHERE home=" . $galid);

                        // cyklus
                        $sql = "";
                        foreach ($_POST as $var => $val) {
                            if ($var == "action" or $var == "param") {
                                continue;
                            }
                            $val = _admin_cparam($val);
                            $ext = pathinfo($val);
                            if (isset($ext['extension'])) {
                                $ext = mb_strtolower($ext['extension']);
                            } else {
                                $ext = "";
                            }
                            if (@file_exists($dir . $val) and !@is_dir($dir . $val) and in_array($ext, SL::$imageExt)) {
                                $sql .= "(" . $galid . "," . ($smallestord + $counter) . ",'','','" . mb_substr($dir . $val, 3) . "'),";
                                ++$counter;
                            }
                        }

                        // vlozeni
                        if ($counter != 0) {
                            $sql = trim($sql, ",");
                            DB::query("INSERT INTO `" . _mysql_prefix . "-images` (home,ord,title,prev,full) VALUES " . $sql);
                        }

                        // zprava
                        $message = _formMessage(1, str_replace("*done*", $counter, $_lang['admin.fman.addtogallery.done']));

                    } else {
                        $message = _formMessage(2, $_lang['global.badinput']);
                    }

                }
                break;

        }

    }

    /* ---  get akce  --- */
    if (isset($_GET['a'])) {

        $action_acbonus = "";
        $action_form_class = null;

        // vyber akce
        switch ($_GET['a']) {

                // novy adresar
            case "newfolder":
                $action_submit = "global.create";
                $action_title = "admin.fman.menu.createfolder";
                $action_code = "
      <tr>
      <td class='rpad'><strong>" . $_lang['global.name'] . ":</strong></td>
      <td><input type='text' name='name' class='inputmedium' maxlength='64' /></td>
      </tr>
      ";
                break;

                // odstraneni
            case "delete":
                if (isset($_GET['name'])) {
                    $name = $_GET['name'];
                    $action_submit = "global.do";
                    $action_title = "admin.fman.delete.title";
                    $action_code = "
        <tr>
        <td colspan='2'>" . str_replace("*name*", _htmlStr(_admin_cparam($name)), $_lang['admin.fman.delask']) . "<input type='hidden' name='name' value='" . _htmlStr($name) . "' /></td>
        </tr>
        ";
                }
                break;

                // prejmenovani
            case "rename":
                if (isset($_GET['name'])) {
                    $name = $_GET['name'];
                    $action_submit = "global.do";
                    $action_title = "admin.fman.rename.title";
                    $action_code = "
        <tr>
        <td class='rpad'><strong>" . $_lang['admin.fman.newname'] . ":</strong></td>
        <td><input type='text' name='newname' class='inputmedium' maxlength='64' value='" . _htmlStr(_admin_cparam($name)) . "' /><input type='hidden' name='name' value='" . _htmlStr($name) . "' /></td>
        </tr>
        ";
                }
                break;

                // uprava
            case "edit":

                // priprava
                $continue = false;
                if (isset($_GET['name'])) {
                    $name = $_GET['name'];
                    $dname = _admin_cparam($name);
                    $dname_ext = strtolower(pathinfo($dname, PATHINFO_EXTENSION));
                    if (@file_exists($dir . $dname)) {
                        if (_admin_issafefile($dname)) {
                            $new = false;
                            $continue = true;
                            $content = @file_get_contents($dir . $dname);
                        } else {
                            $message = _formMessage(2, $_lang['admin.fman.msg.disallowedextension']);
                        }
                    }
                } else {
                    $name = "bmV3LnR4dA==";
                    $content = "";
                    $new = true;
                    $continue = true;
                }

                // formular
                if ($continue) {
                    $action_submit = "global.save";
                    $action_acbonus = (!$new ? "&amp;a=edit&amp;name=" . $name : '');
                    $action_title = "admin.fman.edit.title";

                    $action_form_class = 'cform';
                    $codemirror_classes = array('html' => 'html', 'php' => 'php', 'inc' => 'php', 'xhtml' => 'xml', 'xml' => 'xml', 'css' => 'css', 'js' => 'js');
                    if (!empty($dname_ext) && isset($codemirror_classes[$dname_ext])) $area_class = 'codemirror codemirror-' . $codemirror_classes[$dname_ext];
                    else $area_class = '';

                    $action_code = "
        <tr>
        <td class='rpad'><strong>" . $_lang['global.name'] . "</strong></td>
        <td><input type='text' name='name' class='inputmedium' maxlength='64' value='" . _htmlStr(_admin_cparam($name)) . "' />&nbsp;&nbsp;<small>" . $_lang['admin.fman.edit.namenote' . ($new ? '2' : '')] . "</small></td>
        </tr>

        <tr class='valign-top'>
        <td class='rpad'><strong>" . $_lang['admin.content.form.content'] . "</strong></td>
        <td><textarea rows='25' cols='94' class='areabig " . $area_class . "' name='content' wrap='off'>" . _htmlStr($content) . "</textarea></td>
        </tr>
        ";
                }

                break;

                // upload
            case "upload":
                $action_submit = "global.send";
                $action_title = "admin.fman.menu.upload";
                $action_code = "
      <tr class='valign-top'>
      <td class='rpad'><strong>" . $_lang['admin.fman.file'] . ":</strong></td>
      <td id='fmanFiles'><input type='file' name='uf0[]' multiple='multiple' />&nbsp;&nbsp;<a href='#' onclick='return _sysFmanAddFile();'>" . $_lang['admin.fman.upload.addfile'] . "</a></td>
      </tr>

      <tr>
      <td></td>
      <td>
          <label><input type='checkbox' name='upload_rewrite' value='1' /> " . $_lang['global.uploadrewrite'] . "</label>&nbsp;&nbsp;
          " . ((($uplimit = _getUploadLimit(true)) !== null) ? "<small>(" . $_lang['global.uploadlimit'] . ": <em>" . $uplimit . "MB</em>)</small>" : '') . "
      </td>
      </tr>
      ";
                break;

                // addtogallery
            case "addtogallery":
                $action_submit = "global.insert";
                $action_acbonus = "";
                $action_title = "admin.fman.menu.addtogallery";

                // load and check images
                $images_load = _getPostdata(true, "f");
                $images = "";
                $counter = 0;
                foreach ($images_load as $images_load_image) {
                    $images_load_image = pathinfo(base64_decode($images_load_image[1]));
                    if (isset($images_load_image['extension']) and in_array(mb_strtolower($images_load_image['extension']), SL::$imageExt)) {
                        $images .= "<input type='hidden' name='f" . $counter . "' value='" . base64_encode($images_load_image['basename']) . "' />\n";
                        $counter++;
                    }
                }

                if ($counter != 0) {
                    $action_code = "
      <tr>
      <td class='rpad'><strong>" . $_lang['admin.fman.addtogallery.galllery'] . "</strong></td>
      <td>
      " . _admin_rootSelect("gallery", 5, -1, false, null, null) . "
      " . $images . "
      </td>
      </tr>

      <tr>
      <td class='rpad'><strong>" . $_lang['admin.fman.addtogallery.counter'] . "</strong></td>
      <td>" . $counter . "</td>
      </tr>
      ";
                } else {
                    $message = _formMessage(2, $_lang['admin.fman.addtogallery.noimages']);
                }
                break;

        }

        // dokonceni kodu
        if ($action_code != "") {

            $action_code = "
<div id='fman-action'>
<h2>" . $_lang[$action_title] . "</h2>
<form action='" . $url . $action_acbonus . "'" . ((null !== $action_form_class) ? " class='" . $action_form_class . "'" : '') . " method='post' enctype='multipart/form-data'>
<input type='hidden' name='action' value='" . _htmlStr($_GET['a']) . "' />
<table class='formtable'>
" . $action_code . "

  <tr>
  <td></td>
  <td><input type='submit' value='" . $_lang[$action_submit] . "' />&nbsp;&nbsp;<a href='" . $url . "'>" . $_lang['global.cancel'] . "</a></td>
  </tr>

</table>
" . _xsrfProtect() . "</form>
</div>
";

        }

    }

    /* ---  vystup  --- */

    // menu, formular akce
    $output .= $message . "
    <a name='top'></a>
    <p class='fman-menu'>
    <a href='" . $url . "&amp;a=upload'>" . $_lang['admin.fman.menu.upload'] . "</a>
    <a href='" . $url . "&amp;a=edit'>" . $_lang['admin.fman.menu.createfile'] . "</a>
    <a href='" . $url . "&amp;a=newfolder'>" . $_lang['admin.fman.menu.createfolder'] . "</a>
    " . ((_loginright_admingallery and _loginright_admincontent) ? "<a href='#' onclick='return _sysFmanAddSelectedToGallery()'>" . $_lang['admin.fman.menu.addtogallery'] . "</a>" : '') . "
    <a href='" . $url_base . "dir=" . urlencode($defdir) . "'>" . $_lang['admin.fman.menu.home'] . "</a>
    <strong>" . $_lang['admin.fman.currentdir'] . ":</strong> /" . mb_substr($dir, mb_strlen(_indexroot)) . "
    </p>

    " . $action_code;

    // vypis
    $output .= "
    <form action='" . $url . "' method='post' name='filelist'>
    <input type='hidden' name='action' value='-1' />
    <input type='hidden' name='param' value='-1' />
    <table id='fman-list'>
    <tr><td width='60%'></td><td width='15%'></td><td width='25%'></td></tr>
    ";

    $highlight = false;

    // adresare
    $handle = @opendir($dir);
    $items = array();
    while (false !== ($item = @readdir($handle))) {
        if (@is_dir($dir . $item) and $item != "." and $item != "..") {
            $items[] = $item;
        }
    }
    natsort($items);
    $items = array_merge(array(".."), $items);
    foreach ($items as $item) {

        // adresar nebo odkaz na nadrazeny adresar
        if ($item == "..") {
            if (mb_substr($dir, mb_strlen($dir) - 3, 3) != "../" and $dir != "./") {
                $dirhref = mb_substr($dir, 0, mb_strrpos($dir, '/'));
                $dirhref = mb_substr($dirhref, 0, mb_strrpos($dirhref, '/'));
            } else {
                // nejit za korenovy adresar systemu
                continue;
            }
        } else {
            $dirhref = $dir . $item;
        }

        if ($highlight) {
            $hl_class = " class='hl'";
        } else {
            $hl_class = "";
        }

        $output .= "
      <tr" . $hl_class . ">
      <td colspan='" . (($item == "..") ? "3" : "2") . "'><a href='" . $url_base . "dir=" . urlencode($dirhref) . "/'><img src='images/icons/fman/dir.png' alt='dir' class='icon' />" . _htmlStr(_cutStr($item, 64, false)) . "</a></td>
      " . (($item != "..") ? "<td class='actions'><a href='" . $url . "&amp;a=delete&amp;name=" . _admin_mparam($item) . "'>" . $_lang['global.delete'] . "</a> | <a href='" . $url . "&amp;a=rename&amp;name=" . _admin_mparam($item) . "'>" . $_lang['admin.fman.rename'] . "</a></td>" : '') . "
      </tr>
      ";

        $highlight = !$highlight;
    }

    $output .= "<tr><td colspan='3'>&nbsp;</td></tr>";

    // soubory
    rewinddir($handle);
    $items = array();
    while (false !== ($item = @readdir($handle))) {
        if (!@is_dir($dir . $item) and $item != "..") {
            $items[] = $item;
        }
    }
    natsort($items);
    $filecounter = 0;
    $sizecounter = 0;
    foreach ($items as $item) {
        $filecounter++;
        $row_classes = array();

        // ikona
        $iteminfo = pathinfo($item);
        if (!isset($iteminfo['extension'])) {
            $iteminfo['extension'] = "";
        }
        $ext = mb_strtolower($iteminfo['extension']);
        $image = false;
        if (in_array($ext, array("rar", "zip", "tar", "gz", "tgz", "7z", "cab", "xar", "xla", "777", "alz", "arc", "arj", "bz", "bz2", "bza", "bzip2", "dz", "gza", "gzip", "lzma", "lzs", "lzo", "s7z", "taz", "tbz", "tz", "tzip"))) {
            $icon = "archive";
        } elseif (in_array($ext, array("jpg", "jpeg", "png", "gif", "bmp", "jp2", "tga", "pcx", "tif", "ppf", "pct", "pic", "ai", "ico"))) {
            $icon = "image";
            $image = true;
        } elseif (in_array($ext, array("sql", "php", "php3", "php4", "php5", "phtml", "py", "asp", "cgi", "shtml", "htaccess", "txt", "nfo", "rtf", "html", "htm", "xhtml", "css", "js", "ini", "bat", "inf", "me", "inc", "xml"))) {
            $icon = "editable";
        } elseif (in_array($ext, array("wav", "mp3", "mid", "rmi", "wma", "mpeg", "mpg", "wmv", "3gp", "mp4", "m4a", "xac", "aif", "au", "avi", "voc", "snd", "vox", "ogg", "flac", "mov", "aac", "vob", "amr", "asf", "rm", "ra", "ac3", "swf", "flv"))) {
            $icon = "media";
        } elseif (in_array($ext, array("exe", "com", "bat", "dll"))) {
            $icon = "executable";
        } elseif (in_array($ext, array("sld", "slp", "sle"))) {
            $icon = "sl";
        } else {
            $icon = "other";
        }

        $filesize = @filesize($dir . $item);

        if ($highlight) {
            $row_classes[] = 'hl';
        }
        if (isset($uploaded[$item])) {
            $row_classes[] = 'fman-uploaded';
        }

        $output .= "
      <tr class='" . implode(' ', $row_classes) . "'>
      <td><input type='checkbox' name='f" . $filecounter . "' id='f" . $filecounter . "' value='" . _admin_mparam($item, false) . "' /> <a href='" . _htmlStr($dir . $item) . "' target='_blank'" . ($image ? ' class="lightbox" data-fancybox-group="fman"' : '') . "><img src='images/icons/fman/" . $icon . ".png' alt='file' class='icon' />" . _htmlStr(_cutStr($item, 64, false)) . "</a></td>
      <td>" . (round($filesize / 1024)) . "kB</td>
      <td class='actions'>" . (_admin_issafefile($item) ? "<a href='" . $url . "&amp;a=delete&amp;name=" . _admin_mparam($item) . "'>" . $_lang['global.delete'] . "</a> | <a href='" . $url . "&amp;a=rename&amp;name=" . _admin_mparam($item) . "'>" . $_lang['admin.fman.rename'] . "</a>" . (($icon == "editable") ? " | <a href='" . $url . "&amp;a=edit&amp;name=" . _admin_mparam($item) . "'>" . $_lang['admin.fman.edit'] . "</a>" : '') : '') . "</td>
      </tr>
      ";

        $sizecounter += $filesize;

        $highlight = !$highlight;
    }

    $output .= "
    </table>
    " . _xsrfProtect() . "</form>

    <p class='fman-menu'>
    <span><strong>" . $_lang['admin.fman.filecounter'] . ":</strong> " . $filecounter . " <small>(" . round($sizecounter / 1024) . "kB)</small></span>
    <a href='#' onclick='return _sysFmanSelect(" . $filecounter . ", 1)'>" . $_lang['admin.fman.selectall'] . "</a>
    <a href='#' onclick='return _sysFmanSelect(" . $filecounter . ", 2)'>" . $_lang['admin.fman.deselectall'] . "</a>
    <a href='#' onclick='return _sysFmanSelect(" . $filecounter . ", 3)'>" . $_lang['admin.fman.inverse'] . "</a>
    <strong>" . $_lang['admin.fman.selected'] . ":</strong>&nbsp;&nbsp;
    <a href='#' onclick='return _sysFmanMoveSelected()'>" . $_lang['admin.fman.selected.move'] . "</a>
    <a href='#' onclick='return _sysFmanDeleteSelected()'>" . $_lang['admin.fman.selected.delete'] . "</a>
    <a href='#top'><big>&uarr;</big></a>
    </p>
    ";

}
