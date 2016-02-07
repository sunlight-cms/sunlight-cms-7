<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  pripava promennych  --- */
$message = "";
$continue = false;
if (isset($_GET['g'])) {
    $g = intval($_GET['g']);
    $galdata = DB::query("SELECT title,var2,var3,var4 FROM `" . _mysql_prefix . "-root` WHERE id=" . $g . " AND type=5");
    if (DB::size($galdata) != 0) {
        $galdata = DB::row($galdata);
        $continue = true;
    }
}

/* ---  akce  --- */
if (isset($_POST['xaction']) && $continue) {

    switch ($_POST['xaction']) {

            /* -  vlozeni obrazku  - */
        case 1:

            // nacteni zakladnich promennych
            $title = DB::esc(_htmlStr(trim($_POST['title'])));
            if (!_checkboxLoad("autoprev")) {
                $prev = DB::esc(_htmlStr($_POST['prev']));
            } else {
                $prev = "";
            }
            $full = DB::esc(_htmlStr($_POST['full']));

            // vlozeni na zacatek nebo nacteni poradoveho cisla
            if (_checkboxLoad("moveords")) {
                $smallerord = DB::query("SELECT ord FROM `" . _mysql_prefix . "-images` WHERE home=" . $g . " ORDER BY ord LIMIT 1");
                if (DB::size($smallerord) != 0) {
                    $smallerord = DB::row($smallerord);
                    $ord = $smallerord['ord'];
                } else {
                    $ord = 1;
                }
                DB::query("UPDATE `" . _mysql_prefix . "-images` SET ord=ord+1 WHERE home=" . $g);
            } else {
                $ord = floatval($_POST['ord']);
            }

            // kontrola a vlozeni
            if ($full != '') {
                DB::query("INSERT INTO `" . _mysql_prefix . "-images` (home,ord,title,prev,full) VALUES(" . $g . "," . $ord . ",'" . $title . "','" . $prev . "','" . $full . "')");
                $message = _formMessage(1, $_lang['global.inserted']);
            } else {
                $message = _formMessage(2, $_lang['admin.content.manageimgs.insert.error']);
            }

            break;

            /* -  posunuti poradovych cisel  - */
        case 2:

            // nacteni promennych
            $action = intval($_POST['moveaction']);
            $zonedir = intval($_POST['zonedir']);
            $zone = floatval($_POST['zone']);
            $offset = floatval($_POST['offset']);

            // aplikace
            if ($action == 1) {
                $sign = "+";
            } else {
                $sign = "-";
            }
            if ($zonedir == 1) {
                $zonedir = ">";
            } else {
                $zonedir = "<";
            }
            DB::query("UPDATE `" . _mysql_prefix . "-images` SET ord=ord" . $sign . $offset . " WHERE ord" . $zonedir . "=" . $zone . " AND home=" . $g);
            $message = _formMessage(1, $_lang['global.done']);

            break;

            /* -  vycisteni poradovych cisel  - */
        case 3:
            $items = DB::query("SELECT id FROM `" . _mysql_prefix . "-images` WHERE home=" . $g . " ORDER BY ord");
            $counter = 1;
            while ($item = DB::row($items)) {
                DB::query("UPDATE `" . _mysql_prefix . "-images` SET ord=" . $counter . " WHERE id=" . $item['id'] . " AND home=" . $g);
                $counter++;
            }
            $message = _formMessage(1, $_lang['global.done']);
            break;

            /* -  aktualizace obrazku  - */
        case 4:
            $lastid = -1;
            $sql = "";
            foreach ($_POST as $var => $val) {
                if ($var == "xaction") {
                    continue;
                }
                $var = explode("_", $var);
                if (count($var) == 2) {
                    $id = intval(substr($var[0], 1));
                    $var = DB::esc($var[1]);
                    if ($lastid == -1) {
                        $lastid = $id;
                    }
                    $quotes = "'";
                    $skip = false;
                    switch ($var) {
                        case "title":
                            $val = DB::esc(_htmlStr($val));
                            break;
                        case "full":
                            $val = 'IF(in_storage,full,\'' . DB::esc(_htmlStr($val)) . '\')';
                            $quotes = '';
                            break;
                        case "prevtrigger":
                            $var = "prev";
                            if (!_checkboxLoad('i' . $id . '_autoprev')) {
                                $val = DB::esc(_htmlStr($_POST['i' . $id . '_prev']));
                            } else {
                                $val = "";
                            }
                            break;
                        case "ord":
                            $val = intval($val);
                            $quotes = '';
                            break;
                        default:
                            $skip = true;
                            break;
                    }

                    // ukladani a cachovani
                    if (!$skip) {

                        // ulozeni
                        if ($lastid != $id) {
                            $sql = trim($sql, ",");
                            DB::query("UPDATE `" . _mysql_prefix . "-images` SET " . $sql . " WHERE id=" . $lastid . " AND home=" . $g);
                            $sql = "";
                            $lastid = $id;
                        }

                        $sql .= $var . "=" . $quotes . $val . $quotes . ",";
                    }

                }
            }

            // ulozeni posledniho nebo jedineho obrazku
            if ($sql != "") {
                $sql = substr($sql, 0, -1);
                DB::query("UPDATE `" . _mysql_prefix . "-images` SET " . $sql . " WHERE id=" . $id . " AND home=" . $g);
            }

            $message = _formMessage(1, $_lang['global.saved']);
            break;

            /* -  presunuti obrazku  - */
        case 5:
            $newhome = intval($_POST['newhome']);
            if ($newhome != $g) {
                if (DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-root` WHERE id=" . $newhome . " AND type=5"), 0) != 0) {
                    if (DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-images` WHERE home=" . $g), 0) != 0) {

                        // posunuti poradovych cisel v cilove galerii
                        $moveords = _checkboxLoad("moveords");
                        if ($moveords) {

                            // nacteni nejvetsiho poradoveho cisla v teto galerii
                            $greatestord = DB::query("SELECT ord FROM `" . _mysql_prefix . "-images` WHERE home=" . $g . " ORDER BY ord DESC LIMIT 1");
                            $greatestord = DB::row($greatestord);
                            $greatestord = $greatestord['ord'];

                            DB::query("UPDATE `" . _mysql_prefix . "-images` SET ord=ord+" . $greatestord . " WHERE home=" . $newhome);
                        }

                        // presun obrazku
                        DB::query("UPDATE `" . _mysql_prefix . "-images` SET home=" . $newhome . " WHERE home=" . $g);

                        // zprava
                        $message = _formMessage(1, $_lang['global.done']);

                    } else {
                        $message = _formMessage(2, $_lang['admin.content.manageimgs.moveimgs.nokit']);
                    }
                } else {
                    $message = _formMessage(2, $_lang['global.badinput']);
                }
            } else {
                $message = _formMessage(2, $_lang['admin.content.manageimgs.moveimgs.samegal']);
            }
            break;

            /* -  odstraneni vsech obrazku  - */
        case 6:
            if (_checkboxLoad("confirm")) {
                _tmpGalStorageCleanOnDel('home=' . $g);
                DB::query("DELETE FROM `" . _mysql_prefix . "-images` WHERE home=" . $g);
                $message = _formMessage(1, $_lang['global.done']);
            }
            break;

            /* -  upload obrazku  - */
        case 7:

            // prepare vars
            $done = array();
            $total = 0;

            // prepare and check image storage
            $stor_a = 'pictures/galleries/' . $g . '/';
            $stor = _indexroot . $stor_a;
            if (($nostor = !is_dir($stor)) || !is_writeable($stor)) {
                // try to create or chmod
                if ($nostor && !mkdir($stor, 0777) || !$nostor && !chmod($stor, 0777)) {
                    $message = _formMessage(3, sprintf($_lang['admin.content.manageimgs.upload.acerr'], $stor));
                    break;
                }
            }

            // process uploads
            foreach ($_FILES as $file) {

                if (!is_array($file['name'])) continue;
                for ($i = 0; isset($file['name'][$i]); ++$i) {

                    ++$total;

                    // check file
                    if ($file['error'][$i] != 0 || !is_uploaded_file($file['tmp_name'][$i])) {
                        continue;
                    }

                    // prepare options
                    $picOpts = array(
                        'file_path' => $file['tmp_name'][$i],
                        'file_name' => $file['name'][$i],
                        'target_path' => $stor,
                        'jpg_quality' => 95,
                        'resize' => array(
                            'mode' => 'fit',
                            'keep_smaller' => true,
                            'pad' => false,
                            'x' => _galuploadresize_w,
                            'y' => _galuploadresize_h,
                        ),
                    );
                    _extend('call', 'admin.gallery.picture', array('opts' => &$picOpts));
                    
                    // process
                    $picUid = _pictureProcess($picOpts, $picError, $picFormat);

                    if (false === $picUid) {
                        continue;
                    }

                    $done[] = $picUid . '.' . $picFormat;

                }

            }

            // save to database
            if (!empty($done)) {

                // get order number
                if (isset($_POST['moveords'])) {
                    // move
                    $ord = 0;
                    DB::query('UPDATE `' . _mysql_prefix . '-images` SET ord=ord+' . count($done) . ' WHERE home=' . $g);
                } else {
                    // get max + 1
                    $ord = DB::query("SELECT ord FROM `" . _mysql_prefix . "-images` WHERE home=" . $g . " ORDER BY ord DESC LIMIT 1");
                    $ord = DB::row($ord);
                    $ord = $ord['ord'] + 1;
                }

                // query
                $sql = 'INSERT INTO `' . _mysql_prefix . '-images` (home,ord,title,prev,full,in_storage) VALUES';
                for ($i = 0, $last = (count($done) - 1); isset($done[$i]); ++$i) {
                    $sql .= '(' . $g . ',' . $ord . ',\'\',\'\',\'' . $stor_a . $done[$i] . '\',1)';
                    if ($i !== $last) $sql .= ',';
                    ++$ord;
                }
                $sql .= '';
                DB::query($sql);

            }

            // message
            $done = (isset($last) ? ($last + 1) : count($done));
            $message = _formMessage(($done === $total) ? 1 : 2, sprintf($_lang['admin.content.manageimgs.upload.msg'], $done, $total));
            break;

    }

}

