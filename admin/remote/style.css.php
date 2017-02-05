<?php

/* ----  priprava  ---- */
header("Content-Type: text/css; charset=UTF-8");
header("Expires: " . gmdate("D, d M Y H:i:s", time() + 86400) . " GMT");

/* ----  konfigurace  ---- */
$dark = isset($_GET['d']);
if (isset($_GET['s'])) $s = intval($_GET['s']);
else $s = 0;

/* ----  vypocet barev  ---- */

// rucni nacteni tridy (v tomto skriptu se nepouziva jadro SL)
require_once '../../require/class/color.php';

// funkce pro rychle vytvoreni barvy
function _admin_color($loff = 0, $satc = null, $sat_abs = false)
{
    // nacteni a uprava barev
    $h = $GLOBALS['hue'];
    if ($GLOBALS['dark']) $l = $GLOBALS['light'] - $loff;
    else $l = $GLOBALS['light'] + $loff;
    $s = (isset($satc) ? ($sat_abs ? $satc :  $GLOBALS['sat'] * $satc) : $GLOBALS['sat']);

    // vytvoreni hex kodu barvy
    $color = new Color(array($h, $l, $s), 1);

    return $color->getRGBStr();
}

// vychozi HLS hodnoty
$hue = 0;
$light = 127;
$sat = 255;

// vychozi barevne hodnoty
$scheme_link = null;
if ($dark) {
    $scheme_white = "#000";
    $scheme_black = "#fff";
} else {
    $scheme_white = "#fff";
    $scheme_black = "#000";
}
$scheme_menu = $scheme_black;
$scheme_text = $scheme_black;
if ($dark) {
    $scheme_contrast = $scheme_black;
    $scheme_contrast2 = $scheme_white;
} else {
    $scheme_contrast = $scheme_white;
    $scheme_contrast2 = $scheme_black;
}
$smoke_satc = 0.7;
$dark_flip = ($dark ? -1 : 1);

// uprava podle schematu
switch ($s) {

        // modry
    case 1:
        $hue = 145;
        $sat -= 10;
        break;

        // zeleny
    case 2:
        $hue = 70;
        $light -= 30 * $dark_flip;
        $sat -= 50;
        break;

        // cerveny
    case 3:
        $hue = 5;
        break;

        // zluty
    case 4:
        $hue = 35;
        $scheme_contrast = $scheme_black;
        $scheme_link = "#BE9B02";
        break;

        // purpurovy
    case 5:
        $hue = 205;
        break;

        // azurovy
    case 6:
        $hue = 128;
        $light -= 20 * $dark_flip;
        $sat -= 15;
        break;

        // fialovy
    case 7:
        $hue = 195;
        break;

        // hnedy
    case 8:
        $hue = 20;
        $light -= 10 * $dark_flip;
        $sat *= 0.5;
        $smoke_satc = 0.8;
        break;

        // tmave modry
    case 9:
        $hue = 180;
        $light -= 10 * $dark_flip;
        $sat *= 0.5;
        break;

        // sedy
    case 10:
        $hue = 150;
        $sat = 0;
        $scheme_link = "#67939F";
        break;

        // oranzovy
    default:
        $hue = 20;
        break;

}

// vypocet barev
$scheme_dark = _admin_color(($dark ? 90 : -10), null);
$scheme_lighter = _admin_color(80);
$scheme_lightest = _admin_color(100);
$scheme_smoke = _admin_color(115, $smoke_satc);
$scheme_smoke_text = _admin_color($light * 0.2, 0);
$scheme_smoke_gray = _admin_color(100, 0);
$scheme_smoke_gray_med = _admin_color(90, 0);
$scheme_smoke_gray_dark = _admin_color(60, 0);
$scheme = _admin_color(($dark ? 40 : 0));
if ($scheme_link == null) {
    $scheme_link = _admin_color(($dark ? -20 : 0), 255, true);
}
$scheme_bar = $dark ? $scheme : _admin_color(10);

