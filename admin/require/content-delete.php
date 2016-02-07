<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  pripava promennych  --- */
$continue = false;
$done = false;
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $query = DB::query("SELECT id,title,type,type_idt,ord FROM `" . _mysql_prefix . "-root` WHERE id=" . $id);
    if (DB::size($query) != 0) {
        $query = DB::row($query);
        if (constant('_loginright_admin' . $type_array[$query['type']])) {
            $continue = true;
        }
    }
}

if ($continue) {

    /* ---  odstraneni  --- */
    if (isset($_POST['confirm'])) {

        // souvisejici polozky
        switch ($query['type']) {

                // komentare v sekcich
            case 1:
                DB::query("DELETE FROM `" . _mysql_prefix . "-posts` WHERE type=1 AND home=" . $id);
                break;

                // clanky v kategoriich a jejich komentare
            case 2:
                $rquery = DB::query("SELECT id,home1,home2,home3 FROM `" . _mysql_prefix . "-articles` WHERE home1=" . $id . " OR home2=" . $id . " OR home3=" . $id);
                while ($item = DB::row($rquery)) {
                    if ($item['home1'] == $id and $item['home2'] == -1 and $item['home3'] == -1) {
                        DB::query("DELETE FROM `" . _mysql_prefix . "-posts` WHERE type=2 AND home=" . $item['id']);
                        DB::query("DELETE FROM `" . _mysql_prefix . "-articles` WHERE id=" . $item['id']);
                        continue;
                    } // delete
                    if ($item['home1'] == $id and $item['home2'] != -1 and $item['home3'] == -1) {
                        DB::query("UPDATE `" . _mysql_prefix . "-articles` SET home1=home2 WHERE id=" . $item['id']);
                        DB::query("UPDATE `" . _mysql_prefix . "-articles` SET home2=-1 WHERE id=" . $item['id']);
                        continue;
                    } // 2->1
                    if ($item['home1'] == $id and $item['home2'] != -1 and $item['home3'] != -1) {
                        DB::query("UPDATE `" . _mysql_prefix . "-articles` SET home1=home2 WHERE id=" . $item['id']);
                        DB::query("UPDATE `" . _mysql_prefix . "-articles` SET home2=home3 WHERE id=" . $item['id']);
                        DB::query("UPDATE `" . _mysql_prefix . "-articles` SET home3=-1 WHERE id=" . $item['id']);
                        continue;
                    } // 2->1,3->2
                    if ($item['home1'] == $id and $item['home2'] == -1 and $item['home3'] != -1) {
                        DB::query("UPDATE `" . _mysql_prefix . "-articles` SET home1=home3 WHERE id=" . $item['id']);
                        DB::query("UPDATE `" . _mysql_prefix . "-articles` SET home3=-1 WHERE id=" . $item['id']);
                        continue;
                    } // 3->1
                    if ($item['home1'] != -1 and $item['home2'] == $id) {
                        DB::query("UPDATE `" . _mysql_prefix . "-articles` SET home2=-1 WHERE id=" . $item['id']);
                        continue;
                    } // 2->x
                    if ($item['home1'] != -1 and $item['home3'] == $id) {
                        DB::query("UPDATE `" . _mysql_prefix . "-articles` SET home3=-1 WHERE id=" . $item['id']);
                        continue;
                    } // 3->x
                }
                break;

                // prispevky v knihach
            case 3:
                DB::query("DELETE FROM `" . _mysql_prefix . "-posts` WHERE type=3 AND home=" . $id);
                break;

                // obrazky v galerii
            case 5:
                _tmpGalStorageCleanOnDel('home=' . $id);
                DB::query("DELETE FROM `" . _mysql_prefix . "-images` WHERE home=" . $id);
                @rmdir(_indexroot . 'pictures/galleries/' . $id . '/');
                break;

                // polozky v rozcestniku
            case 7:
                $rquery = DB::query("SELECT id,ord FROM `" . _mysql_prefix . "-root` WHERE intersection=" . $id);
                while ($item = DB::row($rquery)) {
                    DB::query("UPDATE `" . _mysql_prefix . "-root` SET intersection=-1,ord=" . ($query['ord'] . "." . intval($item['ord'])) . " WHERE id=" . $item['id']);
                }
                break;

                // prispevky ve forech
            case 8:
                DB::query("DELETE FROM `" . _mysql_prefix . "-posts` WHERE type=5 AND home=" . $id);
                break;

                // stranka pluginu
            case 9:
                $handled = false;
                _extend('call', 'ppage.' . $query['type_idt'] . '.delete.do', array('handled' => &$handled, 'query' => $query));
                if ($handled !== true) {
                    $output .= _formMessage(3, sprintf($_lang['plugin.error'], $query['type_idt']));

                    return;
                }
                break;

        }

        DB::query("DELETE FROM `" . _mysql_prefix . "-root` WHERE id=" . $id);
        _extend('call', 'admin.root.delete', array('id' => $id, 'query' => $query));
        define('_redirect_to', 'index.php?p=content&done');

        return;

    }

    /* ---  vystup  --- */

    // pole souvisejicich polozek
    $content_array = array();
    switch ($query['type']) {
        case 1:
            $content_array[] = DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-posts` WHERE type=1 AND home=" . $id), 0) . " " . $_lang['admin.content.delete.comments'];
            break;
        case 2:
            $content_array[] = DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-articles` WHERE home1=" . $id . " AND home2=-1 AND home3=-1"), 0) . " " . $_lang['admin.content.delete.articles'];
            break;
        case 3:
            $content_array[] = DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-posts` WHERE type=3 AND home=" . $id), 0) . " " . $_lang['admin.content.delete.posts'];
            break;
        case 5:
        case 3:
            $content_array[] = DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-images` WHERE home=" . $id), 0) . " " . $_lang['admin.content.delete.images'];
            break;
        case 8:
            $content_array[] = DB::result(DB::query("SELECT COUNT(id) FROM `" . _mysql_prefix . "-posts` WHERE type=5 AND home=" . $id), 0) . " " . $_lang['admin.content.delete.posts'];
            break;
        case 9:
            _extend('call', 'ppage.' . $query['type_idt'] . '.delete.confirm', array('contents' => &$content_array, 'query' => $query));
            break;
        default:
            $content_array[] = $_lang['admin.content.delete.norelated'];
    }

    $output .= "
    <p class='bborder'>" . $_lang['admin.content.delete.p'] . "</p>
    <h2>" . $_lang['global.item'] . " <em>" . $query['title'] . "</em></h2><br />
    " . (!empty($content_array) ? "<p>" . $_lang['admin.content.delete.contentlist'] . ":</p>" . _eventList($content_array) . "<div class='hr'><hr /></div>" : '') . "

    <form class='cform' action='index.php?p=content-delete&amp;id=" . $id . "' method='post'>
    <input type='hidden' name='confirm' value='1' />
    <input type='submit' value='" . $_lang['admin.content.delete.confirm'] . "' />
    " . _xsrfProtect() . "</form>
    ";

} else {
    $output .= _formMessage(3, $_lang['global.badinput']);
}