/* ---  odstraneni obrazku  --- */
if (isset($_GET['del']) && _xsrfCheck(true) && $continue) {
    $del = intval($_GET['del']);
    _tmpGalStorageCleanOnDel('id=' . $del . ' AND home=' . $g);
    DB::query("DELETE FROM `" . _mysql_prefix . "-images` WHERE id=" . $del . " AND home=" . $g);
    if (DB::affectedRows() === 1) $message = _formMessage(1, $_lang['global.done']);
}

/* ---  vystup  --- */
if ($continue) {
    $output .= "
<a href='index.php?p=content-editgallery&amp;id=" . $g . "' class='backlink'>&lt; návrat zpět</a>
<h1>" . $_lang['admin.content.manageimgs.title'] . "</h1>
<p class='bborder'>" . str_replace("*galtitle*", $galdata['title'], $_lang['admin.content.manageimgs.p']) . "</p>

" . $message . "

<script type='text/javascript'>
/* <![CDATA[ */
$(document).ready(function(){
    $('.hs_fieldset').each(function(){
        var fieldset = this;
        var link = $(fieldset).find('legend > a').get(0);
        var form = $(fieldset).children('form');
        $(form).hide();
        $(link).click(function(){
            $(form).slideToggle('fast');

            return false;
        });
    });
});
/* ]]> */
</script>

<fieldset>
<legend>" . $_lang['admin.content.manageimgs.upload'] . "</legend>
<form action='index.php?p=content-manageimgs&amp;g=" . $g . "' method='post' enctype='multipart/form-data'>
    <p>" . sprintf($_lang['admin.content.manageimgs.upload.text'], _galuploadresize_w, _galuploadresize_h) . "</p>
    <input type='hidden' name='xaction' value='7' />
    <div id='fmanFiles'><input type='file' name='uf0[]' multiple='multiple' />&nbsp;&nbsp;<a href='#' onclick='return _sysFmanAddFile();'>" . $_lang['admin.fman.upload.addfile'] . "</a></div>
    <div class='hr'><hr /></div>
    <p>
        <input type='submit' value='" . $_lang['admin.content.manageimgs.upload.submit'] . "' />" . ((($uplimit = _getUploadLimit(true)) !== null) ? " &nbsp;<small>" . $_lang['global.uploadlimit'] . ": <em>" . _getUploadLimit() . "MB</em>, " . $_lang['global.uploadext'] . ": <em>" . implode(', ', SL::$imageExt) . "</em></small>" : '') . "<br />
        <label><input type='checkbox' value='1' name='moveords' checked='checked' /> " . $_lang['admin.content.manageimgs.moveords'] . "</label>
    </p>
" . _xsrfProtect() . "</form>
</fieldset>

<fieldset class='hs_fieldset'>
<legend><a href='#'>" . $_lang['admin.content.manageimgs.insert'] . "</a> &nbsp;<small>(" . $_lang['admin.content.manageimgs.insert.tip'] . ")</small></legend>
<form action='index.php?p=content-manageimgs&amp;g=" . $g . "' method='post' name='addform' onsubmit='_sysGalTransferPath(this);'>
<input type='hidden' name='xaction' value='1' />

<table>
<tr class='valign-top'>

<td>
    <table>
    <tr>
    <td class='rpad'><strong>" . $_lang['admin.content.form.title'] . "</strong></td>
    <td><input type='text' name='title' class='inputmedium' maxlength='64' /></td>
    </tr>

    <tr>
    <td class='rpad'><strong>" . $_lang['admin.content.form.ord'] . "</strong></td>
    <td><input type='text' name='ord' class='inputsmall' disabled='disabled' />&nbsp;&nbsp;<label><input type='checkbox' name='moveords' value='1' checked='checked' onclick=\"_sysDisableField(this.checked, 'addform', 'ord');\" /> " . $_lang['admin.content.manageimgs.moveords'] . "</label></td>
    </tr>

    <tr>
    <td class='rpad'><strong>" . $_lang['admin.content.manageimgs.prev'] . "</strong></td>
    <td><input type='text' name='prev' class='inputsmall' disabled='disabled' />&nbsp;&nbsp;<label><input type='checkbox' name='autoprev' value='1' checked='checked' onclick=\"_sysDisableField(this.checked, 'addform', 'prev');\" /> " . $_lang['admin.content.manageimgs.autoprev'] . "</label></td>
    </tr>

    <tr>
    <td class='rpad'><strong>" . $_lang['admin.content.manageimgs.full'] . "</strong></td>
    <td><input type='text' name='full' class='inputmedium' /></td>
    </tr>

    <tr>
    <td></td>
    <td><input type='submit' value='" . $_lang['global.insert'] . "' /></td>
    </tr>

    </table>
</td>

<td>
" . (_loginright_adminfman ? "<div id='gallery-browser'>
    " . (!isset($_GET['browserpath']) ? "<a href='#' onclick=\"return _sysGalBrowse('" . urlencode(_upload_dir) . (_loginright_adminfmanlimit ? _loginname . '%2F' : '') . "');\"><img src='images/icons/loupe.png' alt='browse' class='icon' />" . $_lang['admin.content.manageimgs.insert.browser.link'] . "</a>" : "<script type='text/javascript'>_sysGalBrowse('" . _htmlStr($_GET['browserpath']) . "');</script>") . "
