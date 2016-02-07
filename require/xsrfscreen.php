<?php

// kontrola jadra
if (!defined('_core')) exit;

// titulek
if (_template_autoheadings == 1) $output .= "<h1>" . $_lang['xsrf.title'] . "</h1>\n";

// zprava + formular
$output .= _formMessage(3, $_lang['xsrf.msg'] . '<ul><li>' . str_replace('*domain*', _getDomain(), $_lang['xsrf.warning']) . '</li></ul>');
$output .= "<form method='post'>
" . _getPostdata(false, null, array('_security_token')) . _xsrfProtect() . "
<p><input type='submit' value='" . $_lang['xsrf.button'] . "' /></p>
</form>\n";
