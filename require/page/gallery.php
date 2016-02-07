<?php
// kontrola jadra
if (!defined('_core')) {
    exit;
}

// titulek
$title = $query['title'];
if (_template_autoheadings && $query['autotitle']) {
    $content .= "<h1>" . $query['title'] . "</h1>\n";
    _extend('call', 'page.gallery.aftertitle', $extend_args);
}

// obsah
_extend('call', 'page.gallery.content.before', $extend_args);
if ($query['content'] != "") $content .= _parseHCM($query['content']) . "\n\n<div class='hr'><hr /></div>\n\n";
_extend('call', 'page.gallery.content.after', $extend_args);

// obrazky
$paging = _resultPaging(_indexOutput_url, $query['var2'], "images", "home=" . $id);
$images = DB::query("SELECT * FROM `" . _mysql_prefix . "-images` WHERE home=" . $id . " ORDER BY ord " . $paging[1]);
$images_number = DB::size($images);

if ($images_number != 0) {

    $usetable = $query['var1'] != -1;
    if (_pagingmode == 1 or _pagingmode == 2) {
        $content .= $paging[0];
    }
    if ($usetable) {
        $content .= "<table class='gallery'>\n";
    } else {
        $content .= "<div class='gallery'>\n";
    }

    // obrazky
    $counter = 0;
    $cell_counter = 0;
    while ($img = DB::row($images)) {
        if ($usetable and $cell_counter == 0) {
            $content .= "<tr>\n";
        }

        // bunka
        if ($usetable) {
            $content .= "<td>";
        }
        $content .= _galleryImage($img, $id, $query['var4'], $query['var3']);
        if ($usetable) {
            $content .= "</td>";
        }

        $cell_counter++;
        if ($usetable and ($cell_counter == $query['var1'] or $counter == $images_number - 1)) {
            $cell_counter = 0;
            $content .= "\n</tr>";
        }
        $content .= "\n";
        $counter++;
    }

    if ($usetable) {
        $content .= "</table>";
    } else {
        $content .= "</div>";
    }
    if (_pagingmode == 2 or _pagingmode == 3) {
        $content .= $paging[0];
    }

} else {
    $content .= $_lang['misc.gallery.noimages'];
}
