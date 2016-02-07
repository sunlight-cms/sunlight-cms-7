<?php
/* ---  inicializace jadra  --- */
require '../../require/load.php';
SL::init('../../');

/* ---  vystup  --- */
if (_loginright_level != 10001) {
    exit;
}
require _indexroot . "require/headstart.php";
require _indexroot . "admin/functions.php";

?>
<link href="style.css.php?s=<?php echo _adminscheme . (_admin_schemeIsDark() ? '&amp;d' : '') . '&amp;' . _cacheid; ?>" type="text/css" rel="stylesheet" />
<title><?php echo $_lang['admin.other.php.title']; ?></title>
</head>

<body>
<div id="external-container">

<?php

// nacteni postdat
$process = false;
if (isset($_POST['code'])) {
    $code = $_POST['code'];
    if (_xsrfCheck()) $process = true;
}

?>

<h1><?php echo $_lang['admin.other.php.title']; ?></h1>

<form action="php.php" method="post">
<textarea name="code" rows="25" cols="94" class="areabig"><?php if (isset($code)) echo _htmlStr($code); ?></textarea><br />
<input type="submit" value="<?php echo $_lang['global.do']; ?>" /> &nbsp;<label><input type="checkbox" name="html" value="1"<?php echo _checkboxActivate(isset($_POST['html']) ? 1 : 0); ?> /> <?php echo $_lang['admin.other.php.html']; ?></label>
<?php echo _xsrfProtect(); ?>
</form>

<?php

if ($process) {
    $html = isset($_POST['html']);
    echo '<h2>' . $_lang['global.result'] . '</h2>';
    if (!$html) {
        echo '<br /><pre>';
        ob_start();
    } else echo '<br />';
    eval($code);
    if (!$html) {
        $data = _htmlStr(ob_get_contents());
        ob_end_clean();
        echo $data . '</pre>';
    }
}

?>

</div>
</body>
</html>
