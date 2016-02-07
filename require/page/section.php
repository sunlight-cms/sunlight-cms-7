<?php
// kontrola jadra
if (!defined('_core')) {
    exit;
}

// titulek, obsah
$title = $query['title'];
if (_template_autoheadings && $query['autotitle'] == 1) {
    $content .= "<h1>" . $title . "</h1>";
}
_extend('call', 'page.section.aftertitle', $extend_args);

_extend('call', 'page.section.content.before', $extend_args);
$content .= _parseHCM($query['content']);
_extend('call', 'page.section.content.after', $extend_args);

// komentare
if ($query['var1'] == 1 and _comments) {
    require_once (_indexroot . 'require/functions-posts.php');
    $content .= _postsOutput(1, $id, $query['var3']);
}
