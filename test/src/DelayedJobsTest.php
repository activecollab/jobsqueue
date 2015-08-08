<?php

  namespace ActiveCollab\JobsQueue\Test;

  use ActiveCollab\JobsQueue\Dispatcher;
  use ActiveCollab\JobsQueue\Jobs\Job;
  use ActiveCollab\JobsQueue\Queue\MySql;
  use ActiveCollab\JobsQueue\Test\Jobs\Failing;
  use ActiveCollab\JobsQueue\Test\Jobs\Inc;
  use mysqli;
  use Exception;

  /**
   * @package ActiveCollab\JobsQueue\Test
   */
  class DelayedJobsTest extends TestCase
  {
    /**
     * @var mysqli
     */
    private $link;

    /**
     * @var Dispatcher
     */
    private $dispatcher;

    /**
     * @var string|null
     */
    private $last_failed_job = null, $last_failure_message = null;

    /**
     * Set up test environment
     */
    public function setUp()
    {
      parent::setUp();

      $this->link = new \MySQLi('localhost', 'root', '', 'activecollab_jobs_queue_test');
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
     * Test getting a delayed job
     */
    public function testGettingDelayedJob()
    {
      $this->assertEquals(1, $this->dispatcher->dispatch(new Inc([ 'number' => 123, 'delay' => 1 ])));

      $this->assertNull($this->dispatcher->getQueue()->nextInLine());

      sleep(1);

      $next_in_line = $this->dispatcher->getQueue()->nextInLine();

      $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Inc', $next_in_line);
      $this->assertEquals(1, $next_in_line->getQueueId());
    }

    public function testDelayIsAppliedToFailedAttempts()
    {
      $this->assertRecordsCount(0);

      $this->assertEquals(1, $this->dispatcher->dispatch(new Failing([ 'delay' => 1, 'attempts' => 2 ])));

      $this->assertRecordsCount(1);

      // First attempt
      $this->assertNull($this->dispatcher->getQueue()->nextInLine());

      sleep(1);

      $next_in_line = $this->dispatcher->getQueue()->nextInLine();

      $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Failing', $next_in_line);
      $this->assertEquals(1, $next_in_line->getQueueId());

      $this->dispatcher->getQueue()->execute($next_in_line);

      // Second attempt
      $this->assertRecordsCount(1);
      $this->assertAttempts(1, $next_in_line->getQueueId());

      $this->assertNull($this->dispatcher->getQueue()->nextInLine()); // Not yet available

      sleep(1);

      $next_in_line = $this->dispatcher->getQueue()->nextInLine();

      $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Failing', $next_in_line);
      $this->assertEquals(1, $next_in_line->getQueueId());

      $this->dispatcher->getQueue()->execute($next_in_line);
      $this->assertRecordsCount(0);
    }

    /**
     * Check number of records in memories table
     *
     * @param integer $expected
     */
    private function assertRecordsCount($expected)
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
    private function assertAttempts($expected, $job_id)
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