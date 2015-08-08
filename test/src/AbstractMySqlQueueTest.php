<?php

  namespace ActiveCollab\JobsQueue\Test;

  use ActiveCollab\JobsQueue\Dispatcher;
  use ActiveCollab\JobsQueue\Jobs\Job;
  use ActiveCollab\JobsQueue\Queue\MySql;
  use mysqli;
  use Exception;
  use RuntimeException;

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
     * @var Dispatcher
     */
    protected $dispatcher;

    /**
     * @var string|null
     */
    protected $last_failed_job = null, $last_failure_message = null;

    /**
     * Set up test environment
     */
    public function setUp()
    {
      parent::setUp();

      $this->link = new \MySQLi('localhost', 'root', '', 'activecollab_jobs_queue_test');

      if ($this->link->connect_error) {
        throw new \RuntimeException('Failed to connect to database. MySQL said: ' . $this->link->connect_error);
      }

      $this->link->query('DROP TABLE IF EXISTS `' . MySql::TABLE_NAME . '`');

      $queue = new MySql($this->link);
      $queue->onJobFailure(function(Job $job, Exception $reason) {
        $this->last_failed_job = get_class($job);
        $this->last_failure_message = $reason->getMessage();
      });

      $this->dispatcher = new Dispatcher($queue);

      $this->assertCount(0, $this->dispatcher->getQueue());
    }

    /**
     * Tear down test environment
     */
    public function tearDown()
    {
      $this->link->query('DROP TABLE IF EXISTS `' . MySql::TABLE_NAME . '`');
      $this->link->close();

      $this->last_failed_job = $this->last_failure_message = null;

      parent::tearDown();
    }

    /**
     * Check number of records in memories table
     *
     * @param integer $expected
     */
    protected function assertRecordsCount($expected)
    {
      $result = $this->link->query('SELECT COUNT(`id`) AS "record_count" FROM `' . MySql::TABLE_NAME . '`');
      $this->assertEquals(1, $result->num_rows);
      $this->assertEquals($expected, (integer) $result->fetch_assoc()['record_count']);
    }

    /**
     * Check if attempts value for the given job has an expected value
     *
     * @param integer|null $expected
     * @param integer      $job_id
     */
    protected function assertAttempts($expected, $job_id)
    {
      $result = $this->link->query('SELECT `attempts` FROM `' . MySql::TABLE_NAME . "` WHERE '" . $this->link->escape_string((string) $job_id) . "'");

      if ($expected === null) {
        $this->assertEquals(0, $result->num_rows);
      } else {
        $this->assertEquals(1, $result->num_rows);
        $this->assertEquals($expected, (integer) $result->fetch_assoc()['attempts']);
      }
    }
  }