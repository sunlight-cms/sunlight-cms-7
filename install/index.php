<?php
/* --  inicializace jadra  -- */
require '../require/load.php';
SL::init('../', null, true, true);

$msg = "";

/* --  instalace databaze  -- */
if (isset($_POST['license'])) {

    // nacteni promennych
    $url = _removeSlashesFromEnd($_POST['url']);
    $pass = $_POST['pass'];
    $pass2 = $_POST['pass2'];
    $email = $_POST['email'];
    $rewrite = _checkboxLoad("rewrite");
    $title = DB::esc(_htmlStr($_POST['title']));
    $descr = DB::esc(_htmlStr($_POST['descr']));
    $keywords = DB::esc(_htmlStr($_POST['kwrds']));

    // kontrola promennych
    $errors = array();
    if ($url == "" or $url == "http://") $errors[] = "Nebyla zadána adresa serveru.";
    if ($pass == "" or $pass2 == "") $errors[] = "Nebylo vyplněno heslo.";
    if ($pass != $pass2) $errors[] = "Zadaná hesla nejsou shodná.";
    if (!_validateEmail($email)) $errors[] = "E-mailová adresa není platná.";

    // instalace
    if (count($errors) == 0) {

        // smazani existujicich tabulek
        if ($rewrite) {
            $tables = array('articles', 'boxes', 'groups', 'images', 'iplog', 'pm', 'polls', 'posts', 'root', 'sboxes', 'settings', 'users', 'user-activation', 'redir');
            foreach($tables as $table) DB::query("DROP TABLE IF EXISTS `" . _mysql_prefix . "-" . $table . "`");
        }

        // vypnuti auto_incrementu pri nulovych hodnotach
        DB::query('SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO"');

        // vytvoreni nove struktury
        $pass = _md5Salt($pass);
        $url = DB::esc(_htmlStr($url));
        $email = DB::esc($email);
        require 'data.php';

        // zprava
        if ($sql_error == false) $msg = "<span style='color:green;'>Zdá se, že databáze byla úspěšně nainstalována. Smažte adresář <em>install</em> ze serveru.</span>";
        else $msg = "Během vyhodnocování SQL dotazů nastala chyba:<hr />" . _htmlStr($sql_error) . '<hr />SQL dotaz:<br />' . _htmlStr($line);

    } else {
        $msg = _eventList($errors);
    }

}

/* --  zprava  -- */
if ($msg != "") {
    $msg = "<div id='message'><div>" . $msg . "</div></div><br />";
}

// vlozeni zacatku hlavicky
require '../require/headstart.php';

?>
<title>Instalace databáze SunLight CMS <?php echo _systemversion; ?></title>

