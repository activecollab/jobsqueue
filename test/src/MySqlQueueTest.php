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
  class MySqlQueueTest extends TestCase
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
     * Test if job queue table is prepared for testing
     */
    public function testJobsQueueTableIsCreated()
    {
      $result = $this->link->query('SHOW TABLES');
      $this->assertEquals(1, $result->num_rows);
      $this->assertEquals(MySql::TABLE_NAME, $result->fetch_assoc()['Tables_in_activecollab_jobs_queue_test']);
    }

    /**
     * Test jobs are added to the queue
     */
    public function testJobsAreAddedToTheQueue()
    {
      $this->assertRecordsCount(0);

      $this->assertEquals(1, $this->dispatcher->dispatch(new Inc([ 'number' => 123 ])));
      $this->assertEquals(2, $this->dispatcher->dispatch(new Inc([ 'number' => 456 ])));
      $this->assertEquals(3, $this->dispatcher->dispatch(new Inc([ 'number' => 789 ])));

      $this->assertRecordsCount(3);
    }

    /**
     * Make sure that full job class is recorded
     */
    public function testFullJobClassIsRecorded()
    {
      $this->assertEquals(1, $this->dispatcher->dispatch(new Inc([ 'number' => 123 ])));

      $result = $this->link->query('SELECT * FROM `' . MySql::TABLE_NAME . '` WHERE id = 1');
      $this->assertInstanceOf('mysqli_result', $result);
      $this->assertEquals(1, $result->num_rows);

      $row = $result->fetch_assoc();

      $this->assertArrayHasKey('type', $row);
      $this->assertEquals('ActiveCollab\JobsQueue\Test\Jobs\Inc', $row['type']);
    }

    /**
     * Test if priority is properly set
     */
    public function testPriorityIsProperlySetFromData()
    {
      $this->assertEquals(1, $this->dispatcher->dispatch(new Inc([ 'number' => 123, 'priority' => Job::HAS_HIGHEST_PRIORITY ])));

      $result = $this->link->query('SELECT * FROM `' . MySql::TABLE_NAME . '` WHERE id = 1');
      $this->assertInstanceOf('mysqli_result', $result);
      $this->assertEquals(1, $result->num_rows);

      $row = $result->fetch_assoc();

      $this->assertArrayHasKey('priority', $row);
      $this->assertEquals((string) Job::HAS_HIGHEST_PRIORITY, $row['priority']);
    }

    /**
     * Test job data is properly serialized to JSON
     */
    public function testJobDataIsSerializedToJson()
    {
      $this->assertEquals(1, $this->dispatcher->dispatch(new Inc([ 'number' => 123 ])));

      $result = $this->link->query('SELECT * FROM `' . MySql::TABLE_NAME . '` WHERE id = 1');
      $this->assertInstanceOf('mysqli_result', $result);
      $this->assertEquals(1, $result->num_rows);

      $row = $result->fetch_assoc();

      $this->assertArrayHasKey('data', $row);
      $this->assertStringStartsWith('{', $row['data']);
      $this->assertStringEndsWith('}', $row['data']);

      $decoded_data = json_decode($row['data'], true);
      $this->assertInternalType('array', $decoded_data);

      $this->assertArrayHasKey('number', $decoded_data);
      $this->assertEquals(123, $decoded_data['number']);
      $this->assertArrayHasKey('priority', $decoded_data);
      $this->assertEquals(Job::NOT_A_PRIORITY, $decoded_data['priority']);
    }

    /**
     * Test that jobs are not reserved by default
     */
    public function testJobsAreNotReservedByDefault()
    {
      $this->assertEquals(1, $this->dispatcher->dispatch(new Inc([ 'number' => 123 ])));

      $result = $this->link->query('SELECT * FROM `' . MySql::TABLE_NAME . '` WHERE id = 1');
      $this->assertInstanceOf('mysqli_result', $result);
      $this->assertEquals(1, $result->num_rows);

      $row = $result->fetch_assoc();

      $this->assertArrayHasKey('reservation_key', $row);
      $this->assertNull($row['reservation_key']);

      $this->assertArrayHasKey('reserved_at', $row);
      $this->assertNull($row['reserved_at']);
    }

    /**
     * Test that jobs start with zero attempts
     */
    public function testAttemptsAreZeroByDefault()
    {
      $this->assertEquals(1, $this->dispatcher->dispatch(new Inc([ 'number' => 123 ])));

      $result = $this->link->query('SELECT * FROM `' . MySql::TABLE_NAME . '` WHERE id = 1');
      $this->assertInstanceOf('mysqli_result', $result);
      $this->assertEquals(1, $result->num_rows);

      $row = $result->fetch_assoc();

      $this->assertArrayHasKey('attempts', $row);
      $this->assertEquals('0', $row['attempts']);
    }

    /**
     * Test next in line when no priority is set (FIFO)
     */
    public function testNextInLineReturnsNullOnNoJobs()
    {
      $this->assertRecordsCount(0);
      $this->assertNull($this->dispatcher->getQueue()->nextInLine());
    }

    /**
     * Test next in line when no priority is set (FIFO)
     */
    public function testNextInLine()
    {
      $this->assertRecordsCount(0);

      $this->assertEquals(1, $this->dispatcher->dispatch(new Inc([ 'number' => 123 ])));
      $this->assertEquals(2, $this->dispatcher->dispatch(new Inc([ 'number' => 456 ])));

      $this->assertRecordsCount(2);

      $next_in_line = $this->dispatcher->getQueue()->nextInLine();

      $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Inc', $next_in_line);
      $this->assertEquals(1, $next_in_line->getQueueId());
    }

    /**
     * Test priority tasks are front in line
     */
    public function testPriorityJobsAreFrontInLine()
    {
      $this->assertRecordsCount(0);

      $this->assertEquals(1, $this->dispatcher->dispatch(new Inc([ 'number' => 123 ])));
      $this->assertEquals(2, $this->dispatcher->dispatch(new Inc([ 'number' => 456, 'priority' => Job::HAS_PRIORITY ])));
      $this->assertEquals(3, $this->dispatcher->dispatch(new Inc([ 'number' => 789, 'priority' => Job::HAS_PRIORITY ])));

      $this->assertRecordsCount(3);

      $next_in_line = $this->dispatcher->getQueue()->nextInLine();

      $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Inc', $next_in_line);
      $this->assertEquals(2, $next_in_line->getQueueId());
    }

    /**
     * Test if job execution removes it from the queue
     */
    public function testExecuteJobRemotesItFromQueue()
    {
      $this->assertRecordsCount(0);

      $this->assertEquals(1, $this->dispatcher->dispatch(new Inc([ 'number' => 123 ])));

      $next_in_line = $this->dispatcher->getQueue()->nextInLine();

      $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Inc', $next_in_line);
      $this->assertEquals(1, $next_in_line->getQueueId());

      $job_execution_result = $this->dispatcher->getQueue()->execute($next_in_line);
      $this->assertEquals(124, $job_execution_result);

      $this->assertRecordsCount(0);
    }

    /**
     * Test job failure
     */
    public function testJobFailure()
    {
      $this->assertRecordsCount(0);

      $this->assertEquals(1, $this->dispatcher->dispatch(new Failing()));

      $next_in_line = $this->dispatcher->getQueue()->nextInLine();

      $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Failing', $next_in_line);
      $this->assertEquals(1, $next_in_line->getQueueId());

      $this->dispatcher->getQueue()->execute($next_in_line);

      $this->assertEquals('ActiveCollab\JobsQueue\Test\Jobs\Failing', $this->last_failed_job);
      $this->assertEquals('Built to fail!', $this->last_failure_message);

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
  }