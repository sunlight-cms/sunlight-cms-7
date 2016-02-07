<?php
/* ---  incializace jadra  --- */
require '../../require/load.php';
SL::init('../../');

/* ---  hlasovani  --- */

// nacteni promennych
if (isset($_POST['pid']) and isset($_POST['option']) and _xsrfCheck()) {
    $pid = intval($_POST['pid']);
    $option = intval($_POST['option']);

    // ulozeni hlasu
    $query = DB::query("SELECT locked,answers,votes FROM `" . _mysql_prefix . "-polls` WHERE id=" . $pid);
    if (DB::size($query) != 0) {
        $query = DB::row($query);
        $answers = explode("#", $query['answers']);
        $votes = explode("-", $query['votes']);
        if (_loginright_pollvote and $query['locked'] == 0 and _iplogCheck(4, $pid) and isset($votes[$option])) {
            $votes[$option] += 1;
            $votes = implode("-", $votes);
            DB::query("UPDATE `" . _mysql_prefix . "-polls` SET votes='" . $votes . "' WHERE id=" . $pid);
            _iplogUpdate(4, $pid);
        }
    }

}

// presmerovani
_returnHeader();