<style type="text/css">
body {font-family: Verdana, Helvetica, sans-serif; font-size: 13px; color: #000000; background-color: #ff6600; text-align: center; margin: 10px 0px 10px 0px;}
p,ol,ul {line-height: 24px;}
a {color: #ff6600}
h1 {font-size: 22px; margin: 0px 0px 15px 0px; padding: 0px 0px 8px 0px; border-bottom: 3px solid #ff6600;}
h2 {font-size: 17px;}
td {padding-right: 10px;}
#container {width: 980px; margin: 0 auto; text-align: left;}
#container div {padding: 10px 10px 25px 10px; background-color: #fff8f5; border: 1px solid #ff9859;}
#message {width: 980px; margin: 0 auto; text-align: left;}
#message div {padding: 10px; background-color: #ffffcc; border: 1px solid #f0f0f0; font-weight: bold;}
</style>

<script type="text/javascript">
/* <![CDATA[ */
function _checkForm()
{
var url=document.form.url.value;
var pass=document.form.pass.value;
var pass2=document.form.pass2.value;
var email=document.form.email.value;
var license=document.form.license.checked;
var rewrite=document.form.rewrite.checked;

  if (url=="" || url=="http://") {alert("Nebyla zadána adresa serveru."); return false;}
  if (pass=="" || pass2=="") {alert("Nebylo vyplněno heslo."); return false;}
  if (pass!=pass2) {alert("Zadaná hesla nejsou shodná."); return false;}
  if (email=="" || email=="@") {alert("Nebyla zadána e-mailová adresa."); return false;}
  if (!license) {alert("Musíte souhlasit s licencí pro spuštění instalace."); return false;}
  if (rewrite) {return confirm("Opravdu chcete přepsat existující tabulky se stejnými jmény?");}

}

function _autoFill()
{
var loc = new String(document.location);
if (loc.substring(loc.length-1, loc.length)=="/") loc = loc.substring(0, loc.length-1);

  //odrezani posledniho adresare
  var spos=0;
  for (var x=loc.length-1; x>=0; x-=1) {
    if (loc.substring(x, x+1)=="/") {spos=x; break;}
  }

  //prepsani hodnoty
  if (spos!=0) {
  loc=loc.substring(0, spos);
  document.form.url.value=loc;
  }

}
/* ]]> */
</script>

</head>

<body onload="_autoFill();">

<?php
echo $msg;

?>

<form action="./" method="post" name="form">
<div id="container">
<div>

  <h1>Instalace databáze SunLight CMS <?php
echo _systemversion;

?></h1>
  <p>Tato stránka slouží k instalaci databáze (vytvoření tabulek) pro redakční systém SunLight CMS <?php
echo _systemversion;

?>. Před spuštěním instalace se ujistěte, že máte nastaveny správné přístupové údaje v souboru <em>config.php</em> a že není databáze již nainstalovaná (pro více instalací na jedné databázi je potřeba změnit prefix)! Pokud chcete databázi přeinstalovat, aktivujte možnost <em>Přepsat tabulky</em>.</p>

  <hr />

  <h2>Licenční ujednání</h2>
  <p>Copyright (c) 2006-2017 Pavel Batečko (ShiraNai7)</p>

  <p>Permission is hereby granted, free of charge, to any person
  obtaining a copy of this software and associated documentation
  files (the "Software"), to deal in the Software without
  restriction, including without limitation the rights to use,
  copy, modify, merge, publish, distribute, sublicense, and/or sell
  copies of the Software, and to permit persons to whom the
  Software is furnished to do so, subject to the following
  conditions:</p>

  <p>The above copyright notice and this permission notice shall be
  included in all copies or substantial portions of the Software.</p>

  <p>THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
  EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
  OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
  NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
  HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
  WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
  FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
  OTHER DEALINGS IN THE SOFTWARE.</p>

  <br />

  <label><input type="checkbox" name="license" value="1" /> souhlasím s licenčním ujednáním</label>
  <br /><br />

  <hr />

  <h2>Nastavení instalace</h2>
  <table>

  <tr>
  <td><strong>Adresa serveru</strong></td>
  <td><input type="text" size="50" name="url" value="http://" /></td>
  <td><small>adresa ke kořenovému adresáři systému v absolutním tvaru (důležité)</small></td>
  </tr>

  <tr>
  <td><strong>Přístupové heslo</strong></td>
  <td><input type="password" size="50" name="pass" /></td>
  <td><small>heslo pro účet hlavního administrátora</small></td>
  </tr>

  <tr>
  <td><strong>Přístupové heslo</strong></td>
  <td><input type="password" size="50" name="pass2" /></td>
  <td><small>heslo pro účet hlavního administrátora (kontrola)</small></td>
  </tr>

  <tr>
  <td><strong>E-mail</strong></td>
  <td><input type="text" size="50" name="email" value="@" /></td>
  <td><small>e-mail pro účet hlavního administrátora (pro obnovování ztraceného hesla)</small></td>
  </tr>

  <tr>
  <td><strong>Titulek stránek</strong></td>
  <td><input type="text" size="50" name="title" value="Lorem ipsum" /></td>
  <td><small>titulek stránek</small></td>
  </tr>

  <tr>
  <td><strong>Popis stránek</strong></td>
  <td><input type="text" size="50" name="descr" value="bez popisu" /></td>
  <td><small>krátký popis stránek</small></td>
  </tr>

  <tr>
  <td><strong>Klíčová slova</strong></td>
  <td><input type="text" size="50" name="kwrds" value="" /></td>
  <td><small>oddělená čárkami</small></td>
  </tr>

  <tr>
  <td><strong>Přepsat tabulky</strong></td>
  <td><input type="checkbox" name="rewrite" value="1" /></td>
  <td><small>tato volba způsobí, že existující tabulky se stejnými jmény budou přepsány</small></td>
  </tr>

  </table>
  <br />

  <input type="submit" value="Spustit instalaci databáze &gt;" onclick="return _checkForm();" />

</div>
</div>
</form>

</body>
</html>
