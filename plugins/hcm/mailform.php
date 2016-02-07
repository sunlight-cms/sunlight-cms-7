<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  definice funkce modulu  --- */
function _HCM_mailform($adresa = "", $priloha = false, $predmet = null)
{
    // priprava
    $result = "";
    $_SESSION[_sessionprefix . 'hcm_' . SL::$hcmUid . '_mail_receiver'] = @implode(",", _arrayRemoveValue(@explode(";", trim($adresa)), ""));
    if (_boolean($priloha)) {
        $rfile = array($GLOBALS['_lang']['hcm.mailform.att'], "<input type='file' name='att' />");
        $att = true;
    } else {
        $rfile = array('');
        $att = false;
    }
    if (isset($predmet)) {
        $rsubject = " value='" . _htmlStr($predmet) . "'";
    } else {
        $rsubject = "";
    }
    $rcaptcha = _captchaInit();

    // zprava
    $msg = '';
    if (isset($_GET['hcm_mr_' . SL::$hcmUid])) {
        switch ($_GET['hcm_mr_' . SL::$hcmUid]) {
            case 1:
                $msg = _formMessage(1, $GLOBALS['_lang']['hcm.mailform.msg.done']);
                break;
            case 2:
                $msg = _formMessage(2, $GLOBALS['_lang']['hcm.mailform.msg.failure']);
                break;
            case 3:
                $msg = _formMessage(3, $GLOBALS['_lang']['hcm.mailform.msg.failure2']);
                break;
            case 4:
                $msg = _formMessage(3, $GLOBALS['_lang']['xsrf.msg']);
                break;
        }
    }

    // predvyplneni odesilatele
    if (_loginindicator) {
        $sender = _loginemail;
    } else {
        $sender = "&#64;";
    }

    $result .= "<div class='anchor'><a name='hcm_mform_" . SL::$hcmUid . "'></a></div>\n"
        . $msg
        . _formOutput(
            "mform" . SL::$hcmUid,
            _indexroot . "remote/hcm/mform.php?_return=" . urlencode(_indexOutput_url) . ($att ? "' enctype='multipart/form-data" : ''),
            array(
                array($GLOBALS['_lang']['hcm.mailform.sender'], "<input type='text' class='inputsmall' name='sender' value='" . $sender . "' /><input type='hidden' name='fid' value='" . SL::$hcmUid . "' />"),
                array($GLOBALS['_lang']['posts.subject'], "<input type='text' class='inputsmall' name='subject'" . $rsubject . " />"),
                $rcaptcha,
                array($GLOBALS['_lang']['hcm.mailform.text'], "<textarea class='areasmall' name='text' rows='9' cols='33'></textarea>", true),
                $rfile,
            ),
            array("text", "sender"),
            $GLOBALS['_lang']['hcm.mailform.send']
        )
    ;

    return $result;
}
