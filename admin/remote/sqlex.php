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
<title><?php echo $_lang['admin.other.sqlex.title']; ?></title>
<script type="text/javascript">
//<![CDATA[
function _admin_tableName()
{
var tablename=prompt("<?php echo $_lang['admin.other.sqlex.tablename']; ?>:");
if (tablename!=null && tablename!="") {document.form.sql.value=document.form.sql.value+"`<?php echo _mysql_prefix ?>-"+tablename+"`";}
document.form.sql.focus();
return false;
}
//]]>
</script>
</head>

<body>
<div id="external-container">

<?php
// nacteni postdat
$process = false;
if (isset($_POST['sql'])) {
    $sql = $_POST['sql'];
    if (_xsrfCheck()) $process = true;
}

?>

<h1><?php echo $_lang['admin.other.sqlex.title']; ?></h1>

<form action="sqlex.php" method="post" name="form">
<textarea name="sql" rows="9" cols="33" class="areasmallwide"><?php if (isset($sql)) echo _htmlStr($sql); ?></textarea><br />
<input type="submit" value="<?php echo $_lang['admin.other.sqlex.run']; ?>" />&nbsp;&nbsp;<a href="#" onclick="return _admin_tableName();"><?php echo $_lang['admin.other.sqlex.tablename.hint']; ?></a>
<?php echo _xsrfProtect(); ?>
</form>

<?php
if ($process) {
    echo '<h2>' . $_lang['global.result'] . '</h2><br />';
    $query = DB::query($sql, true);
    if (DB::error() == null) {

        $fields = array();
        $aff_rows = DB::affectedRows();
        if (is_resource($query)) $num_rows = intval(DB::size($query));
        else $num_rows = 0;
        $heading = false;

        if ($num_rows != 0) {
            echo '<p><strong>' . $_lang['admin.other.sqlex.rows'] . ':</strong> ' . $num_rows . '</p>
<table class="list">' . "\n";
            while ($item = DB::row($query)) {

                // nacteni sloupcu, vytvoreni hlavicky tabulky
                if (!$heading) {

                    // sloupce
                    $load = false;
                    foreach($item as $field => $value) $fields[] = $field;

                    // hlavicka
                    $heading = true;
                    echo "<thead><tr>";
                    foreach ($fields as $field) {
                        echo "<td>" . $field . "</td>";
                    }
                    echo "</tr></thead>\n";

                }

                // radek vystupu
                echo "<tr class='valign-top'>";
                foreach ($fields as $field) {
                    if (mb_substr_count($item[$field], "\n") == 0) {
                        $content = _htmlStr($item[$field]);
                    } else {
                        $content = "<textarea rows='8' cols='80' readonly='readonly'>" . _htmlStr($item[$field]) . "</textarea>";
                    }
                    echo "<td>" . $content . "</td>";
                }
                echo "</tr>\n";

            }
            echo "</table>";
        } else {
            if ($aff_rows == 0) {
                echo "\n<p>" . $_lang['admin.other.sqlex.null'] . "</p>\n";
            } else {
                echo "\n<p><strong>" . $_lang['admin.other.sqlex.affected'] . ":</strong> " . $aff_rows . "</p>\n";
            }
        }

    } else {
        echo "<h3>" . $_lang['global.error'] . ":</h3>\n<pre>" . _htmlStr(DB::error()) . "</pre>";
    }
}

?>

</div>
</body>
</html>
