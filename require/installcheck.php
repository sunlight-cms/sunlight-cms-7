<?php
// kontrola jadra
if (!defined('_core')) exit;

// priprava
$errors = array();

// cesty - kontrola existence a moznosti zapisu
$check_dirs = array(
    'upload/',
    'pictures/',
    'pictures/articles/',
    'pictures/avatars/',
    'pictures/galleries/',
    'pictures/thumb/',
    'plugins/',
    'plugins/admin/',
    'plugins/extend/',
    'plugins/hcm/',
    'plugins/languages/',
    'plugins/templates/',
    'plugins/common/',
    'admin/modules/',
    'data/tmp/',
    'data/backup/',
    );

// kontrola verze php
if (!function_exists('version_compare') || version_compare('4.3.3', PHP_VERSION) === 1) {
    $errors[] = 'Používáte příliš starou verzi PHP (' . PHP_VERSION . ') - je vyžadována alespoň verze 4.3.3';
}

// kontrola cest
for ($i = 0; isset($check_dirs[$i]); ++$i) {
    $path = _indexroot . $check_dirs[$i];
    if (!@is_dir($path)) $errors[] = 'Adresář <em>/' . $check_dirs[$i] . '</em> neexistuje nebo není dostupný ke čtení';
    elseif (!@is_writeable($path)) $errors[] = 'Do adresáře <em>/' . $check_dirs[$i] . '</em> nelze zapisovat';
}

// kontrola existence adresare install a souboru patch.php
if (@is_dir(_indexroot . 'install') && !_dev) $errors[] = 'Adresář <em>install</em> se stále nachází na serveru - po instalaci je třeba jej odstranit';
if (file_exists(_indexroot . 'patch.php')) $errors[] = 'Soubor <em>patch.php</em> se stále nachází na serveru - po aktualizaci databáze je třeba jej odstranit';
if (file_exists(_indexroot . 'install.php') && !_dev) $errors[] = 'Soubor <em>install.php</em> se stále nachází na serveru - po instalaci je třeba jej odstranit';

// vyhodnoceni
if (empty($errors)) {
    // vse ok
    DB::query('UPDATE `' . _mysql_prefix . '-settings` SET `val`=\'0\' WHERE `var`=\'install_check\'');
} else {
    // detekovany chyby
    $errors_str = "<ul>\n";
    for($i = 0; isset($errors[$i]); ++$i) $errors_str .= '<li>' . $errors[$i] . '</li>' . _nl;
    $errors_str .= "</ul>";
    _systemFailure('Při kontrole instalace byly detekovány následující problémy:</p>' . $errors_str . '<p>Systém bude funkční až po jejich nápravě.');
}
