<?php
  defined('BASE_PATH') || define('BASE_PATH', realpath(__DIR__));

  date_default_timezone_set('GMT');

  require dirname(__DIR__) . '/vendor/autoload.php';
  require __DIR__ . '/src/TestCase.php';