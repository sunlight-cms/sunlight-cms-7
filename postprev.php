<?php
/* ---  incializace jadra  --- */
require './require/load.php';
SL::init('./');

/* ---  zpracovani  --- */

_checkKeys('_POST', array('content'));
echo _parsePost(_htmlStr(strval($_POST['content'])));