?>
/* tagy */
* {margin: 0; padding: 0;}
body {padding: 1.5em 1em; font-family: sans-serif; font-size: 12px; color: <?php echo $scheme_text; ?>; background-color: <?php echo $scheme_smoke; ?>; margin: 0;}
a {font-size: 12px; color: <?php echo $scheme_link; ?>; text-decoration: none;}
a:hover {color: <?php echo $scheme_text; ?>; text-decoration: none;}
h1 {font-size: 18px;}
h2 {font-size: 14px;}
h3 {font-size: 12px;}
p {padding: 0; margin: 2px 0 10px 0; line-height: 160%;}
ul, ol {padding: 2px 0 12px 40px;}

form {margin: 0 0 8px 0;}
fieldset {margin: 25px 0; padding: 8px; background-color: <?php echo $scheme_smoke; ?>; border: 1px solid <?php echo $scheme_smoke_gray_dark; ?>;}
fieldset fieldset {background-color: <?php echo $scheme_white; ?>;}
legend {font-weight: bold; color: <?php echo $scheme_text; ?>;}
input, textarea {padding: 1px 0;}
input[type=text], input[type=password], input[type=submit], input[type=button], input[type=reset], button, select {padding: 3px;}
label input {margin: 3px 4px 3px 1px; padding: 0;}
optgroup option {padding-left: 16px;}

<?php if ($dark) { ?>
input, textarea, button, select {
    background-color: <?php echo $scheme_white; ?>;
    color: <?php echo $scheme_black; ?>;
    border: 1px solid <?php echo $scheme_smoke_gray_dark; ?>;
}
<?php } ?>

img {border: 0;}
small {color: <?php echo $scheme_smoke_text; ?>;}
td {font-size: 12px; padding: 1px;}

/* layout */
#wrapper {max-width: 1200px; min-width: 700px; margin: 0 auto; background-color: <?php echo $scheme_white ?>;}

/* hlavicka */
#header {font-family: Georgia, "Times New Roman", Times, serif; font-size: 24px; color: <?php echo $scheme_contrast; ?>; background-color: <?php echo $scheme_bar; ?>; padding: 10px;}
#usermenu {float: right; position: relative; top: 6px;}
#usermenu, #usermenu a {font-size: 14px; font-weight: bold; text-decoration: none; color: <?php echo $scheme_contrast; ?>;}
#usermenu a.usermenu-web-link {margin-left: 0.5em;}
#header-avatar {position: absolute; left: -34px; top: -8px; display: block; width: 24px; height: 32px; overflow: hidden; border: 1px solid <?php echo $scheme_dark; ?>; background-color: <?php echo $scheme_white; ?>;}
#header-avatar img {height: 32px;}
#header-avatar:hover img {opacity: 1;}

/* menu */
#menu {padding-left: 6px; background-color: <?php echo $scheme_lighter; ?>; color: <?php echo $scheme_contrast; ?>; font-size: 0;}
#menu a {color: <?php echo $scheme_menu; ?>; font-weight: bold; font-size: 12px; text-decoration: none; padding: 10px 10px; display: inline-block; border-right: 1px solid <?php echo $scheme_lightest ?>; background-color: <?php echo $scheme_lighter; ?>;}
#menu a:hover {background-color: <?php echo $scheme_lightest ?>;}
#menu a.act {background-color: <?php echo $scheme_white ?>;}

/* obsah */
#content {padding: 12px 16px 16px 16px;}

/* copyright */
#copyright {position: relative;z-index:101;text-align: right; padding: 8px 10px; background-color: <?php echo $scheme_bar; ?>;}
#copyright, #copyright * {color: <?php echo $scheme_contrast; ?>; font-size: 10px; text-decoration: none; font-weight: bold;}
#copyright a:hover {text-decoration: underline;}
#copyright div {float: left;}

