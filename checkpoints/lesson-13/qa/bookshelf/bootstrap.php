<?php
// Loads what the Bookshelf unit tests need, without starting the framework: no web server, no database, no .env
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
require $root . '/framework/core/Validator.php';
require $root . '/application/controllers/Bookshelf/BookForm.php';
