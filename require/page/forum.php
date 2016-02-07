<?php
// kontrola jadra
if (!defined('_core')) {
    exit;
}

// titulek, obsah
$title = $query['title'];
if (_template_autoheadings && $query['autotitle']) {
    $content .= "<h1>" . $query['title'] . _linkRSS($id, 5) . "</h1>\n";
}
_extend('call', 'page.forum.aftertitle', $extend_args);

// obsah
_extend('call', 'page.forum.content.before', $extend_args);
if ($query['content'] != "") $content .= _parseHCM($query['content']);
_extend('call', 'page.forum.content.after', $extend_args);

// temata
require_once (_indexroot . 'require/functions-posts.php');
$content .= _postsOutput(5, $id, array($query['var1'], _publicAccess($query['var3']), $query['var2']));
