<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  nacteni promennych  --- */
$message = "";
$continue = false;
if (isset($_GET['id']) and isset($_GET['returnid']) and isset($_GET['returnpage'])) {
    $id = intval($_GET['id']);
    $returnid = $_GET['returnid'];
    if ($returnid != "load") {
        $returnid = intval($returnid);
    }
    $returnpage = intval($_GET['returnpage']);
    $query = DB::query("SELECT art.*,cat.title_seo AS cat_title_seo FROM `" . _mysql_prefix . "-articles` AS art JOIN `" . _mysql_prefix . "-root` AS cat ON(cat.id=art.home1) WHERE art.id=" . $id . _admin_artAccess('art'));
    if (DB::size($query) != 0) {
        $query = DB::row($query);
        $readed_counter = $query['readed'];
        if ($returnid == "load") {
            $returnid = $query['home1'];
        }
        $backlink = "index.php?p=content-articles-list&amp;cat=" . $returnid . "&amp;page=" . $returnpage;
        $actionplus = "&amp;id=" . $id . "&amp;returnid=" . $returnid . "&amp;returnpage=" . $returnpage;
        $submittext = "global.savechanges";
        $artlink = " <a href='" . _indexroot . _linkArticle($query['id'], $query['title_seo'], $query['cat_title_seo']) . "' target='_blank'><img src='images/icons/loupe.png' alt='prev' /></a>";
        $new = false;
        $continue = true;
    }
} else {
    $backlink = "index.php?p=content-articles";
    $actionplus = "";
    $submittext = "global.create";
    $artlink = "";
    $new = true;
    $id = -1;
    $readed_counter = 0;
    $query = array("id" => -1, "title" => "", "title_seo" => "", "keywords" => "", "description" => "", "perex" => "", "picture_uid" => null, "content" => "", "infobox" => "", "author" => _loginid, "home1" => -2, "home2" => -1, "home3" => -1, "time" => time(), "visible" => 1, "public" => 1, "comments" => 1, "commentslocked" => 0, "showinfo" => 1, "confirmed" => 0, "rateon" => 1, "readed" => 0, );
    _extend('call', 'admin.article.default', array('data' => &$query));
    if (isset($_GET['new_cat'])) $query['home1'] = (int) $_GET['new_cat'];
    $continue = true;
}

