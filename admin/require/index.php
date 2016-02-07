<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  pripava promennych  --- */
$mysqlver = DB::$con->server_info;
if ($mysqlver != null and mb_substr_count($mysqlver, "-") != 0) $mysqlver = mb_substr($mysqlver, 0, strpos($mysqlver, "-"));

$software = getenv('SERVER_SOFTWARE');
if (mb_strlen($software) > 16) $software = substr($software, 0, 13) . "...";

/* ---  vystup  --- */

// priprava vlastniho obsahu indexu
if (SL::$settings['admin_index_custom'] !== '') {

    $custom = '
<iframe src="index-custom.php" id="index_custom_iframe" frameborder="0" allowtransparency="true"></iframe>
<script type="text/javascript">/* <![CDATA[ */
$("#index_custom_iframe").load(function(){
    try {
        var doc = this.contentDocument;
         this.style.height = Math.max(
            Math.max(doc.body.scrollHeight, doc.body.scrollHeight),
            Math.max(doc.body.offsetHeight, doc.body.offsetHeight),
            Math.max(doc.body.clientHeight, doc.body.clientHeight)
         ) + "px";
    } catch (e) {}
});
/* ]]> */</script>
';

}
// upozorneni na logout
$logout_warning = '';
$maxltime = ini_get('session.gc_maxlifetime');
if (!empty($maxltime) && !isset($_COOKIE[_sessionprefix . 'persistent_key'])) {
    $logout_warning = _admin_smallNote(sprintf($_lang['admin.index.logoutwarn'], round($maxltime / 60)), false, 'warn');
}

// vystup
$output .= "
<table id='indextable'>


<tr class='valign-top'>

<td>" . ((isset($custom) && SL::$settings['admin_index_custom_pos'] == 0) ? $custom : "
  <h1>" . $_lang['admin.menu.index'] . "</h1>
  <p>" . $_lang['admin.index.p'] . "</p>
  <ul>
  <li><a href='http://sunlight.shira.cz/' target='_blank'>" . $_lang['admin.link.web'] . "</a></li>
  <li><a href='http://sunlight.shira.cz/feedback/docs.php' target='_blank'>" . $_lang['admin.link.docs'] . "</a></li>
  <li><a href='http://sunlight.shira.cz/feedback/forum.php' target='_blank'>" . $_lang['admin.link.forum'] . "</a></li>
  </ul>
  " . $logout_warning . "
  ") . "
</td>

<td width='200'>
  <h2>" . $_lang['admin.index.box'] . "</h2>
  <p>
  <strong>" . $_lang['global.version'] . ":</strong> " . _systemversion . ' <small>' . ((_systemstate === 2) ? '(rev.' . _systemstate_revision . ')' : SL::$states[_systemstate] . _systemstate_revision) . '</small>' . "<br />
  <strong>" . $_lang['admin.index.box.latest'] . ":</strong> <span id='hook'>---</span><br />
  <strong>PHP:</strong> " . PHP_VERSION . "<br />
  <strong>MySQL:</strong> " . $mysqlver . "<br />
  </p>
</td>

</tr>

" . ((isset($custom) && SL::$settings['admin_index_custom_pos'] == 1) ? '<tr><td colspan="2">' . $custom . '</td></tr>' : '') . "


</table>

<script type='text/javascript' src='http://sunlight.shira.cz/feedback/hook.php?ver=" . _systemversion . "&state=" . _systemstate . "&rev=" . _systemstate_revision . "'></script>
";

if (_loginright_group == 1) $output .= '<p align="right"><a href="index.php?p=index-edit"><img src="images/icons/edit.png" alt="edit" class="icon" /> ' . $_lang['admin.index.edit.link'] . '</a></p>';
