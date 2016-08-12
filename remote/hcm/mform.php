<?php
/* ---  incializace jadra  --- */
require '../../require/load.php';
define('_header', 'Content-type: text/plain; charset=UTF-8');
SL::init('../../');

/* ---  send  --- */

// nacteni promennych
$subject = $_POST['subject'];
$sender = $_POST['sender'];
$text = $_POST['text'];
$fid = intval($_POST['fid']);

// nacteni prijemce
$skey = _sessionprefix . 'hcm_' . $fid . '_mail_receiver';
if (isset($_SESSION[$skey])) {
    $receiver = $_SESSION[$skey];
    unset($_SESSION[$skey], $skey);
} else {
    die($_lang['global.badinput']);
}

// casove omezeni
if (_iplogCheck(5)) _iplogUpdate(5); // zaznamenat
else {
    // prekroceno
    echo str_replace('*postsendexpire*', _postsendexpire, $_lang['misc.requestlimit']);
    die;
}

// odeslani
if (_xsrfCheck()) {
    if (_validateEmail($sender) and $text != "" and _captchaCheck()) {

        // pridani informacniho textu do tela
        $info_ip = _userip;
        if (_loginindicator) {
            $info_ip .= ' (' . _loginname . ')';
        }
        $info_from = array("*domain*", "*time*", "*ip*", "*sender*");
        $info_to = array(_getDomain(), _formatTime(time()), $info_ip, $sender);
        $text .= "\n\n" . str_repeat("-", 16) . "\n" . str_replace($info_from, $info_to, $_lang['hcm.mailform.info']);

        // prilozeni souboru
        if (isset($_FILES['att']['tmp_name']) and is_uploaded_file($_FILES['att']['tmp_name'])) {
            $att = true;
            $att_name = $_FILES['att']['name'];
            $att_tmpname = $_FILES['att']['tmp_name'];
            $att_content = @file_get_contents($att_tmpname);
            $att_content = @chunk_split(@base64_encode($att_content));
        } else {
            $att = false;
        }

        // sestaveni emailu

        // hlavicka, kodovani, odesilatel, boundary

        // odesilatel
        if (!_mailerusefrom) {
            $headers = "Reply-To: " . $sender . "\n";
        } else {
            $headers = "From: " . mb_substr($sender, 0, mb_strpos($sender, "@")) . " <" . $sender . ">\n";
        }

        $headers .= "MIME-Version: 1.0\n";
        $headers .= "Content-Type: multipart/mixed;\n";
        $headers .= " boundary=\"==Multipart_Boundary_x000x\"";

        // text
        $body = "This is a multi-part message in MIME format.\n\n";
        $body .= "--==Multipart_Boundary_x000x\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\n";
        $body .= "Content-Transfer-Encoding: 7bit\n\n";
        $body .= "\n$text\n";

        // priloha
        if ($att == true) {
            $body .= "--==Multipart_Boundary_x000x\n";
            $body .= "Content-Type: {application/octet-stream};\n";
            $body .= " name=\"{$att_name}\"\n";
            $body .= "Content-Transfer-Encoding: base64\n\n";
            $body .= $att_content . "\n\n";
        }

        // odeslani
        if (_mail($receiver, $subject, $body, $headers)) {
            $return = 1;
        } else {
            $return = 3;
        }

    } else {
        $return = 2;
    }
} else $return = 4;

// presmerovani zpet
$_GET['_return'] = _addGetToLink($_GET['_return'], "hcm_mr_" . $fid . "=" . $return . "#hcm_mform_" . $fid, false);
_returnHeader();
