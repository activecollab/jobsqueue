<?php

/*
 * This file is part of the Active Collab Jobs Queue.
 *
 * (c) A51 doo <info@activecollab.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

date_default_timezone_set('GMT');

require dirname(__DIR__) . '/vendor/autoload.php';

$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';
$dbName = getenv('DB_NAME') ?: 'activecollab_jobs_queue_test';

$mysqlCmd = 'mysql -u ' . escapeshellarg($dbUser);
if (!empty($dbPass)) {
    $mysqlCmd .= ' -p' . escapeshellarg($dbPass);
}

exec($mysqlCmd . ' -e "DROP DATABASE IF EXISTS ' . $dbName . '"');
exec($mysqlCmd . ' -e "CREATE DATABASE ' . $dbName . '"');
