<?php

/*
 * This file is part of the Active Collab Jobs Queue.
 *
 * (c) A51 doo <info@activecollab.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace ActiveCollab\JobsQueue\Test;

use ActiveCollab\DatabaseConnection\Connection\MysqliConnection;
use ActiveCollab\JobsQueue\Dispatcher;
use ActiveCollab\JobsQueue\Jobs\Job;
use ActiveCollab\JobsQueue\Queue\MySqlQueue;
use Exception;
use mysqli;

/**
 * @package ActiveCollab\JobsQueue\Test
 */
abstract class AbstractMySqlQueueTest extends TestCase
{
    /**
     * @var mysqli
     */
    protected $link;

    /**
     * @var MysqliConnection
     */
    protected $connection;

    /**
     * @var Dispatcher
     */
    protected $dispatcher;

    /**
     * @var string|null
     */
    protected $last_failed_job = null, $last_failure_message = null;

    /**
     * Set up test environment.
     */
    public function setUp()
    {
        parent::setUp();

        $this->link = new \MySQLi('localhost', 'root', '', 'activecollab_jobs_queue_test');

        if ($this->link->connect_error) {
            throw new \RuntimeException('Failed to connect to database. MySQL said: ' . $this->link->connect_error);
        }

        $this->connection = new MysqliConnection($this->link);
        $this->connection->execute('DROP TABLE IF EXISTS `' . MySqlQueue::JOBS_TABLE_NAME . '`');

        $queue = new MySqlQueue($this->connection);
        $queue->onJobFailure(function (Job $job, Exception $reason) {
            $this->last_failed_job = get_class($job);
            $this->last_failure_message = $reason->getMessage();
        });

        $this->dispatcher = new Dispatcher($queue);

        $this->assertCount(0, $this->dispatcher->getQueue());
    }

    /**
     * Tear down test environment.
     */
    public function tearDown()
    {
        $this->connection->execute('DROP TABLE IF EXISTS `' . MySqlQueue::BATCHES_TABLE_NAME . '`');
        $this->connection->execute('DROP TABLE IF EXISTS `' . MySqlQueue::JOBS_TABLE_NAME . '`');
        $this->connection->execute('DROP TABLE IF EXISTS `' . MySqlQueue::FAILED_JOBS_TABLE_NAME . '`');
        $this->link->close();

        $this->last_failed_job = $this->last_failure_message = null;

        parent::tearDown();
    }

    /**
     * Check number of records in jobs queue table.
     *
     * @param int $expected
     */
    protected function assertRecordsCount($expected)
    {
        $this->assertSame($expected, $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . MySqlQueue::JOBS_TABLE_NAME . '`'));
    }

    /**
     * Check number of records in failed jobs queue table.
     *
     * @param int $expected
     */
    protected function assertFailedRecordsCount($expected)
    {
        $this->assertSame($expected, $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . MySqlQueue::FAILED_JOBS_TABLE_NAME . '`'));
    }

    /**
     * Check if attempts value for the given job has an expected value.
     *
     * @param int|null $expected
     * @param int      $job_id
     */
    protected function assertAttempts($expected, $job_id)
    {
        $result = $this->connection->executeFirstCell('SELECT `attempts` FROM `' . MySqlQueue::JOBS_TABLE_NAME . '` WHERE id = ?', $job_id);

        if ($expected === null) {
            $this->assertEmpty($result);
        } else {
            $this->assertSame($expected, (integer) $result);
        }
    }
}