/* ruzne */
#slhook {width: 55px; height: 13px; padding: 0; margin: -3px 0 0 0;}
#external-container {padding: 10px;}
#external-container h1 {border-bottom: 3px solid <?php echo $scheme; ?>; padding-bottom: 3px; margin-bottom: 6px;}

  /* uvodni strana */
  #indextable {width: 100%; margin: 0; padding: 0; border-collapse: collapse;}
  #indextable td {padding: 10px; border: 1px solid <?php echo $scheme_smoke_gray_med; ?>; background-color: <?php echo $scheme_smoke; ?>;}
  #indextable h2 {margin-bottom: 6px; border-bottom: 1px solid <?php echo $scheme_smoke_gray; ?>; padding-bottom: 6px;}
  #indextable li {padding: 3px;}
  #news-box h2 {font-size: 18px; border-bottom: 1px solid <?php echo $scheme_smoke_gray; ?>; margin-bottom: 12px; padding-bottom: 6px;}
  #news h3 {font-size: 16px; color: <?php echo $scheme_link; ?>;}
  #news p {margin-bottom: 3px;}
  #news div {margin-bottom: 15px; color: <?php echo $scheme_smoke_text; ?>;}
  #index_custom_iframe {width: 100%; min-height: 150px;}

  /* sprava obsahu */
  #contenttable {width: 100%; border: 1px solid <?php echo $scheme_smoke_gray; ?>; line-height: 140%;}
  #contenttable a {text-decoration: none;}
  #contenttable h2 {margin: 0 0 8px 0; padding: 4px 0 7px 0; border-bottom: 1px solid <?php echo $scheme_smoke_gray; ?>;}
  #contenttable div.pad {padding: 20px 0;}
  .contenttable-box {padding: 8px; margin: 0; border-right: 1px solid <?php echo $scheme_smoke_gray; ?>;}
  .contenttable-icon {margin-right: 5px; position: relative; top: 3px;}
  #contenttable-list {width: 100%; margin-left: 4px;}
  #contenttable-list tr {vertical-align: top;}
  #contenttable-list tr:hover td {background-color: <?php echo $scheme_lightest; ?>;}
  #contenttable-list td {padding: 0;}
  #contenttable-list .name {width: 60%;}
  #contenttable-list .name a {font-weight: bold;}
  #contenttable-list .name a:hover {color: <?php echo $scheme_link; ?>;}
  #contenttable-list .name input {width: 30px; margin-right: 10px; position: relative; top: 2px; left: 2px;}
  #contenttable-list .type {width: 15%;}
  #contenttable-list .actions {width: 25%; white-space: nowrap;}
  #contenttable-list .actions .tpad, #contenttable-list .type .tpad {padding: 4px;}
  #contenttable-list .intersecpad .name, #contenttable-list .intersecpad-hl .name {padding-left: 30px;}
  #contenttable-list .sep {padding-top: 32px;}
  #contenttable-list .sep .sepbg {background-color: <?php echo $scheme_lighter; ?>; height: 26px;}
  #contenttable-list .sep a {color: <?php echo $scheme_text; ?>;}
  #contenttable-list .index_page a {text-decoration: underline;}

  /* uprava clanku */
  #ae-table {width: 99.1%; border-collapse: collapse; float: left;}
  #ae-table, #ae-table td {margin: 0; padding: 0;}
  #content-cell {width: 75%;}
  #content-cell textarea {width: 99%; height: 480px;}
  #is-cell {width: 25%;}
  #is-cell textarea {width: 213px; height: 179px;}
  #is-cell label {display: block;}
  #is-cell label input {margin: 0;}
  #is-cell-wrapper {position: relative;}
  #is-cell-content {padding: 0 5px; position: absolute; left: 0; top: 0; width: 100%;}
  #is-picture {margin: 10px 0; width: 100%; padding: 5px 0 40px 0; overflow: hidden; position: relative;}
  #is-picture-file {display: block; max-width: 200px; max-height: 200px; margin: 0 auto; border: 1px solid <?php echo $scheme_lighter; ?>;}
  #is-picture-upload {position: absolute; left: 10px; bottom: 10px;}
  #is-picture-delete {position: absolute; right: 10px; top: 5px; padding: 3px;}
  #is-picture-delete img, #is-picture-delete input {vertical-align: middle;}
  #ae-lastrow {padding-bottom: 180px;}
  #infobox-wrapper {width: 213px;}
  #time-cell {z-index: 1; position: relative;}
  .ae-artselect {width: 249px;}
  .ae-artselect-disoption {color: <?php echo $scheme_smoke_text; ?>;}
  .ae-twoi {border-collapse: collapse; width: 765px !important;}
  .ae-twoi input, .ae-twoi select {width: 100% !important;}
  .ae-twoi td {padding-left: 0 !important; padding-right: 16px !important; width: 330px;}
  .ae-twoi td.rpad {width: 100px; white-space: nowrap;}

  /* uprava boxu */
  #boxesedit {width: 100%;}
  #boxesedit td.cell {padding: 10px 20px 25px 10px;}
  #boxesedit td.cell > div {border: 1px solid <?php echo $scheme_smoke_gray; ?>; padding: 20px 15px;}

  /* souborovy manazer */
  #fman-action {border-bottom: 1px solid <?php echo $scheme_smoke_gray; ?>; margin-bottom: 10px;}
  #fman-action h2 {margin-bottom: 6px;}
  #fman-list {min-width: 700px; margin-bottom: 6px;}
  #fman-list.mini {min-width: 0;}
  #fman-list a {color: <?php echo $scheme_text; ?>;}
  #fman-list a:hover {color: <?php echo $scheme_link; ?>;}
  #fman-list .actions, #fman-list .actions a {font-size: 10px;}
  #fman-list td {padding: 2px 4px;}
  #fman-list tr:hover td {background-color: <?php echo $scheme_lighter; ?>;}
  #fman-list input {margin-right: 2px;}
  #fman-list tr.fman-uploaded td {background: <?php echo $scheme_lighter; ?>;}
  .fman-menu {border-width: 1px 0 1px 0; border-style: solid; border-color: <?php echo $scheme_smoke_gray; ?>;}
  .fman-menu, .fman-menu2 {margin-top: 5px; padding: 5px;}
  .fman-menu a, .fman-menu span, .fman-menu2 a, .fman-menu2 span {border-right: 1px solid <?php echo $scheme_smoke_gray; ?>; padding-right: 8px; margin-right: 8px;}

  /* galerie */
  .gallery-savebutton {float: left; margin: 4px 14px 0 0; display: block;}
  #gallery-browser {background-color: <?php echo $scheme_white; ?>; border: 1px solid <?php echo $scheme_smoke_text; ?>; height: 150px; width: 460px; padding: 10px; margin-left: 10px; overflow: auto;}
  #gallery-browser #gallery-browser-actdir {padding-bottom: 3px; margin-bottom: 2px; border-bottom: 1px solid <?php echo $scheme_smoke_gray; ?>;}
  #gallery-browser #fman-list {width: 443px;}
  #gallery-browser a {color: <?php echo $scheme_text; ?>;}
  #gallery-browser td.noimage a {color: <?php echo $scheme_smoke_text; ?>;}
  #gallery-browser-dialog {width: 350px; height: 48px; background-color: <?php echo $scheme_smoke; ?>; border: 2px solid <?php echo $scheme; ?>; position: absolute;}
  #gallery-browser-dialog div {width: 338px; height: 33px; border: 2px solid <?php echo $scheme_smoke; ?>; padding: 11px 0 0 8px; font-size: 16px;}
  #gallery-browser-dialog div span {font-weight: bold; padding-right: 4px;}
  #gallery-browser-dialog div a {font-size: 16px; padding: 2px 5px; margin: 0 4px; border: 2px outset <?php echo $scheme_link; ?>; background-color: <?php echo $scheme_lighter; ?>; color: <?php echo $scheme_text; ?>;}
  #gallery-browser-dialog div a:active {border-style: inset;}
  #gallery-edittable {border-collapse: collapse; margin: 14px 0; background-color: <?php echo $scheme_lightest; ?>;}
  .gallery-edittable-td {border: 1px solid <?php echo $scheme_contrast; ?>; vertical-align: top; padding: 20px;}
  .gallery-edittable-td a {color: <?php echo $scheme_black; ?>;}
  #gallery-edittable a.lightbox img {border: 1px solid <?php echo $scheme_smoke_text; ?>;}
  #gallery-insertform-cell {}

  /* nastaveni */
  #settingsnav {width: 20%; float: left; margin-right: 1em;}
  #settingsnav, #settingsnav a {font-size: 12px;}
  #settingsnav div.scroll-fix {position: fixed; top: 10px; z-index: 100;}
  #settingsnav input {width: 100%; padding: 0.5em;}
  #settingsnav ul {padding: 0; margin: 0.5em 0 0 0; border: 1px solid <?php echo $scheme_smoke_gray; ?>; background-color: <?php echo $scheme_lighter; ?>;}
  #settingsnav li {display: block; list-style-type: none;}
  #settingsnav li a {display: block; padding: 11px; border-bottom: 1px solid <?php echo $scheme_lightest;?>; font-weight: bold; color: <?php echo $scheme_text; ?>;}
  #settingsnav li.active a {background-color: <?php echo $scheme; ?>; color: <?php  echo $scheme_white; ?>;}

  #settingsform {float: left; padding-bottom: 30em; width: 78%;}
  #settingsform fieldset {margin: 0 0 5em 0;}
  #settingsform table {border-collapse: collapse;}
  #settingsform table td {padding: 4px 8px; border: 1px solid <?php echo $scheme_smoke_gray_med; ?>;}
  #settingsform table td:first-child {white-space: nowrap;}
  #settingsform table td.rpad {padding-right: 8px; padding-left: 4px;}

  /* codemirror */
  div.CodeMirror {
    cursor: text;
    background-color: #fff;
    <?php if ($dark): ?>
    border: 1px solid <?php echo $scheme_smoke_gray_dark ?>;
    <?php else: ?>
    outline: 1px solid <?php echo $scheme_white ?>;
    border-width: 2px;
    border-style: solid;
    border-color: <?php echo $scheme_smoke_gray_dark ?> <?php echo $scheme_smoke_gray ?> <?php echo $scheme_smoke_gray ?> <?php echo $scheme_smoke_gray_dark ?>;
    <?php endif ?>
  }
  div.CodeMirror span.cm-hcm {color: <?php echo $dark ? '#ff0' : '#f60' ?>;}

