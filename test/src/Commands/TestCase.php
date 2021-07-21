<?php

/*
 * This file is part of the Active Collab Jobs Queue.
 *
 * (c) A51 doo <info@activecollab.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace ActiveCollab\JobQueue\Test\Commands;

use ActiveCollab\DatabaseConnection\Connection\MysqliConnection;
use ActiveCollab\DatabaseConnection\ConnectionInterface;
use ActiveCollab\JobsQueue\Dispatcher;
use ActiveCollab\JobsQueue\DispatcherInterface;
use ActiveCollab\JobsQueue\Queue\MySqlQueue;
use ActiveCollab\JobsQueue\Queue\QueueInterface;
use ActiveCollab\JobsQueue\Test\Fixtures\Container;
use Interop\Container\ContainerInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use mysqli;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var mysqli
     */
    protected $link;

    /**
     * @var ConnectionInterface
     */
    protected $connection;

    /**
     * @var QueueInterface
     */
    protected $queue;

    /**
     * @var DispatcherInterface
     */
    protected $dispatcher;

    /**
     * @var string
     */
    protected $log_file_path;

    /**
     * @var LoggerInterface
     */
    protected $log;

    /**
     * @var array
     */
    protected $config_options = false;

    public function setUp(): void
    {
        parent::setUp();

        $this->link = new mysqli('localhost', 'root', '');

        if ($this->link->connect_error) {
            throw new RuntimeException('Failed to connect to database. MySQL said: '.$this->link->connect_error);
        }

        if (!$this->link->select_db('activecollab_jobs_queue_test')) {
            throw new RuntimeException('Failed to select database.');
        }

        $this->connection = new MysqliConnection($this->link);
        $this->queue = new MySqlQueue($this->connection);
        $this->dispatcher = new Dispatcher($this->queue);

        $this->log_file_path = dirname(__DIR__).'/logs/'.date('Y-m-d').'.txt';

        if (is_file($this->log_file_path)) {
            unlink($this->log_file_path);
        }

        $this->log = new Logger('cli');

        $handler = new StreamHandler($this->log_file_path, Logger::DEBUG);

        $formatter = new LineFormatter();
        $formatter->includeStacktraces(true);

        $handler->setFormatter($formatter);

        $this->log->pushHandler($handler);

        $this->container = new Container([
            'dispatcher' => $this->dispatcher,
            'log' => $this->log,
        ]);
    }

    protected function tearDown(): void
    {
        foreach ([MySqlQueue::BATCHES_TABLE_NAME, MySqlQueue::JOBS_TABLE_NAME, MySqlQueue::FAILED_JOBS_TABLE_NAME, 'email_log'] as $table_name) {
            if ($this->connection->tableExists($table_name)) {
                $this->connection->dropTable($table_name);
            }
        }

        if ($this->link) {
            $this->link->close();
        }

        if (is_file($this->log_file_path)) {
            unlink($this->log_file_path);
        }

        parent::tearDown();
    }

    /**
     * Check number of records in jobs queue table.
     *
     * @param int $expected
     */
    protected function assertRecordsCount($expected)
    {
        $this->assertSame($expected, $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `'.MySqlQueue::JOBS_TABLE_NAME.'`'));
    }

    /**
     * Check number of records in failed jobs queue table.
     *
     * @param int $expected
     */
    protected function assertFailedRecordsCount($expected)
    {
        $this->assertSame($expected, $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `'.MySqlQueue::FAILED_JOBS_TABLE_NAME.'`'));
    }
}
