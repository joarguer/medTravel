<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$log = __DIR__ . '/../logs/test_simple.log';
file_put_contents($log, "Test simple ejecutado\n", FILE_APPEND);
echo "OK";
