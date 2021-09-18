<?php

/*
 * This file is part of the Active Collab Jobs Queue.
 *
 * (c) A51 doo <info@activecollab.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace ActiveCollab\JobsQueue\Test\Base;

use ActiveCollab\DatabaseConnection\Connection\MysqliConnection;
use ActiveCollab\JobsQueue\Queue\MySqlQueue;
use mysqli;
use RuntimeException;

class IntegratedConnectionTestCase extends TestCase
{
    protected mysqli $link;
    protected MysqliConnection $connection;

    public function setUp(): void
    {
        parent::setUp();

        $this->link = new mysqli('localhost', 'root', '', 'activecollab_jobs_queue_test');

        if ($this->link->connect_error) {
            throw new RuntimeException('Failed to connect to database. MySQL said: ' . $this->link->connect_error);
        }

        $this->connection = new MysqliConnection($this->link);
        $this->connection->execute('DROP TABLE IF EXISTS `' . MySqlQueue::JOBS_TABLE_NAME . '`');
    }

    protected function tearDown(): void
    {
        $this->link->close();

        parent::tearDown();
    }
}
