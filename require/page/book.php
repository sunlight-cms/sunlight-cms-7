<?php
// kontrola jadra
if (!defined('_core')) {
    exit;
}

// titulek
$title = $query['title'];
if (_template_autoheadings && $query['autotitle']) {
    $content .= "<h1>" . $query['title'] . _linkRSS($id, 3) . "</h1>\n";
}
_extend('call', 'page.book.aftertitle', $extend_args);

// obsah
_extend('call', 'page.book.content.before', $extend_args);
if ($query['content'] != "") $content .= _parseHCM($query['content']);
_extend('call', 'page.book.content.after', $extend_args);

// prispevky
require_once (_indexroot . 'require/functions-posts.php');
$content .= _postsOutput(3, $id, array($query['var2'], _publicAccess($query['var1']), $query['var3']));
