<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  odeslani  --- */
if (isset($_POST['text'])) {

    // nacteni promennych
    $text = $_POST['text'];
    $subject = $_POST['subject'];
    $sender = $_POST['sender'];
    if (isset($_POST['receivers'])) $receivers = (array) $_POST['receivers'];
    else $receivers = array();
    $ctype = $_POST['ctype'];
    $maillist = _checkboxLoad("maillist");

    // kontrola promennych
    $errors = array();
    if ($text == "" and !$maillist) $errors[] = $_lang['admin.other.massemail.notext'];
    if (count($receivers) == 0) $errors[] = $_lang['admin.other.massemail.noreceivers'];
    if ($subject == "" and !$maillist) $errors[] = $_lang['admin.other.massemail.nosubject'];
    if (!_validateEmail($sender) and !$maillist) $errors[] = $_lang['admin.other.massemail.badsender'];

    if (count($errors) == 0) {

        // sestaveni casti sql dotazu - 'where'
        $groups = _sqlWhereColumn("`group`", implode("-", $receivers));

        // hlavicky
        $headers = "Content-Type: " . (($ctype == 1) ? 'text/plain' : 'text/html') . "; charset=UTF-8\n" . (_mailerusefrom ? "From: " . mb_substr($sender, 0, mb_strpos($sender, "@")) . " <" . $sender . ">" : "Reply-To: " . $sender . "") . "\n";

        // nacteni prijemcu
        $query = DB::query("SELECT email FROM `" . _mysql_prefix . "-users` WHERE massemail=1 AND (" . $groups . ")");

        // odeslani nebo zobrazeni adres
        if (!$maillist) {

            // priprava
            $rec_buffer = array();
            $rec_buffer_size = 20;
            $rec_buffer_counter = 0;
            $item_counter = 0;
            $item_total = DB::size($query);

            // poznamka na konci zpravy
            $notice = str_replace('*domain*', _getDomain(), $_lang['admin.other.massemail.emailnotice']);
            if ($ctype == 1) $notice = "\n\n\n-------------------------------------\n" . $notice;
            else $notice = "<br><br><hr><p><small>" . _htmlStr($notice) . "</small></p>";
            $text .= $notice;

            // postupne odesilani po skupinach
            $done = 0;
            while ($item = DB::row($query)) {
                $rec_buffer[] = $item['email'];
                ++$rec_buffer_counter;
                ++$item_counter;
                if ($rec_buffer_counter === $rec_buffer_size || $item_counter === $item_total) {
                    // odeslani emailu
                    if (_mail('', $subject, $text, "Bcc: " . implode(",", $rec_buffer) . "\n" . $headers)) $done += sizeof($rec_buffer);
                    $rec_buffer = array();
                    $rec_buffer_counter = 0;
                }
            }

            // zprava
            if ($done != 0) $output .= _formMessage(1, str_replace(array("*done*", "*total*"), array($done, $item_total), $_lang['admin.other.massemail.send']));
            else $output .= _formMessage(2, $_lang['admin.other.massemail.noreceiversfound']);

        } else {

            // vypis emailu
            $emails_total = DB::size($query);
            if ($emails_total != 0) {

                $emails = '';
                $email_counter = 0;
                while ($item = DB::row($query)) {
                    ++$email_counter;
                    $emails .= $item['email'];
                    if ($email_counter !== $emails_total) $emails .= ',';
                }

                $output .= _formMessage(1, "<textarea class='areasmallwide' rows='9' cols='33' name='list'>" . $emails . "</textarea>");

            } else {
                $output .= _formMessage(2, $_lang['admin.other.massemail.noreceiversfound']);
            }

        }

    } else $output .= _formMessage(2, _eventList($errors, 'errors'));

}

/* ---  vystup  --- */

$output .= "
<br />
<form class='cform' action='index.php?p=other-massemail' method='post'>
<table class='formtable'>

<tr>
<td class='rpad'><strong>" . $_lang['admin.other.massemail.sender'] . "</strong></td>
<td><input type='text' name='sender'" . _restorePostValue("sender", _sysmail) . " class='inputbig' /></td>
</tr>

<tr>
<td class='rpad'><strong>" . $_lang['posts.subject'] . "</strong></td>
<td><input type='text' name='subject' class='inputbig'" . _restorePostValue("subject") . " /></td>
</tr>

<tr class='valign-top'>
<td class='rpad'><strong>" . $_lang['admin.other.massemail.receivers'] . "</strong></td>
<td>" . _admin_authorSelect("receivers", -1, "1", "selectbig", null, true, 4) . "</td>
</tr>

<tr>
<td class='rpad'><strong>" . $_lang['admin.other.massemail.ctype'] . "</strong></td>
<td>
  <select name='ctype' class='selectbig'>
  <option value='1'>" . $_lang['admin.other.massemail.ctype.1'] . "</option>
  <option value='2'" . ((isset($_POST['ctype']) and $_POST['ctype'] == 2) ? " selected='selected'" : '') . ">" . $_lang['admin.other.massemail.ctype.2'] . "</option>
  </select>
</td>
</tr>

<tr class='valign-top'>
<td class='rpad'><strong>" . $_lang['admin.other.massemail.text'] . "</strong></td>
<td><textarea name='text' class='areabig' rows='9' cols='94'>" . _restorePostValue("text", null, true) . "</textarea></td>
</tr>

<tr><td></td>
<td><input type='submit' value='" . $_lang['global.send'] . "' />&nbsp;&nbsp;<label><input type='checkbox' name='maillist' value='1'" . _checkboxActivate(_checkboxLoad("maillist")) . " /> " . $_lang['admin.other.massemail.maillist'] . "</label></td>
</tr>

</table>
" . _xsrfProtect() . "</form>
";
