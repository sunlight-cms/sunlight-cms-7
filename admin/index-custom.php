<?php
/* ----  inicializace jadra  ---- */

chdir('../'); // nasimulovat skript v rootu
require './require/load.php';
SL::init('');

// kontrola pristupu a dat
if (!_loginright_administration || SL::$settings['admin_index_custom'] === '') {
    die;
}

// funkce motivu a administrace
require _indexroot . "require/functions-template.php";
require _indexroot . "admin/functions.php";

/* ----  vystup  ---- */

// konstanty
define('_indexOutput_url', _indexroot);
define('_indexOutput_pid', null);
define('_indexOutput_ptype', 'aindex');
define('_indexOutput_content', '');
define('_indexOutput_title', '');

// tmave schema?
$scheme_dark = _admin_schemeIsDark();

// html hlavicka
require 'require/headstart.php';
?><base href="./../" target="_blank" />
<link href="admin/remote/style.css.php?s=<?php echo _adminscheme . ($scheme_dark ? '&amp;d' : '') . '&amp;' . _cacheid; ?>" type="text/css" rel="stylesheet" />
<link href="admin/remote/style.custom.css?<?php echo _cacheid; ?>" type="text/css" rel="stylesheet" />
<script type="text/javascript">/* <![CDATA[ */var sl_indexroot = '';/* ]]> */</script>
<script type="text/javascript" src="<?php echo _indexroot; ?>remote/jscript.php?<?php echo _cacheid . '&amp;' . _language; ?>"></script>
<title>Admin custom index</title>
</head>

<body>

<div id="custom-wrapper">
<?php echo _parseHCM(SL::$settings['admin_index_custom']); ?>
</div>

</body>
</html>