</div>" : '') . "
</td>

</tr>
</table>

" . _xsrfProtect() . "</form>
</fieldset>

";

    // strankovani
    $paging = _resultPaging("index.php?p=content-manageimgs&amp;g=" . $g, $galdata['var2'], "images", "home=" . $g);
    $s = $paging[2];

    $output .= "
<fieldset>
<legend>" . $_lang['admin.content.manageimgs.current'] . "</legend>
<form action='index.php?p=content-manageimgs&amp;g=" . $g . "&amp;page=" . $s . "' method='post' name='editform'>
<input type='hidden' name='xaction' value='4' />

<input type='submit' value='" . $_lang['admin.content.manageimgs.savechanges'] . "' class='gallery-savebutton' />
" . $paging[0] . "
<div class='cleaner'></div>";

    // vypis obrazku
    $images = DB::query("SELECT * FROM `" . _mysql_prefix . "-images` WHERE home=" . $g . " ORDER BY ord " . $paging[1]);
    $images_forms = array();
    if (DB::size($images) != 0) {
        // sestaveni formularu
        while ($image = DB::row($images)) {
            // kod nahledu
            $preview = _galleryImage($image, "1", $galdata['var4'], $galdata['var3']);

            // kod formulare
            $images_forms[] .= "
<table>

<tr>
<td class='rpad'><strong>" . $_lang['admin.content.form.title'] . "</strong></td>
<td><input type='text' name='i" . $image['id'] . "_title' class='inputmedium' value='" . $image['title'] . "' maxlength='64' /></td>
</tr>

<tr>
<td class='rpad'><strong>" . $_lang['admin.content.form.ord'] . "</strong></td>
<td><input type='text' name='i" . $image['id'] . "_ord' class='inputmedium' value='" . $image['ord'] . "' /></td>
</tr>

" . (!$image['in_storage'] ? "<tr>
<td class='rpad'><strong>" . $_lang['admin.content.manageimgs.prev'] . "</strong></td>
<td><input type='hidden' name='i" . $image['id'] . "_prevtrigger' value='1' /><input type='text' name='i" . $image['id'] . "_prev' class='inputsmall' value='" . $image['prev'] . "'" . _inputDisable($image['prev'] != "") . " />&nbsp;&nbsp;<label><input type='checkbox' name='i" . $image['id'] . "_autoprev' value='1' onclick=\"_sysDisableField(checked, 'editform', 'i" . $image['id'] . "_prev');\"" . _checkboxActivate($image['prev'] == "") . " /> " . $_lang['admin.content.manageimgs.autoprev'] . "</label></td>
</tr>

<tr>
<td class='rpad'><strong>" . $_lang['admin.content.manageimgs.full'] . "</strong></td>
<td><input type='text' name='i" . $image['id'] . "_full' class='inputmedium' value='" . $image['full'] . "' /></td>
</tr>" : '') . "

<tr class='valign-top'>
<td class='rpad'><strong>" . $_lang['global.preview'] . "</strong></td>
<td>" . $preview . "<br /><br /><a href='" . _xsrfLink("index.php?p=content-manageimgs&amp;g=" . $g . "&amp;page=" . $s . "&amp;del=" . $image['id']) . "' onclick='return _sysConfirm();'><img src='images/icons/delete.png' alt='del' class='icon' />" . $_lang['admin.content.manageimgs.delete'] . "</a></td>
</tr>

</table>
    ";
        }

        // sestaveni tabulky formularu po dvou
        $output .= "\n<table id='gallery-edittable'>";
        $count = count($images_forms);
        for ($i = 0; $i < $count; $i += 2) {
            if (isset($images_forms[$i])) {
                $output .= "<tr><td" . ((0 === ($i % 2) && !isset($images_forms[$i + 1]) && 1 !== $count) ? ' colspan="2"' : '') . " class='gallery-edittable-td'>\n" . $images_forms[$i] . "\n</td>\n";
                if (isset($images_forms[$i + 1])) {
                    $output .= "<td class='gallery-edittable-td'>\n" . $images_forms[$i + 1] . "\n</td></tr>\n";
                } else {
                    $output .= '</tr>' . _nl;
                }
            }
        }
        $output .= '</table>';

        $output .= "<input type='submit' value='" . $_lang['admin.content.manageimgs.savechanges'] . "' class='gallery-savebutton' />\n" . $paging[0];
    } else {
        $output .= '<p>' . $_lang['global.nokit'] . '</p>';
    }

    $output .= "
