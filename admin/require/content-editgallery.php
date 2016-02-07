<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  nastaveni a vlozeni skriptu pro upravu stranky  --- */
$type = 5;
require 'require/sub/content-editscript-init.php';
if ($continue) {

    $custom_settings = "
  <input type='text' name='var1' value='" . $query['var1'] . "' class='inputmicro' /> " . $_lang['admin.content.form.imgsperrow'] . ",&nbsp;&nbsp;
  <input type='text' name='var2' value='" . $query['var2'] . "' class='inputmicro' /> " . $_lang['admin.content.form.imgsperpage'] . "
  </span>&nbsp;&nbsp;<span class='customsettings'>
  <input type='text' name='var4' value='" . $query['var4'] . "' class='inputmini' /> " . $_lang['admin.content.form.prevwidth'] . "&nbsp;&nbsp;
  <input type='text' name='var3' value='" . $query['var3'] . "' class='inputmini' /> " . $_lang['admin.content.form.prevheight'] . "
  ";

    $custom_array = array(array("var1", false, 2, false), array("var2", false, 2, false), array("var3", false, 2, false), array("var4", false, 2, false));

}
require 'require/sub/content-editscript.php';