/* ---  ulozeni  --- */
if (isset($_POST['title'])) {

    // nacteni promennych
    $newdata['title'] = DB::esc(_htmlStr($_POST['title']));
    if ($_POST['title_seo'] === '') $_POST['title_seo'] = $_POST['title'];
    $newdata['title_seo'] = _anchorStr($_POST['title_seo'], true);
    $newdata['keywords'] = DB::esc(_htmlStr(trim($_POST['keywords'])));
    $newdata['description'] = DB::esc(_htmlStr(trim($_POST['description'])));
    $newdata['home1'] = intval($_POST['home1']);
    $newdata['home2'] = intval($_POST['home2']);
    $newdata['home3'] = intval($_POST['home3']);
    if (_loginright_adminchangeartauthor) $newdata['author'] = intval($_POST['author']);
    else $newdata['author'] = $query['author'];
    $newdata['perex'] = DB::esc($_POST['perex']);
    $newdata['content'] = DB::esc(_filtrateHCM($_POST['content']));
    $newdata['infobox'] = DB::esc(_filtrateHCM(trim($_POST['infobox'])));
    $newdata['public'] = _checkboxLoad('public');
    $newdata['visible'] = _checkboxLoad('visible');
    if (_loginright_adminconfirm || (!_loginright_adminneedconfirm && $newdata['author'] == _loginid)) {
        $newdata['confirmed'] = _checkboxLoad('confirmed');
    } else {
        $newdata['confirmed'] = $query['confirmed'];
    }
    $newdata['comments'] = _checkboxLoad('comments');
    $newdata['commentslocked'] = _checkboxLoad('commentslocked');
    $newdata['rateon'] = _checkboxLoad('rateon');
    $newdata['showinfo'] = _checkboxLoad('showinfo');
    $newdata['resetrate'] = _checkboxLoad('resetrate');
    $newdata['delcomments'] = _checkboxLoad('delcomments');
    $newdata['resetread'] = _checkboxLoad('resetread');
    $newdata['time'] = _loadTime('time', $query['time']);

    // kontrola promennych
    $error_log = array();

    // titulek
    if ($newdata['title'] == "") {
        $error_log[] = $_lang['admin.content.articles.edit.error1'];
    }

    // kategorie
    $homechecks = array("home1", "home2", "home2");
    foreach ($homechecks as $homecheck) {
        if ($newdata[$homecheck] != -1 or $homecheck == "home1") {
            if (DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-root` WHERE type=2 AND id=" . $newdata[$homecheck]), 0) == 0) {
                $error_log[] = $_lang['admin.content.articles.edit.error2'];
            }
        }
    }

    // zruseni duplikatu
    if ($newdata['home1'] == $newdata['home2']) {
        $newdata['home2'] = -1;
    }
    if ($newdata['home2'] == $newdata['home3'] or $newdata['home1'] == $newdata['home3']) {
        $newdata['home3'] = -1;
    }

    // autor
    if (DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-users` WHERE id=" . $newdata['author'] . " AND (id=" . _loginid . " OR (SELECT level FROM `" . _mysql_prefix . "-groups` WHERE id=`" . _mysql_prefix . "-users`.`group`)<" . _loginright_level . ")"), 0) == 0) {
        $error_log[] = $_lang['admin.content.articles.edit.error3'];
    }

    // obrazek
    $newdata['picture_uid'] = $query['picture_uid'];
    if (isset($_FILES['picture']) && is_uploaded_file($_FILES['picture']['tmp_name'])) {

        // priprava moznosti zmeny velikosti
        $picOpts = array(
            'file_path' => $_FILES['picture']['tmp_name'],
            'file_name' => $_FILES['picture']['name'],
            'target_path' => _indexroot . 'pictures/articles/',
            'target_format' => 'jpg',
            'resize' => array(
                'mode' => 'fit',
                'keep_smaller' => true,
                'pad' => false,
                'x' => _article_pic_w,
                'y' => _article_pic_h,
            ),
        );
        _extend('call', 'admin.article.picture', array('opts' => &$picOpts));

        // zpracovani
        $picUid = _pictureProcess($picOpts, $picError);

        if (false !== $picUid) {
            // uspech
            if (isset($query['picture_uid'])) {
                // odstraneni stareho
                @unlink(_pictureStorageGet(_indexroot . 'pictures/articles/', null, $query['picture_uid'], 'jpg'));
            }
            $newdata['picture_uid'] = $picUid;
        } else {
            // chyba
            $error_log[] = $_lang['admin.content.form.picture'] . ' - ' . $picError;
        }

    } elseif (isset($query['picture_uid']) && _checkboxLoad('picture-delete')) {
        // smazani obrazku
        @unlink(_pictureStorageGet(_indexroot . 'pictures/articles/', null, $query['picture_uid'], 'jpg'));
        $newdata['picture_uid'] = null;
    }

    // ulozeni
    if (count($error_log) == 0) {

        if (!$new) {

            // data
            DB::query("UPDATE `" . _mysql_prefix . "-articles` SET title='" . $newdata['title'] . "',title_seo='" . $newdata['title_seo'] . "',keywords='" . $newdata['keywords'] . "',description='" . $newdata['description'] . "',home1=" . $newdata['home1'] . ",home2=" . $newdata['home2'] . ",home3=" . $newdata['home3'] . ",author=" . $newdata['author'] . ",perex='" . $newdata['perex'] . "',picture_uid=" . (isset($newdata['picture_uid']) ? '\'' . DB::esc($newdata['picture_uid']) . '\'' : 'NULL') . ",content='" . $newdata['content'] . "',infobox='" . $newdata['infobox'] . "',public=" . $newdata['public'] . ",visible=" . $newdata['visible'] . ",confirmed=" . $newdata['confirmed'] . ",comments=" . $newdata['comments'] . ",commentslocked=" . $newdata['commentslocked'] . ",rateon=" . $newdata['rateon'] . ",showinfo=" . $newdata['showinfo'] . ",time=" . $newdata['time'] . " WHERE id=" . $id);

            // smazani komentaru
            if ($newdata['delcomments'] == 1) {
                DB::query("DELETE FROM `" . _mysql_prefix . "-posts` WHERE type=2 AND home=" . $id);
            }

            // vynulovani poctu precteni
            if ($newdata['resetread'] == 1) {
                DB::query("UPDATE `" . _mysql_prefix . "-articles` SET readed=0 WHERE id=" . $id);
            }

            // vynulovani hodnoceni
            if ($newdata['resetrate'] == 1) {
                DB::query("UPDATE `" . _mysql_prefix . "-articles` SET ratenum=0,ratesum=0 WHERE id=" . $id);
                DB::query("DELETE FROM `" . _mysql_prefix . "-iplog` WHERE type=3 AND var=" . $id);
            }

            // udalost
            _extend('call', 'admin.article.edit', array('id' => $id, 'data' => $newdata));

            // presmerovani
            define('_redirect_to', 'index.php?p=content-articles-edit&id=' . $id . '&saved&returnid=' . $returnid . '&returnpage=' . $returnpage);

            return;

        } else {

            // vlozeni
            DB::query("INSERT INTO `" . _mysql_prefix . "-articles` (title,title_seo,keywords,description,perex,picture_uid,content,infobox,author,home1,home2,home3,time,visible,public,comments,commentslocked,confirmed,showinfo,readed,rateon,ratenum,ratesum) VALUES ('" . $newdata['title'] . "','" . $newdata['title_seo'] . "','" . $newdata['keywords'] . "','" . $newdata['description'] . "','" . $newdata['perex'] . "'," . (isset($newdata['picture_uid']) ? '\'' . DB::esc($newdata['picture_uid']) . '\'' : 'NULL') . ",'" . $newdata['content'] . "','" . $newdata['infobox'] . "'," . $newdata['author'] . "," . $newdata['home1'] . "," . $newdata['home2'] . "," . $newdata['home3'] . "," . $newdata['time'] . "," . $newdata['visible'] . "," . $newdata['public'] . "," . $newdata['comments'] . "," . $newdata['commentslocked'] . "," . $newdata['confirmed'] . "," . $newdata['showinfo'] . ",0," . $newdata['rateon'] . ",0,0)");
            $newid = DB::insertID();

            // udalost
            _extend('call', 'admin.article.new', array('id' => $newid, 'data' => $newdata));

            // presmerovani
            define('_redirect_to', 'index.php?p=content-articles-edit&id=' . $newid . '&created&returnid=' . $newdata['home1'] . '&returnpage=1');

            return;

        }

    } else {
        $message = _formMessage(2, _eventList($error_log, 'errors'));
    }

}

/* ---  vystup  --- */
if ($continue) {

    // vyber autora
    if (_loginright_adminchangeartauthor) {
        $author_select = _admin_authorSelect("author", $query['author'], "adminart=1", "selectmedium");
    } else {
        $author_select = "";
    }

    // zprava
    if (isset($_GET['saved'])) {
        $message = _formMessage(1, $_lang['global.saved'] . "&nbsp;&nbsp;<small>(" . _formatTime(time()) . ")</small>");
    }
    if (isset($_GET['created'])) {
        $message = _formMessage(1, $_lang['global.created']);
    }

    // wysiwyg editor
    $output .= _admin_wysiwyg();

    // vypocet hodnoceni
    if (!$new) {
        if ($query['ratenum'] != 0) {
            $rate = DB::result(DB::query("SELECT ROUND(ratesum/ratenum) FROM `" . _mysql_prefix . "-articles` WHERE id=" . $query['id']), 0) . "%, " . $query['ratenum'] . "x";
        } else {
            $rate = $_lang['article.rate.nodata'];
        }
    } else {
        $rate = "";
    }

    // seo title input
    $seo_input = "<input type='text' name='title_seo' value='" . $query['title_seo'] . "' maxlength='255' class='input" . (($author_select != '') ? 'medium' : 'big') . "' />";

    // obrazek
    $picture = '';
    if (isset($query['picture_uid'])) {
        $picture .= "<img src='" . _pictureStorageGet(_indexroot . 'pictures/articles/', null, $query['picture_uid'], 'jpg') . "' alt='article picture' id='is-picture-file' />
<label id='is-picture-delete'><input type='checkbox' name='picture-delete' value='1' /> <img src='images/icons/delete3.png' class='icon' alt='" . $_lang['global.delete'] . "' /></label>";
    } else $picture .= "<img src='images/art-no-pic.png' alt='no picture' />\n";
    $picture .= "<input type='file' name='picture' id='is-picture-upload' />\n";

    // formular
    $output .= "
<a href='" . $backlink . "' class='backlink'>&lt; " . $_lang['global.return'] . "</a>
<h1>" . $_lang['admin.content.articles.edit.title'] . "</h1>
<p class='bborder'>" . $_lang['admin.content.articles.edit.p'] . "</p>" . $message . "

" . (($new == true and _loginright_adminneedconfirm) ? _admin_smallNote($_lang['admin.content.articles.edit.newconfnote']) : '') . "
" . (($query['confirmed'] != 1) ? _admin_smallNote($_lang['admin.content.articles.edit.confnote']) : '') . "

" . ((!$new && DB::result(DB::query('SELECT COUNT(*) FROM `' . _mysql_prefix . '-articles` WHERE `id`!=' . $query['id'] . ' AND `home1`=' . $query['home1'] . ' AND `title_seo`=\'' . $query['title_seo'] . '\''), 0) != 0) ? _formMessage(2, $_lang['admin.content.form.title_seo.collision']) : '') . "

<form class='cform' action='index.php?p=content-articles-edit" . $actionplus . "' method='post' enctype='multipart/form-data' name='artform'" . _jsCheckForm("artform", array("title")) . ">

<table class='formtable'>

<tr>
<td class='rpad'><strong>" . $_lang['article.category'] . "</strong></td>
<td>" . _admin_rootSelect("home1", 2, $query['home1'], false) . " " . _admin_rootSelect("home2", 2, $query['home2'], true) . " " . _admin_rootSelect("home3", 2, $query['home3'], true) . "</td>
</tr>

<tr>
<td class='rpad'><strong>" . $_lang['admin.content.form.title'] . "</strong></td>
<td><input type='text' name='title' value='" . $query['title'] . "' class='inputbig' /></td>
</tr>

<tr>
<td class='rpad'><strong>" . $_lang['admin.content.form.title_seo'] . "</strong></td>
<td>" . (($author_select == '' ? $seo_input : "
    <table class='ae-twoi'><tr>
    <td>" . $seo_input . "</td>
    <td class='rpad'><strong>" . $_lang['article.author'] . "</strong></td>
    <td>" . $author_select . "</td>
    </tr></table>
")) . "</td>
</tr>

<tr>
<td class='rpad'><strong>" . $_lang['admin.content.form.description'] . "</strong></td>
<td>
    <table class='ae-twoi'><tr>
    <td><input type='text' name='description' value='" . $query['description'] . "' maxlength='128' class='inputmedium' /></td>
    <td class='rpad'><strong>" . $_lang['admin.content.form.keywords'] . "</strong></td>
    <td><input type='text' name='keywords' value='" . $query['keywords'] . "' maxlength='128' class='inputmedium' /></td>
    </tr></table>
</td>
</tr>

<tr class='valign-top'>
<td class='rpad'><strong>" . $_lang['admin.content.form.perex'] . "</strong></td>
<td><textarea name='perex' rows='9' cols='94' class='areabigperex codemirror'>" . _htmlStr($query['perex']) . "</textarea></td>
</tr>

<tr class='valign-top'>
<td class='rpad'><strong>" . $_lang['admin.content.form.content'] . "</strong>" . $artlink . "</td>
<td>

  <table id='ae-table'>
  <tr class='valign-top'>
    <td id='content-cell'>
      <textarea name='content' rows='25' cols='68' class='wysiwyg_editor" . ((!_wysiwyg || !_loginwysiwyg) ? ' codemirror' : '') . "'>" . _htmlStr($query['content']) . "</textarea>
    </td>
    <td id='is-cell'>
      <div id='is-cell-wrapper'>
      <div id='is-cell-content'>

      <h2>" . $_lang['admin.content.form.picture'] . "</h2>
      <div id='is-picture'>" . $picture . "</div>

      <h2>" . $_lang['admin.content.form.settings'] . "</h2>
      <p id='is-settings'>
      <label><input type='checkbox' name='public' value='1'" . _checkboxActivate($query['public']) . " /> " . $_lang['admin.content.form.public'] . "</label>
      <label><input type='checkbox' name='visible' value='1'" . _checkboxActivate($query['visible']) . " /> " . $_lang['admin.content.form.visible'] . "</label>
      " . ((_loginright_adminconfirm || (!_loginright_adminneedconfirm && $query['author'] == _loginid)) ? "<label><input type='checkbox' name='confirmed' value='1'" . _checkboxActivate($query['confirmed']) . " /> " . $_lang['admin.content.form.confirmed'] . "</label>" : '') . "
      <label><input type='checkbox' name='comments' value='1'" . _checkboxActivate($query['comments']) . " /> " . $_lang['admin.content.form.comments'] . "</label>
      <label><input type='checkbox' name='commentslocked' value='1'" . _checkboxActivate($query['commentslocked']) . " /> " . $_lang['admin.content.form.commentslocked'] . "</label>
      <label><input type='checkbox' name='rateon' value='1'" . _checkboxActivate($query['rateon']) . " /> " . $_lang['admin.content.form.artrate'] . "</label>
      <label><input type='checkbox' name='showinfo' value='1'" . _checkboxActivate($query['showinfo']) . " /> " . $_lang['admin.content.form.showinfo'] . "</label>
      " . (!$new ? "<label><input type='checkbox' name='resetrate' value='1' /> " . $_lang['admin.content.form.resetartrate'] . " <small>(" . $rate . ")</small></label>" : '') . "
      " . (!$new ? "<label><input type='checkbox' name='delcomments' value='1' /> " . $_lang['admin.content.form.delcomments'] . " <small>(" . DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-posts` WHERE home=" . $query['id'] . " AND type=2"), 0) . ")</small></label>" : '') . "
      " . (!$new ? "<label><input type='checkbox' name='resetread' value='1' /> " . $_lang['admin.content.form.resetartread'] . " <small>(" . $readed_counter . ")</small></label>" : '') . "
      </p>

      <h2>" . $_lang['admin.content.form.infobox'] . "</h2>
      <div id='infobox-wrapper'>
        <textarea name='infobox' rows='10' cols='20' class='codemirror'>" . _htmlStr($query['infobox']) . "</textarea>
      </div>

      </div>
      </div>
    </td>
  </tr>
  </table>

</td>
</tr>

<tr id='time-cell'>
<td class='rpad'><strong>" . $_lang['article.posted'] . "</strong></td>
<td>" . _editTime('time', $query['time'], true, $new) . "</td>
</tr>

<tr>
<td></td>
<td id='ae-lastrow'><br /><input type='submit' value='" . $_lang[$submittext] . "' />
" . (!$new ? "
&nbsp;&nbsp;
<span class='customsettings'><a href='index.php?p=content-articles-delete&amp;id=" . $query['id'] . "&amp;returnid=" . $query['home1'] . "&amp;returnpage=1'><span><img src='images/icons/delete.png' alt='del' class='icon' />" . $_lang['global.delete'] . "</span></a></span>&nbsp;&nbsp;
<span class='customsettings'><small>" . $_lang['admin.content.form.thisid'] . " " . $query['id'] . "</small></span>
" : '') . "

</td>
</tr>

</table>

" . _xsrfProtect() . "</form>

";

} else {
    $output .= "<a href='index.php?p=content-articles' class='backlink'>&lt; " . $_lang['global.return'] . "</a>\n<h1>" . $_lang['admin.content.articles.edit.title'] . "</h1>\n" . _formMessage(3, $_lang['global.badinput']);
}