" . _xsrfProtect() . "</form>
</fieldset>


<a id='func'></a>
<fieldset class='hs_fieldset'>
<legend><a href='#'>" . $_lang['admin.content.manageimgs.moveallords'] . "</a></legend>

<form class='cform' action='index.php?p=content-manageimgs&amp;g=" . $g . "&amp;page=" . $s . "' method='post'>
<input type='hidden' name='xaction' value='2' />
<select name='moveaction'><option value='1'>" . $_lang['admin.content.move.choice1'] . "</option><option value='2'>" . $_lang['admin.content.move.choice2'] . "</option></select>&nbsp;
" . $_lang['admin.content.move.text1'] . "&nbsp;
<select name='zonedir'><option value='1'>" . $_lang['admin.content.move.choice3'] . "</option><option value='2'>" . $_lang['admin.content.move.choice4'] . "</option></select>&nbsp;
" . $_lang['admin.content.move.text2'] . "&nbsp;
<input type='text' name='zone' value='1' class='inputmini' maxlength='5' />&nbsp;,
" . $_lang['admin.content.move.text3'] . "&nbsp;
<input type='text' name='offset' value='1' class='inputmini' maxlength='5' />.&nbsp;
<input type='submit' value='" . $_lang['global.do'] . "' onclick='return _sysConfirm();' />
" . _xsrfProtect() . "</form>

