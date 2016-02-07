<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  definice funkce modulu  --- */
function _HCM_sbox($id = null)
{
    // priprava
    $result = "";
    $id = intval($id);

    // nacteni dat shoutboxu
    $sboxdata = DB::query("SELECT * FROM `" . _mysql_prefix . "-sboxes` WHERE id=" . $id);
    if (DB::size($sboxdata) != 0) {
        $sboxdata = DB::row($sboxdata);
        $rcontinue = true;
    } else {
        $rcontinue = false;
    }

    // sestaveni kodu
    if ($rcontinue) {

        $result = "
    <div class='anchor'><a name='hcm_sbox_" . SL::$hcmUid . "'></a></div>
    <div class='sbox'>
    <div class='sbox-content'>
    " . (($sboxdata['title'] != "") ? "<div class='sbox-title'>" . $sboxdata['title'] . "</div>" : '') . "<div class='sbox-item'" . (($sboxdata['title'] == "") ? " style='border-top:none;'" : '') . ">";

        // formular na pridani
        if ($sboxdata['locked'] != 1 and _publicAccess($sboxdata['public'])) {

            // priprava bunek
            // $captcha = _captchaInit();
            if (!_loginindicator) {
                $inputs[] = array($GLOBALS['_lang']['posts.guestname'], "<input type='text' name='guest' class='sbox-input' maxlength='22' />");
            }
            $inputs[] = array($GLOBALS['_lang']['posts.text'], "<input type='text' name='text' class='sbox-input' maxlength='255' /><input type='hidden' name='_posttype' value='4' /><input type='hidden' name='_posttarget' value='" . $id . "' />");
            if (!_loginindicator) {
                $inputs[1][2] = true;
                // $inputs[] = $captcha;
            }

            $result .= _formOutput("hcm_sboxform_" . SL::$hcmUid, _indexroot . "remote/post.php?_return=" . urlencode(_indexOutput_url . "#hcm_sbox_" . SL::$hcmUid), $inputs, null, null);

        } else {
            if ($sboxdata['locked'] != 1) {
                $result .= $GLOBALS['_lang']['posts.loginrequired'];
            } else {
                $result .= "<img src='" . _templateImage("icons/lock.png") . "' alt='locked' class='icon' /> " . $GLOBALS['_lang']['posts.locked2'];
            }
        }

        $result .= "\n</div>\n<div class='sbox-posts'>";
        // vypis prispevku
        $sposts = DB::query("SELECT id,text,author,guest,time,ip FROM `" . _mysql_prefix . "-posts` WHERE home=" . $id . " AND type=4 ORDER BY id DESC");
        if (DB::size($sposts) != 0) {
            while ($spost = DB::row($sposts)) {

                // nacteni autora
                if ($spost['author'] != -1) {
                    $author = _linkUser($spost['author'], "post-author' title='" . _formatTime($spost['time']), false, false, 16, ":");
                } else {
                    $author = "<span class='post-author-guest' title='" . _formatTime($spost['time']) . ", ip=" . _showIP($spost['ip']) . "'>" . $spost['guest'] . ":</span>";
                }

                // odkaz na spravu
                if (_postAccess($spost)) {
                    $alink = " <a href='index.php?m=editpost&amp;id=" . $spost['id'] . "'><img src='" . _templateImage("icons/edit.png") . "' alt='edit' class='icon' /></a>";
                } else {
                    $alink = "";
                }

                // kod polozky
                $result .= "<div class='sbox-item'>" . $author . $alink . " " . _parsePost($spost['text'], true, false, false) . "</div>\n";

            }
        } else {
            $result .= "\n<div class='sbox-item'>" . $GLOBALS['_lang']['posts.noposts'] . "</div>\n";
        }

        $result .= "
  </div>
  </div>
  </div>
  ";

    }

    return $result;
}
