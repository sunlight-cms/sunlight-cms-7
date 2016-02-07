<?php
/* ---  inicializace jadra  --- */
require '../../require/load.php';
require_once '../../admin/functions-backup.php';
define('_header', '');
SL::init('../../');

// podminka spusteni
if (!_loginright_adminbackup || !_xsrfCheck()) exit;

// nacteni parametru
_checkKeys('_POST', array('type', 'fname', 'compress'));
$type = intval($_POST['type']);
$fname = basename(trim($_POST['fname']));
if (empty($fname)) $fname = 'backup';
$compress = intval($_POST['compress']);
$extra_dirs = null;
if (in_array($type, array(_backup_partial, _backup_full)) && isset($_POST['dir_upload'])) $extra_dirs = array('upload');

// ulozeni na serveru?
if ($store = isset($_POST['target_store'])) {

    // uplnou zalohu nelze ulozit
    if ($type === _backup_full) die;

    // zpracovat nazev souboru a otevrit
    $type_ext = _backupExt($type);
    if ($type_ext === false) die;
    $fname = $fname . '_' . uniqid('', false) . '.' . $type_ext;
    $fname = fopen(_indexroot . 'data/backup/' . $fname, 'wb');

    // html hlavicka
    header('Content-Type: text/html; charset=UTF-8');
    require _indexroot . 'require/headstart.php';

    ?>
<title><?php echo $_lang['admin.other.backup.backup'] ?></title>
</head>
<?php

} else $store = false;

// vytvoreni zalohy
_backupCreate($type, $compress, $fname, (empty($_POST['note']) ? null : $_POST['note']), $extra_dirs);

// zprava?
if ($store) {

    ?>

<body>

<p><?php echo $_lang['admin.other.backup.backup.ok'] ?></p>

</body>
</html>

    <?php

}
