<?php

namespace ActiveCollab\JobQueue\Test\Commands;

use ActiveCollab\DatabaseConnection\Connection\MysqliConnection;
use ActiveCollab\DatabaseConnection\ConnectionInterface;
use ActiveCollab\JobsQueue\Test\Fixtures\Container;
use ActiveCollab\JobsQueue\Dispatcher;
use ActiveCollab\JobsQueue\DispatcherInterface;
use ActiveCollab\JobsQueue\Queue\MySqlQueue;
use ActiveCollab\JobsQueue\Queue\QueueInterface;
use Interop\Container\ContainerInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use mysqli;
use Psr\Log\LoggerInterface;

/**
 */
abstract class TestCase extends \PHPUnit_Framework_TestCase
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

    /**
     * Set up test environment.
     */
    public function setUp()
    {
        parent::setUp();

        $this->link = new \MySQLi('localhost', 'root', '');

        if ($this->link->connect_error) {
            throw new \RuntimeException('Failed to connect to database. MySQL said: '.$this->link->connect_error);
        }

        if (!$this->link->select_db('activecollab_jobs_queue_commands_test')) {
            throw new \RuntimeException('Failed to select database.');
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

    /**
     * Tear down test environment.
     */
    public function tearDown()
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
