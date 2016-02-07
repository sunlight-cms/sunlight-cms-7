<?php
/* ---  incializace jadra  --- */
require '../require/load.php';
define('_header', 'Content-Type: text/plain; charset=UTF-8');
SL::init('../', null, true,  false, true, false);

/* --- autorizace --- */
$auth = explode(':', SL::$settings['cron_auth'], 2);
if (
    2 !== sizeof($auth)
    || !isset($_GET['user'], $_GET['password'])
    || $_GET['user'] !== $auth[0]
    || $_GET['password'] !== $auth[1]
) {
    header('HTTP/1.0 401 Unauthorized');
    echo 'Unauthorized';
    exit(1);
}

/* ---  spusteni cronu  --- */

// priprava
$start = microtime(true);
$names = array();
function cron_log_name($args)
{
    $GLOBALS['names'][] = $args['name'];
}
_extend('reg', 'sys.cron', 'cron_log_name');

// spusteni
SL::runCron();

// vysledek
echo 'OK(', round((microtime(true) - $start) * 1000), 'ms) ', implode(', ', $names);
