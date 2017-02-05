<?php

// kontrola jadra a prostredi
if (!defined('_core') || !_dev || version_compare(PHP_VERSION, '5.3.0', '<')) {
    return;
}

require dirname(__FILE__) . '/devkit_init.php';