<form class='cform' action='index.php?p=content-manageimgs&amp;g=" . $g . "&amp;page=" . $s . "' method='post'>
<input type='hidden' name='xaction' value='3' />
" . $_lang['admin.content.manageimgs.moveallords.cleanup'] . " <input type='submit' value='" . $_lang['global.do'] . "' onclick='return _sysConfirm();' />
" . _xsrfProtect() . "</form>

</fieldset>

<table width='100%'>
<tr class='valign-top'>

<td width='50%'>
  <fieldset class='hs_fieldset'>
  <legend><a href='#'>" . $_lang['admin.content.manageimgs.moveimgs'] . "</a></legend>

  <form class='cform' action='index.php?p=content-manageimgs&amp;g=" . $g . "&amp;page=" . $s . "' method='post'>
  <input type='hidden' name='xaction' value='5' />
  " . _admin_rootSelect("newhome", 5, -1, false) . " <input type='submit' value='" . $_lang['global.do'] . "' onclick='return _sysConfirm();' /><br /><br />
  <label><input type='checkbox' name='moveords' value='1' checked='checked' /> " . $_lang['admin.content.manageimgs.moveords'] . "</label>
  " . _xsrfProtect() . "</form>

  </fieldset>
</td>

<td>
  <fieldset class='hs_fieldset'>
  <legend><a href='#'>" . $_lang['admin.content.manageimgs.delimgs'] . "</a></legend>

  <form class='cform' action='index.php?p=content-manageimgs&amp;g=" . $g . "&amp;page=" . $s . "' method='post'>
  <input type='hidden' name='xaction' value='6' />
  <label><input type='checkbox' name='confirm' value='1' /> " . $_lang['admin.content.manageimgs.delimgs.confirm'] . "</label>&nbsp;&nbsp;<input type='submit' value='" . $_lang['global.do'] . "' onclick='return _sysConfirm();' />
  " . _xsrfProtect() . "</form>

  </fieldset>
</td>

</tr>
</table>

";
} else {
    $output .= _formMessage(3, $_lang['global.badinput']);
}
