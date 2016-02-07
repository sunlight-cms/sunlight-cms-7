<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  odstraneni ankety  --- */
$message = "";
if (isset($_GET['del']) && _xsrfCheck(true)) {
    $del = intval($_GET['del']);
    DB::query("DELETE FROM `" . _mysql_prefix . "-polls` WHERE id=" . $del . _admin_pollAccess());
    if (DB::affectedRows() != 0) {
        $message = _formMessage(1, $_lang['global.done']);
    }
}

/* ---  vystup  --- */

// filtr autoru
if (_loginright_adminpollall and isset($_GET['author']) and $_GET['author'] != -1) {
    $pasep = true;
    $author_filter_id = intval($_GET['author']);
    $author_filter = "author=" . intval($_GET['author']);
} else {
    $pasep = false;
    $author_filter = "";
    $author_filter_id = -1;
}

$output .= "
<p class='bborder'>" . $_lang['admin.content.polls.p'] . "</p>
<p><img src='images/icons/new.png' class='icon' alt='new' /><a href='index.php?p=content-polls-edit'>" . $_lang['admin.content.polls.new'] . "</a></p>
";

// filtr
if (_loginright_adminpollall) {
    $output .= "
  <form class='cform' action='index.php' method='get'>
  <input type='hidden' name='p' value='content-polls' />
  <strong>" . $_lang['admin.content.polls.filter'] . ":</strong> " . _admin_authorSelect("author", $author_filter_id, "adminpoll=1", null, $_lang['global.all2']) . " <input type='submit' value='" . $_lang['global.apply'] . "' />
  </form>
  ";
}

// strankovani
$paging = _resultPaging("index.php?p=content-polls", 25, "polls", $author_filter . _admin_pollAccess($pasep), "&amp;filter=" . $author_filter_id);
$output .= $paging[0] . "<br />";

$output .= $message . "
<table class='list'>
<thead><tr><td>" . $_lang['admin.content.form.question'] . "</td>" . (_loginright_adminpollall ? "<td>" . $_lang['article.author'] . "</td>" : '') . "<td>" . $_lang['global.id'] . "</td><td>" . $_lang['global.action'] . "</td></tr></thead>
<tbody>
";

// vypis anket
$query = DB::query("SELECT question,id,author,locked FROM `" . _mysql_prefix . "-polls` WHERE " . $author_filter . _admin_pollAccess($pasep) . " ORDER BY id DESC " . $paging[1]);
if (DB::size($query) != 0) {
    while ($item = DB::row($query)) {
        if (_loginright_adminpollall) {
            $username = "<td>" . _linkUser($item['author']) . "</td>";
        } else {
            $username = "";
        }
        $output .= "<tr><td><a href='index.php?p=content-polls-edit&amp;id=" . $item['id'] . "' class='block'>" . _cutStr($item['question'], 64) . "</a>" . (($item['locked'] == 1) ? " (" . $_lang['admin.content.form.locked'] . ")" : '') . "</td>" . $username . "<td>" . $item['id'] . "</td><td><a href='" . _xsrfLink("index.php?p=content-polls&amp;author=" . $author_filter_id . "&amp;page=" . $paging[2] . "&amp;del=" . $item['id']) . "' onclick='return _sysConfirm();'><img src='images/icons/delete.png' class='icon' alt='del' /> " . $_lang['global.delete'] . "</a></td></tr>\n";
    }
} else {
    $output .= "<tr><td colspan='" . (_loginright_adminpollall ? "4" : "3") . "'>" . $_lang['global.nokit'] . "</td></tr>";
}

$output .= "
</tbody>
</table>

<br />
<form class='cform' action='index.php' method='get'>
<input type='hidden' name='p' value='content-polls-edit' />
" . $_lang['admin.content.polls.openid'] . ": <input type='text' name='id' class='inputmini' /> <input type='submit' value='" . $_lang['global.open'] . "' />
</form>
";
