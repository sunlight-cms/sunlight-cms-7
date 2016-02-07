<?php
/* ---  incializace jadra  --- */
require '../require/load.php';
SL::init('../');

/* ---  nacteni argumentu  --- */

if (!isset($_GET['redir_id'], $_GET['redir_type'])) die;
$id = intval($_GET['redir_id']);
$type = intval($_GET['redir_type']);
if ($type !== 1) $type = 0;

/* ---  test existence  --- */

if (0 === $type) $query = DB::query('SELECT `id`,`title_seo` FROM `' . _mysql_prefix . '-root` WHERE `id`=' . $id);
else $query = DB::query('SELECT art.`id`,art.`title_seo`,cat.`title_seo` AS cat_title_seo FROM `' . _mysql_prefix . '-articles` AS art JOIN `' . _mysql_prefix . '-root` AS cat ON(cat.id=art.home1) WHERE art.`id`=' . $id);
$query = DB::row($query);
if ($query === false) {
    // neexistuje
    $_GET = array('m' => '404');
    define('_index_noinit', true);
    require _indexroot . 'index.php';
    die;
}

/* ---  presmerovani  --- */

// sestavit adresu
$redir = (($type === 0) ? _linkRoot($query['id'], $query['title_seo']) : _linkArticle($query['id'], $query['title_seo'], $query['cat_title_seo']));
unset($_GET['redir_id'], $_GET['redir_type']);
if (!empty($_GET)) $redir = _addGetToLink($redir, _buildQuery($_GET), false);

// poslat hlavicky
header("HTTP/1.1 301 Moved Permanently");
header("Location: " . _url . "/" . $redir);
die;