/* tridy */
a.normal {color: <?php echo $scheme_text; ?>;}
a.invisible {color: <?php echo $scheme_smoke_text; ?>;}
a.notpublic {font-style: italic; color: <?php echo $scheme_text; ?>;}
a.invisible-notpublic {color: <?php echo $scheme_smoke_text; ?>; font-style: italic;}
.intersecpad-hl, .hl {background-color: <?php echo $scheme_smoke; ?>;}

.message1, .message2, .message3 {margin: 5px 0 20px 0; padding: 13px 5px 13px 48px; border: 1px solid <?php echo $scheme_smoke_gray; ?>; font-weight: bold; background-color: <?php echo $scheme_smoke; ?>; background-position: 5px 5px; background-repeat: no-repeat;}
.message1 ul, .message2 ul, .message3 ul {margin: 0; padding: 5px 0 0 15px;}
.message1 {background-image: url("../images/icons/info.png");}
.message2 {background-image: url("../images/icons/warning.png");}
.message3 {background-image: url("../images/icons/error.png");}

.formtable, .formbox {border: 1px dotted <?php echo $scheme_smoke_gray; ?>; background-color: <?php echo $scheme_smoke; ?>;}
.formtable td {padding: 2px 4px;}
.formbox {padding: 5px;}

.text-red {color: #E71717;}
.text-green {color: #080;}
.text-orange {color: #FE7F00;}

.cform table {width: 100%;}
.arealine {width: 99%; height: 100px;}
.areasmall {width: 290px; height: 150px;}
.areasmall_100pwidth {width: 100%; height: 200px;}
.areasmallwide {width: 620px; height: 150px;}
.areamedium {width: 600px; height: 350px;}
.areabig {width: 99%; height: 400px;}
.areabigperex {width: 99%; height: 150px;}
.inputmicro {width: 18px;}
.inputmini {width: 32px;}
.inputsmaller {width: 80px;}
.inputsmall {width: 145px;}
.inputmedium {width: 290px;}
.inputbig {width: 750px;}
.selectmedium {width: 294px;}
.selectbig {width: 753px;}

.hr {height: 10px; background-image: url("../images/hr<?php if ($dark) echo '_dark'; ?>.gif"); background-position: left center; background-repeat: repeat-x;}
.hr hr {display: none;}

.paging {padding: 6px 0 3px 1px;}
.paging span a {padding: 0 2px;}
.paging a.act {text-decoration: underline;}

.wintable, .listable {border: 1px solid <?php echo $scheme_smoke_gray; ?>;}
.wintable {width: 100%;}
.listable {min-width: 400px;}
.wintable td, .listable td {padding: 5px 15px;}
.wintable td.lpad, .listable td.lpad {padding: 5px 15px 5px 32px;}
.wintable td.rbor, .listable td.rbor {border-right: 1px solid <?php echo $scheme_smoke_gray; ?>;}
.wintable h2, .listable h2 {margin-bottom: 10px; padding-bottom: 5px; border-bottom: 1px solid <?php echo $scheme_smoke_gray; ?>;}
.listable thead td {font-weight: bold;}

.list {border-collapse: collapse;}
.list thead td {font-weight: bold; background-color: <?php echo $scheme_smoke; ?>;}
.list td {padding: 5px 32px 5px 5px; border: 1px solid <?php echo $scheme_smoke_gray; ?>;}
.list tbody tr:hover td {background-color: <?php echo $scheme_lightest; ?>;}
fieldset .list thead td {background-color: <?php echo $scheme_smoke_gray_med; ?>;}
fieldset .list td {border-color: <?php echo $scheme_smoke_gray_dark; ?>;}

.ex-list li {list-style-image: url("../images/icons/action.png"); padding: 5px 10px;}
.ex-list a {font-weight: bold; font-size: 13px;}

.bborder {padding-bottom: 8px; margin-bottom: 12px; border-bottom: 1px solid <?php echo $scheme_smoke_gray; ?>;}
fieldset .bborder {border-color: <?php echo $scheme_smoke_text; ?>;}
fieldset fieldset .bborder {border-color: <?php echo $scheme_smoke_gray; ?>;}
.customsettings {border-left: 1px solid <?php echo $scheme_smoke_gray; ?>; padding-left: 8px;}
.customsettings strong, .customsettings span {border-left: 1px solid <?php echo $scheme_white; ?>;}
.backlink {display: block; font-weight: bold; padding-bottom: 10px;}
.icon {margin: -1px 5px 0 0; vertical-align: middle;}
.groupicon {vertical-align: middle; margin-top: -1px;}
.rpad {padding-right: 10px;}
.lpad {padding-left: 10px;}
.inline {display: inline;}
.hidden {display: none;}
.cleaner {clear: both;}
.micon {height: 15px; margin: 0 1px;}
.intersecpad {padding-left: 20px;}
.litem {font-weight: bold;}
.special {color: <?php echo $scheme_link; ?>;}
.small {font-size: 10px;}
.block {display: block;}
.note {color: <?php echo $scheme_smoke_text; ?>;}
.minwidth {min-width: 700px;}
.important {color: red;}
.highlight {color: <?php echo $scheme_lightest; ?>;}
.big, .big * {font-size: 17px;}
tr.valign-top td {vertical-align: top;}
