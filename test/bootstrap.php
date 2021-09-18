<?php

/*
 * This file is part of the Active Collab Jobs Queue.
 *
 * (c) A51 doo <info@activecollab.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Bootstrap test case.
 */
date_default_timezone_set('GMT');

require dirname(__DIR__) . '/vendor/autoload.php';

exec('mysql -u root -e "DROP DATABASE IF EXISTS activecollab_jobs_queue_test"');
exec('mysql -u root -e "CREATE DATABASE activecollab_jobs_queue_test"');

require __DIR__ . '/src/Base/TestCase.php';
require __DIR__ . '/src/Base/IntegratedTestCase.php';
