<?php
/* ---  incializace jadra  --- */
if (!defined('_core')) {
    require '../require/load.php';
    SL::init('../');
}

/* ---  odhlaseni a presmerovani  --- */
if (_xsrfCheck(true)) _userLogout();
_returnHeader();
