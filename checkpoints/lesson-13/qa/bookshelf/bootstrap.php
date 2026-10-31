<?php
// Loads what the Bookshelf unit tests need, without starting the framework: no web server, no database, no .env
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
foreach (array('Validator', 'Context', 'ServiceError', 'Capability', 'Service') as $class) {
    require_once $root . "/framework/core/$class.php";
}
foreach (glob($root . '/application/services/Bookshelf/*.php') as $service) {
    require_once $service;
}
