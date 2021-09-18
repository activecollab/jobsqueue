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
use ActiveCollab\JobsQueue\Jobs\Job;
use ActiveCollab\JobsQueue\JobsDispatcher;
use ActiveCollab\JobsQueue\Queue\MySqlQueue;
use ActiveCollab\JobsQueue\Queue\QueueInterface;
use ActiveCollab\JobsQueue\Test\Base\TestCase;
use Exception;
use mysqli;
use RuntimeException;

abstract class IntegratedMySqlQueueTest extends TestCase
{
    protected mysqli $link;
    protected MysqliConnection $connection;
    protected QueueInterface $queue;
    protected JobsDispatcher $dispatcher;
    protected ?string $last_failed_job = null;
    protected ?string $last_failure_message = null;

    /**
     * Set up test environment.
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->link = new mysqli('localhost', 'root', '', 'activecollab_jobs_queue_test');

        if ($this->link->connect_error) {
            throw new RuntimeException('Failed to connect to database. MySQL said: ' . $this->link->connect_error);
        }

        $this->connection = new MysqliConnection($this->link);
        $this->connection->execute('DROP TABLE IF EXISTS `' . MySqlQueue::JOBS_TABLE_NAME . '`');

        $this->queue = new MySqlQueue($this->connection);
        $this->queue->onJobFailure(
            function (Job $job, Exception $reason) {
                $this->last_failed_job = get_class($job);
                $this->last_failure_message = $reason->getMessage();
            }
        );

        $this->dispatcher = new JobsDispatcher($this->queue);

        $this->assertCount(0, $this->dispatcher->getQueue());
    }

    protected function tearDown(): void
    {
        $this->connection->dropTable(MySqlQueue::BATCHES_TABLE_NAME);
        $this->connection->dropTable(MySqlQueue::JOBS_TABLE_NAME);
        $this->connection->dropTable(MySqlQueue::FAILED_JOBS_TABLE_NAME);
        $this->connection->dropTable('email_log');

        $this->link->close();

        parent::tearDown();
    }

    /**
     * Check number of records in jobs queue table.
     */
    protected function assertRecordsCount(int $expected): void
    {
        $this->assertSame(
            $expected,
            $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . MySqlQueue::JOBS_TABLE_NAME . '`')
        );
    }

    /**
     * Check number of records in failed jobs queue table.
     */
    protected function assertFailedRecordsCount(int $expected): void
    {
        $this->assertSame(
            $expected,
            $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . MySqlQueue::FAILED_JOBS_TABLE_NAME . '`')
        );
    }

    /**
     * Check if attempts value for the given job has an expected value.
     */
    protected function assertAttempts(?int $expected, int $job_id): void
    {
        $result = $this->connection->executeFirstCell(
            'SELECT `attempts` FROM `' . MySqlQueue::JOBS_TABLE_NAME . '` WHERE id = ?', $job_id
        );

        if ($expected === null) {
            $this->assertEmpty($result);
        } else {
            $this->assertSame($expected, (integer) $result);
        }
    }
}
